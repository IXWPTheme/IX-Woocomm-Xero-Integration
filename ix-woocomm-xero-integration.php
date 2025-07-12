<?php
/**
 * Plugin Name: IX Woocomm Xero Integration
 * Plugin URI: https://yourwebsite.com/ix-woocomm-xero-integration
 * Description: Integrates WooCommerce with Xero accounting software to automatically create invoices and sync products.
 * Version: 1.0.5
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: ix-woocomm-xero
 * Domain Path: /languages
 * WC requires at least: 3.0.0
 * WC tested up to: 7.0.0
 */

defined('ABSPATH') || exit;

// Define plugin constants
define('IX_WOOCOMM_XERO_VERSION', '1.0.4');
define('IX_WOOCOMM_XERO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IX_WOOCOMM_XERO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('IX_WOOCOMM_XERO_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', 'ix_woocomm_xero_woocommerce_missing_notice');
    return;
}

function ix_woocomm_xero_woocommerce_missing_notice()
{
    echo '<div class="error"><p>';
    printf(
        esc_html__('IX Woocomm Xero Integration requires WooCommerce to be installed and active. You can download %s here.', 'ix-woocomm-xero'),
        '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>'
    );
    echo '</p></div>';
}

// Load required files
require_once IX_WOOCOMM_XERO_PLUGIN_DIR . 'includes/api/class-ix-xero-api.php';
require_once IX_WOOCOMM_XERO_PLUGIN_DIR . 'includes/api/class-ix-xero-api-invoices.php';
require_once IX_WOOCOMM_XERO_PLUGIN_DIR . 'includes/api/class-ix-xero-api-items.php';
require_once IX_WOOCOMM_XERO_PLUGIN_DIR . 'includes/api/class-ix-xero-api-customers.php';
require_once IX_WOOCOMM_XERO_PLUGIN_DIR . 'includes/class-ix-xero-woo-order.php';
require_once IX_WOOCOMM_XERO_PLUGIN_DIR . 'includes/class-ix-xero-woo-product.php';
require_once IX_WOOCOMM_XERO_PLUGIN_DIR . 'includes/class-ix-xero-woo-users.php';
require_once IX_WOOCOMM_XERO_PLUGIN_DIR . 'includes/admin/class-ix-xero-admin.php';

// Initialize the plugin
function ix_woocomm_xero_init()
{
    // Initialize Xero API services
    $xero_api 		= new IX_Xero_API();
    $xero_invoices 	= new IX_Xero_API_Invoices();
    $xero_items 	= new IX_Xero_API_Items();
	$xero_customers = new IX_Xero_API_Customers();

    // Initialize components with their required API services
    $xero_admin 	= new IX_Xero_Admin($xero_api);
    $xero_order 	= new IX_Xero_Woocommerce_Order($xero_invoices);
    $xero_product 	= new IX_Xero_Woocommerce_Product($xero_items);
	$xero_users 	= new IX_Xero_Woocommerce_Users($xero_customers);

    // Initialize admin
    $xero_admin->init();

    // Add action for single product sync
    add_action( 'admin_post_ix_xero_sync_single_product' , array( $xero_product, 'sync_single_product_admin'));

    // Add action for single order sync (if needed)
    add_action( 'admin_post_ix_xero_sync_single_order' , array( $xero_order, 'sync_single_order_admin'));
	
	// Add action for customer sync
    add_action( 'admin_post_ix_xero_sync_customers' , array( $xero_users, 'sync_customers_admin'));
}
add_action('plugins_loaded', 'ix_woocomm_xero_init');

// Activation and deactivation hooks
register_activation_hook(__FILE__, function () {
    // Add default options if needed
    add_option('ix_xero_auto_create_invoice', 'yes');
    add_option('ix_xero_auto_sync_products', 'yes');
});

register_deactivation_hook(__FILE__, function () {
    // Clean up temporary options if needed
    delete_option('ix_xero_temp_data');
});

// Register uninstall hook
register_uninstall_hook(__FILE__, 'ix_woocomm_xero_uninstall');

function ix_woocomm_xero_uninstall()
{
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
}