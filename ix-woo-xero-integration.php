<?php
/**
 * Plugin Name: IX Woo Xero Integration
 * Plugin URI: https://yourdomain.com/ix-woo-xero-integration
 * Description: Seamless integration between WooCommerce and Xero accounting software
 * Version: 1.0.0
 * Author: Your Company
 * Author URI: https://yourdomain.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: ix-woo-xero
 * Domain Path: /languages
 * WC requires at least: 5.0.0
 * WC tested up to: 7.0.0
 * 
 * @package IX_Woo_Xero
 */

defined('ABSPATH') || exit;

// Define plugin constants
define('IX_WOO_XERO_VERSION', '1.0.0');
define('IX_WOO_XERO_PLUGIN_FILE', __FILE__);
define('IX_WOO_XERO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IX_WOO_XERO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('IX_WOO_XERO_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('IX_WOO_XERO_MIN_PHP_VER', '7.2.0');
define('IX_WOO_XERO_MIN_WC_VER', '5.0.0');

/**
 * Autoload classes
 */
spl_autoload_register(function ($class) {
    $prefix = 'IX_Woo_Xero\\';
    $base_dir = IX_WOO_XERO_PLUGIN_DIR . 'includes/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

/**
 * Check system requirements before loading plugin
 */
register_activation_hook(__FILE__, function() {
    // Check PHP version
    if (version_compare(PHP_VERSION, IX_WOO_XERO_MIN_PHP_VER, '<')) {
        wp_die(sprintf(
            __('IX Woo Xero Integration requires PHP %s or higher. Your server is running PHP %s.', 'ix-woo-xero'),
            IX_WOO_XERO_MIN_PHP_VER,
            PHP_VERSION
        ));
    }
    
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        wp_die(__('IX Woo Xero Integration requires WooCommerce to be installed and activated.', 'ix-woo-xero'));
    }
    
    // Check WooCommerce version
    if (version_compare(WC_VERSION, IX_WOO_XERO_MIN_WC_VER, '<')) {
        wp_die(sprintf(
            __('IX Woo Xero Integration requires WooCommerce %s or higher. You are running WooCommerce %s.', 'ix-woo-xero'),
            IX_WOO_XERO_MIN_WC_VER,
            WC_VERSION
        ));
    }
});

/**
 * Initialize the plugin
 */
add_action('plugins_loaded', function() {
    // Load translations
    load_plugin_textdomain(
        'ix-woo-xero',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );
    
    // Check for required plugins
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            ?>
            <div class="error notice">
                <p><?php _e('IX Woo Xero Integration requires WooCommerce to be installed and active.', 'ix-woo-xero'); ?></p>
            </div>
            <?php
        });
        return;
    }
    
    // Include required files
    require_once IX_WOO_XERO_PLUGIN_DIR . 'includes/class-ix-core.php';
    
    // Initialize the plugin
    IX_Woo_Xero\Core::instance();
}, 10);

/**
 * Plugin deactivation cleanup
 */
register_deactivation_hook(__FILE__, function() {
    // Clear scheduled events
    wp_clear_scheduled_hook('ix_woo_xero_daily_sync');
    
    // Delete transients
    delete_transient('ix_woo_xero_tracking_categories');
    delete_transient('ix_woo_xero_tax_rates_list');
});

/**
 * Helper function to access plugin instance
 */
function IX_Woo_Xero() {
    return IX_Woo_Xero\Core::instance();
}