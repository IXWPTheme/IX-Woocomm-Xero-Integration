<?php
if (!defined('ABSPATH')) {
    exit;
}

class IX_Xero_Woocommerce_Order {
    private $xero_api;

    public function __construct($xero_api) {
        $this->xero_api = $xero_api;
        $this->init_hooks();
    }

    private function init_hooks() {
        // Order hooks
        add_action('woocommerce_order_status_completed', array($this, 'create_xero_invoice'), 10, 1);
        add_action('woocommerce_thankyou', array($this, 'maybe_create_xero_invoice'), 10, 1);
        
        // Admin columns
        add_filter('manage_edit-shop_order_columns', array($this, 'add_xero_status_column'), 20);
        add_action('manage_shop_order_posts_custom_column', array($this, 'display_xero_status_column'), 10, 1);
        add_filter('manage_edit-shop_order_sortable_columns', array($this, 'make_xero_status_column_sortable'));
        add_action('pre_get_posts', array($this, 'handle_xero_status_sorting'));
    }

    public function maybe_create_xero_invoice($order_id) {
        $order = wc_get_order($order_id);
        $auto_create = get_option('ix_xero_auto_create_invoice', 'yes');
        
        if ('yes' === $auto_create && !$order->get_meta('_xero_invoice_id')) {
            $this->create_xero_invoice($order_id);
        }
    }

    public function create_xero_invoice($order_id) {
        $order = wc_get_order($order_id);
        
        if ($order->get_meta('_xero_invoice_id')) {
            return;
        }
        
        try {
            $invoice_data = $this->prepare_invoice_data($order);
            $response = $this->xero_api->create_invoice($invoice_data);
            
            if ($response && isset($response['Invoices'][0]['InvoiceID'])) {
                $order->update_meta_data('_xero_invoice_id', $response['Invoices'][0]['InvoiceID']);
                $order->update_meta_data('_xero_invoice_number', $response['Invoices'][0]['InvoiceNumber']);
                $order->save();
                
                do_action('ix_xero_invoice_created', $order_id, $response);
            }
        } catch (Exception $e) {
            error_log('Xero Invoice Creation Error: ' . $e->getMessage());
            $order->add_order_note(sprintf(__('Xero invoice creation failed: %s', 'ix-woocomm-xero'), $e->getMessage()));
        }
    }

    private function prepare_invoice_data($order) {
        $line_items = array();
        
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $line_items[] = array(
                'Description' => $item->get_name(),
                'Quantity' => $item->get_quantity(),
                'UnitAmount' => $order->get_item_total($item, false, false),
                'AccountCode' => get_option('ix_xero_sales_account_code', '200'),
                'TaxAmount' => $item->get_total_tax(),
                'TaxType' => $this->get_xero_tax_type($order, $item),
                'LineAmount' => $item->get_total()
            );
        }
        
        if ($order->get_shipping_total() > 0) {
            $line_items[] = array(
                'Description' => __('Shipping', 'ix-woocomm-xero'),
                'Quantity' => 1,
                'UnitAmount' => $order->get_shipping_total(),
                'AccountCode' => get_option('ix_xero_shipping_account_code', '201'),
                'TaxAmount' => $order->get_shipping_tax(),
                'TaxType' => $this->get_xero_tax_type($order, null, 'shipping'),
                'LineAmount' => $order->get_shipping_total()
            );
        }
        
        $invoice_prefix = get_option('ix_xero_invoice_prefix', 'PHSC-');
        $invoice_data = array(
            'Type' => 'ACCREC',
            'Contact' => $this->prepare_contact_data($order),
            'Date' => date('Y-m-d'),
            'DueDate' => date('Y-m-d', strtotime('+30 days')),
            'LineItems' => $line_items,
            'Reference' => $invoice_prefix . $order->get_order_number(),
            'Status' => 'AUTHORISED'
        );
        
        return apply_filters('ix_xero_invoice_data', $invoice_data, $order);
    }
    
    private function prepare_contact_data($order) {
        $contact = array(
            'Name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'FirstName' => $order->get_billing_first_name(),
            'LastName' => $order->get_billing_last_name(),
            'EmailAddress' => $order->get_billing_email(),
            'Addresses' => array(
                array(
                    'AddressType' => 'STREET',
                    'AddressLine1' => $order->get_billing_address_1(),
                    'AddressLine2' => $order->get_billing_address_2(),
                    'City' => $order->get_billing_city(),
                    'Region' => $order->get_billing_state(),
                    'PostalCode' => $order->get_billing_postcode(),
                    'Country' => $order->get_billing_country()
                )
            ),
            'Phones' => array(
                array(
                    'PhoneType' => 'DEFAULT',
                    'PhoneNumber' => $order->get_billing_phone()
                )
            )
        );
        
        return apply_filters('ix_xero_contact_data', $contact, $order);
    }
    
    private function get_xero_tax_type($order, $item = null, $type = 'line_item') {
        $tax_rates = WC_Tax::get_rates();
        $tax_type = 'NONE';
        
        if (!empty($tax_rates)) {
            $first_rate = reset($tax_rates);
            $rate = $first_rate['rate'];
            
            if ($rate > 0) {
                $tax_type = 'OUTPUT';
                
                if (in_array($order->get_billing_country(), array('AU', 'NZ'))) {
                    $tax_type = 'GST';
                }
                
                if (in_array($order->get_billing_country(), array('AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GB', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK'))) {
                    $tax_type = 'OUTPUT2';
                }
            }
        }
        
        return apply_filters('ix_xero_tax_type', $tax_type, $order, $item, $type);
    }

    public function add_xero_status_column($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;
            if ($key === 'order_status') {
                $new_columns['xero_status'] = __('Xero Status', 'ix-woocomm-xero');
            }
        }
        
        return $new_columns;
    }

    public function display_xero_status_column($column) {
        global $post;

        if ($column === 'xero_status') {
            $order = wc_get_order($post->ID);
            $invoice_id = $order->get_meta('_xero_invoice_id');
            $invoice_number = $order->get_meta('_xero_invoice_number');

            if ($invoice_id) {
                $status = '<mark class="order-status status-completed tips" data-tip="' 
                         . esc_attr__('Synced to Xero', 'ix-woocomm-xero') . '">'
                         . '<span>' . __('Synced', 'ix-woocomm-xero') . '</span>'
                         . '</mark>';

                if ($invoice_number) {
                    $status .= '<small class="meta">' 
                             . sprintf(__('Invoice #%s', 'ix-woocomm-xero'), $invoice_number)
                             . '</small>';
                }

                echo $status;
            } else {
                echo '<mark class="order-status status-failed tips" data-tip="' 
                   . esc_attr__('Not synced to Xero', 'ix-woocomm-xero') . '">'
                   . '<span>' . __('Not Synced', 'ix-woocomm-xero') . '</span>'
                   . '</mark>';
            }
        }
    }

    public function make_xero_status_column_sortable($columns) {
        $columns['xero_status'] = 'xero_status';
        return $columns;

    }

    public function handle_xero_status_sorting($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        if (isset($_GET['post_type']) && 'shop_order' === $_GET['post_type'] 
            && isset($_GET['orderby']) && 'xero_status' === $_GET['orderby']) {

            $query->set('meta_key', '_xero_invoice_id');
            $query->set('orderby', 'meta_value');
        }
    }
}