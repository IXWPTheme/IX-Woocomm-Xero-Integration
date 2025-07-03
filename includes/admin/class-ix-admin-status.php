<?php
/**
 * IX Woo Xero Integration - Admin Status Class
 * 
 * Handles all status-related functionality including:
 * - Sync status dashboard
 * - Log viewing
 * - System health checks
 */

namespace IX_Woo_Xero\Admin;

use IX_Woo_Xero\API\Xero_API;

if (!defined('ABSPATH')) {
    exit;
}

class Admin_Status {

    /**
     * The single instance of the class
     *
     * @var Admin_Status
     */
    private static $_instance = null;

    /**
     * Xero API instance
     *
     * @var Xero_API
     */
    private $xero_api;

    /**
     * Main Admin_Status Instance
     *
     * @return Admin_Status
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
        add_action('admin_init', [$this, 'register_status_actions']);
        add_action('wp_ajax_ix_woo_xero_get_sync_stats', [$this, 'get_sync_stats_ajax']);
        add_action('wp_ajax_ix_woo_xero_clear_logs', [$this, 'clear_logs_ajax']);
    }

    /**
     * Register status page actions
     */
    public function register_status_actions() {
        if (isset($_GET['page']) && $_GET['page'] === 'ix-woo-xero-status') {
            if (isset($_GET['action']) && $_GET['action'] === 'refresh-stats') {
                $this->refresh_stats();
            }
            
            if (isset($_GET['action']) && $_GET['action'] === 'download-logs') {
                $this->download_logs();
            }
        }
    }

    /**
     * Render status page
     */
    public function render_status_page() {
        $stats = $this->get_sync_stats();
        $recent_errors = $this->get_recent_errors();
        $system_status = $this->get_system_status();
        $last_sync = $this->get_last_sync_times();

        include IX_WOO_XERO_PLUGIN_DIR . 'templates/admin/status/dashboard.php';
    }

    /**
     * Get synchronization statistics
     */
    public function get_sync_stats() {
        return [
            'products' => [
                'total' => $this->get_product_count(),
                'synced' => $this->get_synced_product_count(),
                'percentage' => $this->get_sync_percentage('products')
            ],
            'orders' => [
                'total' => $this->get_order_count(),
                'synced' => $this->get_synced_order_count(),
                'percentage' => $this->get_sync_percentage('orders')
            ],
            'customers' => [
                'total' => $this->get_customer_count(),
                'synced' => $this->get_synced_customer_count(),
                'percentage' => $this->get_sync_percentage('customers')
            ],
            'subscriptions' => [
                'total' => $this->get_subscription_count(),
                'synced' => $this->get_synced_subscription_count(),
                'percentage' => $this->get_sync_percentage('subscriptions')
            ]
        ];
    }

    /**
     * Get sync stats via AJAX
     */
    public function get_sync_stats_ajax() {
        check_ajax_referer('ix_woo_xero_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'ix-woo-xero'));
        }

        wp_send_json_success($this->get_sync_stats());
    }

    /**
     * Get total product count
     */
    private function get_product_count() {
        global $wpdb;
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'product' 
             AND post_status = 'publish'"
        );
    }

    /**
     * Get synced product count
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
     * Get total order count
     */
    private function get_order_count() {
        global $wpdb;
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'shop_order' 
             AND post_status IN ('wc-processing', 'wc-completed')"
        );
    }

    /**
     * Get synced order count
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
     * Get total customer count
     */
    private function get_customer_count() {
        global $wpdb;
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->users}
             WHERE ID IN (SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'wp_capabilities' AND meta_value LIKE '%customer%')"
        );
    }

    /**
     * Get synced customer count
     */
    private function get_synced_customer_count() {
        global $wpdb;
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} 
             WHERE meta_key = '_xero_contact_id'"
        );
    }

    /**
     * Get total subscription count
     */
    private function get_subscription_count() {
        if (!class_exists('WC_Subscriptions')) {
            return 0;
        }

        global $wpdb;
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'shop_subscription'"
        );
    }

    /**
     * Get synced subscription count
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
     * Calculate sync percentage
     */
    private function get_sync_percentage($type) {
        $stats = $this->get_sync_stats();
        
        if (!isset($stats[$type]) || $stats[$type]['total'] === 0) {
            return 0;
        }

        return round(($stats[$type]['synced'] / $stats[$type]['total']) * 100, 2);
    }

    /**
     * Get recent error logs
     */
    public function get_recent_errors($limit = 10) {
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

    /**
     * Get system status information
     */
    public function get_system_status() {
        global $wpdb;
        
        return [
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
            'wc_version' => defined('WC_VERSION') ? WC_VERSION : __('Not installed', 'ix-woo-xero'),
            'plugin_version' => IX_WOO_XERO_VERSION,
            'xero_connected' => $this->xero_api->is_connected() ? __('Connected', 'ix-woo-xero') : __('Not connected', 'ix-woo-xero'),
            'memory_limit' => wp_convert_hr_to_bytes(ini_get('memory_limit')),
            'timeout' => ini_get('max_execution_time'),
            'db_version' => $wpdb->db_version(),
            'debug_mode' => defined('WP_DEBUG') && WP_DEBUG ? __('Enabled', 'ix-woo-xero') : __('Disabled', 'ix-woo-xero')
        ];
    }

    /**
     * Get last sync times
     */
    public function get_last_sync_times() {
        return [
            'products' => get_option('ix_woo_xero_last_product_sync', __('Never', 'ix-woo-xero')),
            'orders' => get_option('ix_woo_xero_last_order_sync', __('Never', 'ix-woo-xero')),
            'customers' => get_option('ix_woo_xero_last_customer_sync', __('Never', 'ix-woo-xero')),
            'subscriptions' => get_option('ix_woo_xero_last_subscription_sync', __('Never', 'ix-woo-xero'))
        ];
    }

    /**
     * Clear log files via AJAX
     */
    public function clear_logs_ajax() {
        check_ajax_referer('ix_woo_xero_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'ix-woo-xero'));
        }

        $result = $this->clear_logs();
        
        if ($result) {
            wp_send_json_success(__('Logs cleared successfully.', 'ix-woo-xero'));
        } else {
            wp_send_json_error(__('Failed to clear logs.', 'ix-woo-xero'));
        }
    }

    /**
     * Clear log files
     */
    public function clear_logs() {
        $log_handler = new \WC_Log_Handler_File();
        $log_files = $log_handler->get_log_files();
        
        if (!in_array('ix-woo-xero', $log_files)) {
            return false;
        }
        
        $log_file = $log_handler->get_log_file_path('ix-woo-xero');
        return file_put_contents($log_file, '') !== false;
    }

    /**
     * Download log files
     */
    public function download_logs() {
        check_admin_referer('download-logs');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied.', 'ix-woo-xero'));
        }

        $log_handler = new \WC_Log_Handler_File();
        $log_files = $log_handler->get_log_files();
        
        if (!in_array('ix-woo-xero', $log_files)) {
            wp_die(__('No logs found.', 'ix-woo-xero'));
        }
        
        $log_file = $log_handler->get_log_file_path('ix-woo-xero');
        $logs = file_get_contents($log_file);
        
        if (!$logs) {
            wp_die(__('No logs found.', 'ix-woo-xero'));
        }

        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="ix-woo-xero-logs-' . date('Y-m-d') . '.log"');
        header('Content-Length: ' . strlen($logs));
        
        echo $logs;
        exit;
    }

    /**
     * Refresh stats cache
     */
    public function refresh_stats() {
        check_admin_referer('refresh-stats');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied.', 'ix-woo-xero'));
        }

        // Clear any transients or cached data
        delete_transient('ix_woo_xero_sync_stats');
        
        wp_redirect(admin_url('admin.php?page=ix-woo-xero-status'));
        exit;
    }
}

Admin_Status::instance();