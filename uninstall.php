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

// Remove all plugin options
delete_option('ix_xero_client_id');
delete_option('ix_xero_client_secret');
delete_option('ix_xero_tenant_id');
delete_option('ix_xero_access_token');
delete_option('ix_xero_refresh_token');
delete_option('ix_xero_token_expires');
delete_option('ix_xero_auto_create_invoice');
delete_option('ix_xero_auto_sync_products');
delete_option('ix_xero_sales_account_code');
delete_option('ix_xero_shipping_account_code');
delete_option('ix_xero_inventory_account_code');
delete_option('ix_xero_invoice_prefix');

// Remove any transients we've set
delete_transient('ix_xero_connection_test');
delete_transient('ix_xero_last_sync_time');

// Clean up scheduled events
wp_clear_scheduled_hook('ix_xero_daily_sync');

// Remove meta data from orders
$wpdb->query(
    "DELETE FROM {$wpdb->postmeta} 
    WHERE meta_key IN (
        '_xero_invoice_id',
        '_xero_invoice_number',
        '_xero_sync_status',
        '_xero_last_sync'
    )"
);

// Remove meta data from products
$wpdb->query(
    "DELETE FROM {$wpdb->postmeta} 
    WHERE meta_key IN (
        '_xero_item_id',
        '_xero_item_code',
        '_xero_last_sync'
    )"
);

// Log the uninstallation (for debugging)
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('IX Woocomm Xero Integration plugin uninstalled and all data cleaned up');
}

// Optional: Drop custom database tables if created
// $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ix_xero_logs");