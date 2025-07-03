<?php
/**
 * IX Woo Xero Integration - Uninstall
 *
 * Removes all plugin data when uninstalling the plugin.
 *
 * @package IX_Woo_Xero_Integration
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Exit if not uninstalling
if (!defined('IX_WOO_XERO_UNINSTALL')) {
    exit;
}

// Check user capabilities
if (!current_user_can('delete_plugins')) {
    wp_die(
        esc_html__('You do not have sufficient permissions to delete plugins.', 'ix-woo-xero'),
        esc_html__('Error', 'ix-woo-xero'),
        array('response' => 403)
    );
}

// Load main plugin file to get constants
if (!defined('IX_WOO_XERO_ABSPATH')) {
    $plugin_path = dirname(__FILE__);
    require_once $plugin_path . '/ix-woo-xero-integration.php';
}

/**
 * Class IX_Uninstaller
 * Handles all cleanup tasks when uninstalling the plugin
 */
class IX_Uninstaller {

    /**
     * Run all uninstall tasks
     */
    public static function run() {
        $delete_data = get_option('ix_woo_xero_uninstall_data', 'no');

        if ('yes' === $delete_data) {
            self::delete_options();
            self::delete_tables();
            self::delete_cron_jobs();
            self::delete_transients();
            self::delete_log_files();
        }

        // Always remove the version
        delete_option('ix_woo_xero_version');
    }

    /**
     * Delete plugin options
     */
    private static function delete_options() {
        global $wpdb;

        // Main settings
        delete_option('ix_woo_xero_settings');
        delete_option('ix_woo_xero_oauth_token');
        delete_option('ix_woo_xero_connection_status');
        delete_option('ix_woo_xero_last_sync');
        delete_option('ix_woo_xero_uninstall_data');

        // Delete all options with prefix
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                'ix_woo_xero_%'
            )
        );
    }

    /**
     * Delete plugin database tables
     */
    private static function delete_tables() {
        global $wpdb;

        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ix_xero_sync_log");
    }

    /**
     * Delete scheduled cron jobs
     */
    private static function delete_cron_jobs() {
        wp_clear_scheduled_hook('ix_woo_xero_daily_sync');
        wp_clear_scheduled_hook('ix_woo_xero_hourly_sync');
    }

    /**
     * Delete plugin transients
     */
    private static function delete_transients() {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_ix_xero_%',
                '_transient_timeout_ix_xero_%'
            )
        );
    }

    /**
     * Delete log files
     */
    private static function delete_log_files() {
        $upload_dir = wp_upload_dir();
        $log_dir = trailingslashit($upload_dir['basedir']) . 'ix-woo-xero-logs/';

        if (file_exists($log_dir)) {
            $files = glob($log_dir . '*.log');

            if (is_array($files)) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }

            @rmdir($log_dir);
        }
    }

    /**
     * Delete product meta
     */
    private static function delete_product_meta() {
        global $wpdb;

        $meta_keys = array(
            '_xero_account_code',
            '_xero_item_id',
            '_xero_last_sync',
            '_xero_sync_status'
        );

        foreach ($meta_keys as $meta_key) {
            $wpdb->delete(
                $wpdb->postmeta,
                array('meta_key' => $meta_key),
                array('%s')
            );
        }
    }

    /**
     * Delete order meta
     */
    private static function delete_order_meta() {
        global $wpdb;

        $meta_keys = array(
            '_xero_invoice_id',
            '_xero_invoice_number',
            '_xero_payment_id',
            '_xero_last_sync',
            '_xero_sync_status',
            '_xero_sync_error'
        );

        foreach ($meta_keys as $meta_key) {
            $wpdb->delete(
                $wpdb->postmeta,
                array('meta_key' => $meta_key),
                array('%s')
            );
        }
    }
}

// Run the uninstaller
IX_Uninstaller::run();