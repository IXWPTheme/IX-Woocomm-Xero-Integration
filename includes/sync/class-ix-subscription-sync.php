<?php
class IX_Subscription_Sync {
    
    private static $instance = null;
    private $xero_api;
    
    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->xero_api = IX_Xero_API::get_instance();
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Check if WooCommerce Subscriptions is active
        if (!class_exists('WC_Subscriptions')) {
            return;
        }
        
        // Sync subscription on status change
        add_action('woocommerce_subscription_status_updated', array($this, 'sync_subscription_to_xero'), 10, 3);
        
        // Sync subscription payment
        add_action('woocommerce_subscription_payment_complete', array($this, 'sync_subscription_payment'), 10, 1);
    }
    
    public function sync_subscription_to_xero($subscription, $new_status, $old_status) {
        $sync_statuses = apply_filters('ix_woo_xero_subscription_sync_statuses', ['active']);
        
        if (!in_array($new_status, $sync_statuses)) {
            return;
        }
        
        $xero_subscription_id = $subscription->get_meta('_xero_subscription_id');
        if ($xero_subscription_id) {
            return;
        }
        
        $customer_id = $subscription->get_customer_id();
        $xero_contact_id = $customer_id ? get_user_meta($customer_id, '_xero_contact_id', true) : null;
        
        if (!$xero_contact_id) {
            // Try to sync customer first
            $this->sync_customer($subscription);
            $xero_contact_id = $customer_id ? get_user_meta($customer_id, '_xero_contact_id', true) : $subscription->get_meta('_xero_contact_id');
            
            if (!$xero_contact_id) {
                IX_Logger::log('Failed to sync subscription - no Xero contact ID for subscription ID: ' . $subscription->get_id());
                return;
            }
        }
        
        $data = $this->prepare_subscription_data($subscription, $xero_contact_id);
        
        $response = $this->xero_api->make_api_request(
            'https://api.xero.com/api.xro/2.0/Subscriptions',
            'PUT',
            ['Subscriptions' => [$data]]
        );
        
        if ($response && isset($response['Subscriptions'][0]['SubscriptionID'])) {
            $subscription->update_meta_data('_xero_subscription_id', $response['Subscriptions'][0]['SubscriptionID']);
            $subscription->save();
        } else {
            IX_Logger::log('Failed to sync subscription ID: ' . $subscription->get_id());
        }
    }
    
    public function sync_subscription_payment($subscription) {
        $xero_subscription_id = $subscription->get_meta('_xero_subscription_id');
        if (!$xero_subscription_id) {
            return;
        }
        
        $latest_order = $subscription->get_last_order();
        if (!$latest_order) {
            return;
        }
        
        $data = $this->prepare_subscription_payment_data($subscription, $latest_order);
        
        $response = $this->xero_api->make_api_request(
            'https://api.xero.com/api.xro/2.0/SubscriptionPayments',
            'PUT',
            ['SubscriptionPayments' => [$data]]
        );
        
        if ($response === false) {
            IX_Logger::log('Failed to sync subscription payment for subscription ID: ' . $subscription->get_id());
        }
    }
    
    private function sync_customer($subscription) {
        $customer_id = $subscription->get_customer_id();
        
        if ($customer_id) {
            $this->sync_customer_to_xero($customer_id);
        } else {
            // Handle guest customer
            $customer_sync = IX_Customer_Sync::get_instance();
            $customer_sync->sync_guest_customer_to_xero($subscription);
        }
    }
    
    private function prepare_subscription_data($subscription, $xero_contact_id) {
        $product = $subscription->get_product();
        $xero_item_id = $product ? get_post_meta($product->get_id(), '_xero_item_id', true) : null;
        
        $data = [
            'ContactID' => $xero_contact_id,
            'StartDate' => $subscription->get_date('start'),
            'PlanName' => $product ? $product->get_name() : __('Subscription', 'ix-woo-xero'),
            'Item' => $xero_item_id ? ['ItemID' => $xero_item_id] : null,
            'LineAmount' => $subscription->get_total(),
            'BillingFrequency' => $this->get_xero_frequency($subscription->get_billing_period(), $subscription->get_billing_interval()),
            'Status' => 'ACTIVE'
        ];
        
        return $data;
    }
    
    private function prepare_subscription_payment_data($subscription, $order) {
        return [
            'SubscriptionID' => $subscription->get_meta('_xero_subscription_id'),
            'InvoiceID' => $order->get_meta('_xero_invoice_id'),
            'Date' => $order->get_date_paid() ? $order->get_date_paid()->format('Y-m-d') : date('Y-m-d'),
            'Amount' => $order->get_total(),
            'Reference' => $order->get_order_number()
        ];
    }
    
    private function get_xero_frequency($period, $interval) {
        $interval = max(1, $interval);
        
        switch ($period) {
            case 'day':
                return $interval === 1 ? 'DAILY' : $interval . 'DAYS';
            case 'week':
                return $interval === 1 ? 'WEEKLY' : $interval . 'WEEKS';
            case 'month':
                return $interval === 1 ? 'MONTHLY' : $interval . 'MONTHS';
            case 'year':
                return $interval === 1 ? 'YEARLY' : $interval . 'YEARS';
            default:
                return 'MONTHLY';
        }
    }
}