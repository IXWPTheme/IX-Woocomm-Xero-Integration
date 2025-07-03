<?php
class IX_Invoice_Sync {
    
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
        // Sync order on status change
        add_action('woocommerce_order_status_changed', array($this, 'sync_order_to_xero'), 10, 4);
        
        // Manual sync action
        add_action('ix_woo_xero_sync_invoices', array($this, 'sync_all_pending_invoices'));
    }
    
    public function sync_order_to_xero($order_id, $from_status, $to_status, $order) {
        // Only sync for specific statuses
        $sync_statuses = apply_filters('ix_woo_xero_invoice_sync_statuses', ['completed', 'processing']);
        
        if (!in_array($to_status, $sync_statuses)) {
            return;
        }
        
        // Check if already synced
        $xero_invoice_id = $order->get_meta('_xero_invoice_id');
        if ($xero_invoice_id) {
            return;
        }
        
        $data = $this->prepare_invoice_data($order);
        
        $response = $this->xero_api->make_api_request(
            'https://api.xero.com/api.xro/2.0/Invoices',
            'PUT',
            ['Invoices' => [$data]]
        );
        
        if ($response && isset($response['Invoices'][0]['InvoiceID'])) {
            $order->update_meta_data('_xero_invoice_id', $response['Invoices'][0]['InvoiceID']);
            $order->save();
        } else {
            IX_Logger::log('Failed to sync order ID: ' . $order_id);
        }
    }
    
    private function prepare_invoice_data($order) {
        $line_items = [];
        
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $xero_item_id = $product ? get_post_meta($product->get_id(), '_xero_item_id', true) : null;
            
            $line_items[] = [
                'Description' => $item->get_name(),
                'Quantity' => $item->get_quantity(),
                'UnitAmount' => $order->get_item_subtotal($item, false, false),
                'AccountCode' => get_option('ix_woo_xero_sales_account'),
                'ItemCode' => $xero_item_id ? null : $product->get_sku(),
                'Item' => $xero_item_id ? ['ItemID' => $xero_item_id] : null
            ];
        }
        
        // Add shipping as a line item
        if ($order->get_shipping_total() > 0) {
            $line_items[] = [
                'Description' => __('Shipping', 'ix-woo-xero'),
                'Quantity' => 1,
                'UnitAmount' => $order->get_shipping_total(),
                'AccountCode' => get_option('ix_woo_xero_shipping_account')
            ];
        }
        
        // Add fees as line items
        foreach ($order->get_fees() as $fee) {
            $line_items[] = [
                'Description' => $fee->get_name(),
                'Quantity' => 1,
                'UnitAmount' => $fee->get_total(),
                'AccountCode' => get_option('ix_woo_xero_fees_account')
            ];
        }
        
        $customer_id = $order->get_customer_id();
        $xero_contact_id = $customer_id ? get_user_meta($customer_id, '_xero_contact_id', true) : null;
        
        $data = [
            'Type' => 'ACCREC',
            'Contact' => $xero_contact_id ? ['ContactID' => $xero_contact_id] : $this->prepare_contact_data($order),
            'Date' => $order->get_date_created()->format('Y-m-d'),
            'DueDate' => $order->get_date_created()->format('Y-m-d'),
            'LineAmountTypes' => 'Inclusive',
            'LineItems' => $line_items,
            'Reference' => $order->get_order_number(),
            'Status' => 'AUTHORISED'
        ];
        
        return $data;
    }
    
    private function prepare_contact_data($order) {
        return [
            'Name' => $order->get_formatted_billing_full_name(),
            'FirstName' => $order->get_billing_first_name(),
            'LastName' => $order->get_billing_last_name(),
            'EmailAddress' => $order->get_billing_email(),
            'Addresses' => [
                [
                    'AddressType' => 'STREET',
                    'AddressLine1' => $order->get_billing_address_1(),
                    'AddressLine2' => $order->get_billing_address_2(),
                    'City' => $order->get_billing_city(),
                    'Region' => $order->get_billing_state(),
                    'PostalCode' => $order->get_billing_postcode(),
                    'Country' => $order->get_billing_country()
                ]
            ],
            'Phones' => [
                [
                    'PhoneType' => 'DEFAULT',
                    'PhoneNumber' => $order->get_billing_phone()
                ]
            ]
        ];
    }
    
    public function sync_all_pending_invoices() {
        if (!$this->xero_api->is_connected()) {
            return false;
        }
        
        $args = [
            'status' => ['processing', 'completed'],
            'limit' => -1,
            'meta_query' => [
                [
                    'key' => '_xero_invoice_id',
                    'compare' => 'NOT EXISTS'
                ]
            ]
        ];
        
        $orders = wc_get_orders($args);
        
        foreach ($orders as $order) {
            $this->sync_order_to_xero($order->get_id(), '', $order->get_status(), $order);
        }
        
        return true;
    }
}