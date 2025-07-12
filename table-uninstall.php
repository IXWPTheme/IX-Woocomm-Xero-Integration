<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package IX_Woocomm_Xero_Integration
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Verify user capabilities
if (!current_user_can('delete_plugins')) {
    wp_die(__('You do not have sufficient permissions to delete plugins.', 'ix-woocomm-xero'));
}

global $wpdb;

// Remove all plugin options (matches your uninstall hook function)
$options = [
    'ix_xero_client_id',
    'ix_xero_client_secret',
    'ix_xero_tenant_id',
    'ix_xero_access_token',
    'ix_xero_refresh_token',
    'ix_xero_token_expires',
    'ix_xero_auto_create_invoice',
    'ix_xero_auto_sync_products',
    'ix_xero_sales_account_code',
    'ix_xero_shipping_account_code',
    'ix_xero_inventory_account_code',
    'ix_xero_invoice_prefix',
    'ix_xero_temp_data' // From your deactivation hook
];

foreach ($options as $option) {
    delete_option($option);
}

// Remove any transients
delete_transient('ix_xero_connection_test');
delete_transient('ix_xero_last_sync_time');

// Clean up scheduled events
wp_clear_scheduled_hook('ix_xero_daily_sync');
wp_clear_scheduled_hook('ix_xero_hourly_product_sync');

// Remove meta data from orders and products
$meta_keys = [
    '_xero_invoice_id',
    '_xero_invoice_number',
    '_xero_sync_status',
    '_xero_last_sync',
    '_xero_item_id',
    '_xero_item_code',
    '_xero_sync_error'
];

foreach ($meta_keys as $meta_key) {
    $wpdb->delete($wpdb->postmeta, ['meta_key' => $meta_key]);
}

// Drop custom tables with proper prefix handling
$custom_tables = [
    'ix_xero_logs',
    'ix_xero_invoices',
    'ix_xero_items'
];

foreach ($custom_tables as $table) {
    $table_name = $wpdb->prefix . $table;
    $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
}

// Multisite support - clean up network-wide if needed
if (is_multisite()) {
    $blog_ids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs}");
    
    foreach ($blog_ids as $blog_id) {
        switch_to_blog($blog_id);
        
        // Delete site-specific options
        foreach ($options as $option) {
            delete_option($option);
        }
        
        // Delete site-specific tables
        foreach ($custom_tables as $table) {
            $table_name = $wpdb->prefix . $table;
            $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
        }
        
        restore_current_blog();
    }
}

// Final cleanup of any remaining traces
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ix_xero%'");
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'ix_xero%'");

// Log the uninstallation
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('[IX Xero] Plugin uninstalled - all data cleaned up');
}