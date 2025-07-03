<?php
/**
 * IX Core Class
 * 
 * Main plugin class that initializes and manages all components.
 * 
 * @package IX_Woo_Xero_Integration
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class IX_Core {

    /**
     * Plugin version
     * 
     * @var string
     */
    const VERSION = '1.0.0';

    /**
     * The single instance of the class
     * 
     * @var IX_Core
     */
    protected static $_instance = null;

    /**
     * Main IX_Core instance
     * 
     * @return IX_Core
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        $this->define_constants();
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Define plugin constants
     */
    private function define_constants() {
        $this->define('IX_WOO_XERO_ABSPATH', dirname(IX_WOO_XERO_PLUGIN_FILE) . '/');
        $this->define('IX_WOO_XERO_PLUGIN_BASENAME', plugin_basename(IX_WOO_XERO_PLUGIN_FILE));
        $this->define('IX_WOO_XERO_VERSION', self::VERSION);
        $this->define('IX_WOO_XERO_API_URL', 'https://api.xero.com/api.xro/2.0/');
    }

    /**
     * Define constant if not already set
     * 
     * @param string $name
     * @param mixed $value
     */
    private function define($name, $value) {
        if (!defined($name)) {
            define($name, $value);
        }
    }

    /**
     * Include required files
     */
    private function includes() {
        // Utilities
        require_once IX_WOO_XERO_ABSPATH . 'includes/utilities/class-ix-helper.php';
        require_once IX_WOO_XERO_ABSPATH . 'includes/utilities/class-ix-logger.php';
        require_once IX_WOO_XERO_ABSPATH . 'includes/utilities/class-ix-ajax.php';

        // API
        require_once IX_WOO_XERO_ABSPATH . 'includes/api/class-ix-xero-api.php';

        // Sync classes
        require_once IX_WOO_XERO_ABSPATH . 'includes/sync/class-ix-product-sync.php';
        require_once IX_WOO_XERO_ABSPATH . 'includes/sync/class-ix-invoice-sync.php';
        require_once IX_WOO_XERO_ABSPATH . 'includes/sync/class-ix-customer-sync.php';

        // Admin classes
        if (is_admin()) {
            require_once IX_WOO_XERO_ABSPATH . 'includes/admin/class-ix-admin.php';
            require_once IX_WOO_XERO_ABSPATH . 'includes/admin/class-ix-admin-settings.php';
            require_once IX_WOO_XERO_ABSPATH . 'includes/admin/class-ix-admin-status.php';
        }

        // CLI
        if (defined('WP_CLI') && WP_CLI) {
            require_once IX_WOO_XERO_ABSPATH . 'includes/cli/class-ix-cli.php';
        }

        // Premium features
        if (IX_Helper::is_premium()) {
            require_once IX_WOO_XERO_ABSPATH . 'includes/premium/class-ix-webhooks.php';
            require_once IX_WOO_XERO_ABSPATH . 'includes/premium/class-ix-tracking.php';
            require_once IX_WOO_XERO_ABSPATH . 'includes/premium/class-ix-tax.php';
        }
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(IX_WOO_XERO_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(IX_WOO_XERO_PLUGIN_FILE, array($this, 'deactivate'));

        add_action('plugins_loaded', array($this, 'init_plugin'));
        add_action('init', array($this, 'load_textdomain'));
    }

    /**
     * Initialize the plugin
     */
    public function init_plugin() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

        // Initialize components
        $this->init_components();
    }

    /**
     * Initialize plugin components
     */
    private function init_components() {
        // Initialize API
        IX_Xero_API::init();

        // Initialize sync classes
        IX_Product_Sync::init();
        IX_Invoice_Sync::init();
        IX_Customer_Sync::init();

        // Initialize admin
        if (is_admin()) {
            IX_Admin::init();
            IX_Admin_Settings::init();
            IX_Admin_Status::init();
        }

        // Initialize CLI
        if (defined('WP_CLI') && WP_CLI) {
            IX_CLI::init();
        }

        // Initialize premium features
        if (IX_Helper::is_premium()) {
            IX_Webhooks::init();
            IX_Tracking::init();
            IX_Tax::init();
        }

        do_action('ix_woo_xero_init');
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'ix-woo-xero',
            false,
            dirname(plugin_basename(IX_WOO_XERO_PLUGIN_FILE)) . '/languages/'
        );
    }

    /**
     * Plugin activation
     */
    public function activate() {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        // Create required database tables
        $this->create_tables();

        // Set default options
        $this->set_default_options();

        // Schedule cron jobs
        $this->schedule_cron_jobs();

        // Add plugin version
        update_option('ix_woo_xero_version', self::VERSION);
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        // Clear scheduled cron jobs
        $this->clear_cron_jobs();
    }

    /**
     * Create required database tables
     */
    private function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ix_xero_sync_log (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            object_type varchar(20) NOT NULL,
            object_id bigint(20) NOT NULL,
            xero_id varchar(255) DEFAULT NULL,
            sync_date datetime NOT NULL,
            status varchar(20) NOT NULL,
            message text DEFAULT NULL,
            PRIMARY KEY (id),
            KEY object_type (object_type),
            KEY object_id (object_id),
            KEY xero_id (xero_id),
            KEY status (status)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $default_settings = array(
            'order_statuses' => array('completed', 'processing'),
            'default_account_code' => '200',
            'default_tax_type' => 'OUTPUT',
            'enable_logging' => 'yes',
            'sync_frequency' => 'daily'
        );

        if (false === get_option('ix_woo_xero_settings')) {
            add_option('ix_woo_xero_settings', $default_settings);
        }
    }

    /**
     * Schedule cron jobs
     */
    private function schedule_cron_jobs() {
        if (!wp_next_scheduled('ix_woo_xero_daily_sync')) {
            wp_schedule_event(time(), 'daily', 'ix_woo_xero_daily_sync');
        }
    }

    /**
     * Clear cron jobs
     */
    private function clear_cron_jobs() {
        wp_clear_scheduled_hook('ix_woo_xero_daily_sync');
    }

    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        echo '<div class="error"><p>';
        printf(
            __('IX Woo Xero Integration requires %s to be installed and active.', 'ix-woo-xero'),
            '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>'
        );
        echo '</p></div>';
    }

    /**
     * Get the plugin URL
     * 
     * @return string
     */
    public function plugin_url() {
        return untrailingslashit(plugins_url('/', IX_WOO_XERO_PLUGIN_FILE));
    }

    /**
     * Get the plugin path
     * 
     * @return string
     */
    public function plugin_path() {
        return untrailingslashit(plugin_dir_path(IX_WOO_XERO_PLUGIN_FILE));
    }
}