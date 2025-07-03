<?php
class IX_Ajax {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('wp_ajax_ix_woo_xero_sync_products', array($this, 'ajax_sync_products'));
        add_action('wp_ajax_ix_woo_xero_sync_orders', array($this, 'ajax_sync_orders'));
        add_action('wp_ajax_ix_woo_xero_sync_customers', array($this, 'ajax_sync_customers'));
        add_action('wp_ajax_ix_woo_xero_sync_subscriptions', array($this, 'ajax_sync_subscriptions'));
    }
    
    public function ajax_sync_products() {
        $this->verify_nonce();
        
        $product_sync = IX_Product_Sync::get_instance();
        $result = $product_sync->sync_all_products();
        
        if ($result) {
            wp_send_json_success(__('Products synced successfully', 'ix-woo-xero'));
        } else {
            wp_send_json_error(__('Product sync failed', 'ix-woo-xero'));
        }
    }
    
    public function ajax_sync_orders() {
        $this->verify_nonce();
        
        $invoice_sync = IX_Invoice_Sync::get_instance();
        $result = $invoice_sync->sync_all_pending_invoices();
        
        if ($result) {
            wp_send_json_success(__('Orders synced successfully', 'ix-woo-xero'));
        } else {
            wp_send_json_error(__('Order sync failed', 'ix-woo-xero'));
        }
    }
    
    public function ajax_sync_customers() {
        $this->verify_nonce();
        
        $customer_sync = IX_Customer_Sync::get_instance();
        
        // Get all customer IDs
        $customer_ids = get_users(array(
            'role' => 'customer',
            'fields' => 'ID'
        ));
        
        foreach ($customer_ids as $customer_id) {
            $customer_sync->sync_customer_to_xero($customer_id);
        }
        
        wp_send_json_success(__('Customers synced successfully', 'ix-woo-xero'));
    }
    
    public function ajax_sync_subscriptions() {
        $this->verify_nonce();
        
        if (!class_exists('WC_Subscriptions')) {
            wp_send_json_error(__('WooCommerce Subscriptions is not active', 'ix-woo-xero'));
        }
        
        $subscription_sync = IX_Subscription_Sync::get_instance();
        
        $args = array(
            'status' => 'any',
            'limit' => -1,
            'return' => 'ids'
        );
        
        $subscription_ids = wcs_get_subscriptions($args);
        
        foreach ($subscription_ids as $subscription_id) {
            $subscription = wcs_get_subscription($subscription_id);
            $subscription_sync->sync_subscription_to_xero($subscription, $subscription->get_status(), '');
        }
        
        wp_send_json_success(__('Subscriptions synced successfully', 'ix-woo-xero'));
    }
    
    private function verify_nonce() {
        if (!isset($_REQUEST['nonce']) || !wp_verify_nonce($_REQUEST['nonce'], 'ix_woo_xero_nonce')) {
            wp_send_json_error(__('Invalid nonce', 'ix-woo-xero'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'ix-woo-xero'));
        }
    }
}

IX_Ajax::get_instance();