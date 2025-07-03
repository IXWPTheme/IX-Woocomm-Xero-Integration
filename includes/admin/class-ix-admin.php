<?php
/**
 * IX Woo Xero Integration - Admin Class
 * 
 * Handles all admin-facing functionality including:
 * - Settings pages
 * - Connection management
 * - Status dashboard
 * - Admin notices
 */

namespace IX_Woo_Xero\Admin;

use IX_Woo_Xero\API\Xero_API;

if (!defined('ABSPATH')) {
    exit;
}

class Admin {

    /**
     * The single instance of the class
     *
     * @var Admin
     */
    private static $_instance = null;

    /**
     * Xero API instance
     *
     * @var Xero_API
     */
    private $xero_api;

    /**
     * Main Admin Instance
     *
     * @return Admin
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
        $this->xero_api = Xero_API::instance();
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Admin menu
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        
        // Admin assets
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        
        // Plugin action links
        add_filter('plugin_action_links_' . IX_WOO_XERO_PLUGIN_BASENAME, [$this, 'plugin_action_links']);
        
        // Admin notices
        add_action('admin_notices', [$this, 'admin_notices']);
        
        // AJAX handlers
        add_action('wp_ajax_ix_woo_xero_test_connection', [$this, 'test_connection']);
        add_action('wp_ajax_ix_woo_xero_get_logs', [$this, 'get_logs']);
    }

    /**
     * Add admin menu items
     */
    public function admin_menu() {
        // Main menu item
        add_menu_page(
            __('Xero Integration', 'ix-woo-xero'),
            __('Xero Integration', 'ix-woo-xero'),
            'manage_options',
            'ix-woo-xero',
            [$this, 'settings_page'],
            'dashicons-money-alt',
            56
        );
        
        // Settings submenu
        add_submenu_page(
            'ix-woo-xero',
            __('Settings', 'ix-woo-xero'),
            __('Settings', 'ix-woo-xero'),
            'manage_options',
            'ix-woo-xero',
            [$this, 'settings_page']
        );
        
        // Status submenu
        add_submenu_page(
            'ix-woo-xero',
            __('Sync Status', 'ix-woo-xero'),
            __('Sync Status', 'ix-woo-xero'),
            'manage_options',
            'ix-woo-xero-status',
            [$this, 'status_page']
        );
        
        // Tools submenu
        add_submenu_page(
            'ix-woo-xero',
            __('Tools', 'ix-woo-xero'),
            __('Tools', 'ix-woo-xero'),
            'manage_options',
            'ix-woo-xero-tools',
            [$this, 'tools_page']
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        // API Settings
        register_setting('ix_woo_xero_api_settings', 'ix_woo_xero_client_id');
        register_setting('ix_woo_xero_api_settings', 'ix_woo_xero_client_secret');
        register_setting('ix_woo_xero_api_settings', 'ix_woo_xero_webhook_key');
        
        // Account Mapping
        register_setting('ix_woo_xero_account_settings', 'ix_woo_xero_sales_account');
        register_setting('ix_woo_xero_account_settings', 'ix_woo_xero_purchase_account');
        register_setting('ix_woo_xero_account_settings', 'ix_woo_xero_inventory_account');
        register_setting('ix_woo_xero_account_settings', 'ix_woo_xero_shipping_account');
        register_setting('ix_woo_xero_account_settings', 'ix_woo_xero_fees_account');
        
        // Sync Settings
        register_setting('ix_woo_xero_sync_settings', 'ix_woo_xero_auto_sync_products');
        register_setting('ix_woo_xero_sync_settings', 'ix_woo_xero_auto_sync_orders');
        register_setting('ix_woo_xero_sync_settings', 'ix_woo_xero_auto_sync_customers');
        register_setting('ix_woo_xero_sync_settings', 'ix_woo_xero_auto_sync_subscriptions');
        
        // Tax Settings
        register_setting('ix_woo_xero_tax_settings', 'ix_woo_xero_tax_mappings');
        
        // Tracking Settings
        register_setting('ix_woo_xero_tracking_settings', 'ix_woo_xero_tracking_mappings');
    }

    /**
     * Render settings page
     */
    public function settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Handle disconnection
        if (isset($_GET['disconnect_xero'])) {
            $this->disconnect_xero();
        }
        
        // Get all Xero accounts for mapping
        $accounts = $this->xero_api->get_accounts();
        
        // Load template
        include IX_WOO_XERO_PLUGIN_DIR . 'templates/admin/settings/main.php';
    }

    /**
     * Render status page
     */
    public function status_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $stats = [
            'products' => $this->get_synced_product_count(),
            'orders' => $this->get_synced_order_count(),
            'customers' => $this->get_synced_customer_count(),
            'subscriptions' => $this->get_synced_subscription_count(),
        ];
        
        $recent_errors = $this->get_recent_errors();
        
        include IX_WOO_XERO_PLUGIN_DIR . 'templates/admin/status/dashboard.php';
    }

    /**
     * Render tools page
     */
    public function tools_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        include IX_WOO_XERO_PLUGIN_DIR . 'templates/admin/tools/main.php';
    }

    /**
     * Enqueue admin assets
     */
    public function admin_assets($hook) {
        if (strpos($hook, 'ix-woo-xero') === false) {
            return;
        }
        
        // CSS
        wp_enqueue_style(
            'ix-woo-xero-admin',
            IX_WOO_XERO_PLUGIN_URL . 'assets/css/admin.css',
            [],
            IX_WOO_XERO_VERSION
        );
        
        // JS
        wp_enqueue_script(
            'ix-woo-xero-admin',
            IX_WOO_XERO_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'wp-util'],
            IX_WOO_XERO_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script(
            'ix-woo-xero-admin',
            'ixWooXeroVars',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ix_woo_xero_nonce'),
                'i18n' => [
                    'confirm_disconnect' => __('Are you sure you want to disconnect from Xero?', 'ix-woo-xero'),
                    'syncing' => __('Syncing...', 'ix-woo-xero'),
                    'sync_complete' => __('Sync complete!', 'ix-woo-xero'),
                    'sync_error' => __('Error during sync. Check logs for details.', 'ix-woo-xero'),
                    'no_items_selected' => __('Please select items to sync.', 'ix-woo-xero'),
                    'bulk_sync_complete' => __('Bulk sync completed successfully.', 'ix-woo-xero'),
                    'bulk_sync_error' => __('Error during bulk sync.', 'ix-woo-xero'),
                ]
            ]
        );
    }

    /**
     * Add plugin action links
     */
    public function plugin_action_links($links) {
        $action_links = [
            'settings' => sprintf(
                '<a href="%s" aria-label="%s">%s</a>',
                admin_url('admin.php?page=ix-woo-xero'),
                esc_attr__('View settings', 'ix-woo-xero'),
                __('Settings', 'ix-woo-xero')
            ),
        ];
        
        return array_merge($action_links, $links);
    }

    /**
     * Display admin notices
     */
    public function admin_notices() {
        // Connection notice
        if (!$this->xero_api->is_connected() && current_user_can('manage_options')) {
            $screen = get_current_screen();
            
            if ($screen && strpos($screen->id, 'ix-woo-xero') === false) {
                echo '<div class="notice notice-warning">';
                echo '<p>' . sprintf(
                    __('IX Woo Xero Integration is not connected to Xero. <a href="%s">Connect now</a>.', 'ix-woo-xero'),
                    admin_url('admin.php?page=ix-woo-xero')
                ) . '</p>';
                echo '</div>';
            }
        }
    }

    /**
     * Test Xero connection via AJAX
     */
    public function test_connection() {
        check_ajax_referer('ix_woo_xero_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'ix-woo-xero'));
        }
        
        $connected = $this->xero_api->is_connected();
        
        if ($connected) {
            wp_send_json_success([
                'message' => __('Successfully connected to Xero.', 'ix-woo-xero'),
                'tenant_name' => $this->xero_api->get_tenant_name()
            ]);
        } else {
            wp_send_json_error(__('Not connected to Xero.', 'ix-woo-xero'));
        }
    }

    /**
     * Get recent logs via AJAX
     */
    public function get_logs() {
        check_ajax_referer('ix_woo_xero_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'ix-woo-xero'));
        }
        
        $logs = $this->get_recent_logs();
        
        wp_send_json_success([
            'logs' => $logs
        ]);
    }

    /**
     * Disconnect from Xero
     */
    private function disconnect_xero() {
        $this->xero_api->disconnect();
        
        wp_redirect(admin_url('admin.php?page=ix-woo-xero&tab=api'));
        exit;
    }

    /**
     * Get count of synced products
     */
    private function get_synced_product_count() {
        global $wpdb;
        
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
             WHERE meta_key = '_xero_item_id' 
             AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product')"
        );
    }

    /**
     * Get count of synced orders
     */
    private function get_synced_order_count() {
        global $wpdb;
        
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
             WHERE meta_key = '_xero_invoice_id' 
             AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'shop_order')"
        );
    }

    /**
     * Get count of synced customers
     */
    private function get_synced_customer_count() {
        global $wpdb;
        
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} 
             WHERE meta_key = '_xero_contact_id'"
        );
    }

    /**
     * Get count of synced subscriptions
     */
    private function get_synced_subscription_count() {
        if (!class_exists('WC_Subscriptions')) {
            return 0;
        }
        
        global $wpdb;
        
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
             WHERE meta_key = '_xero_subscription_id' 
             AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'shop_subscription')"
        );
    }

    /**
     * Get recent logs from log file
     */
    private function get_recent_logs($limit = 50) {
        $log_handler = new \WC_Log_Handler_File();
        $log_files = $log_handler->get_log_files();
        
        if (!in_array('ix-woo-xero', $log_files)) {
            return __('No logs found.', 'ix-woo-xero');
        }
        
        $log_file = $log_handler->get_log_file_path('ix-woo-xero');
        $logs = file($log_file);
        
        if (!$logs) {
            return __('No logs found.', 'ix-woo-xero');
        }
        
        $logs = array_reverse($logs);
        $logs = array_slice($logs, 0, $limit);
        
        return '<pre>' . esc_html(implode('', $logs)) . '</pre>';
    }

    /**
     * Get recent error logs
     */
    private function get_recent_errors($limit = 10) {
        $log_handler = new \WC_Log_Handler_File();
        $log_files = $log_handler->get_log_files();
        
        if (!in_array('ix-woo-xero', $log_files)) {
            return [];
        }
        
        $log_file = $log_handler->get_log_file_path('ix-woo-xero');
        $logs = file($log_file);
        
        if (!$logs) {
            return [];
        }
        
        $errors = array_filter($logs, function($line) {
            return strpos($line, 'ERROR') !== false || strpos($line, 'WARNING') !== false;
        });
        
        return array_slice(array_reverse($errors), 0, $limit);
    }
}

// Initialize the admin class
Admin::instance();