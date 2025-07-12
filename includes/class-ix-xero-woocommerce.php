<?php
class IX_Xero_Woocommerce {
    private $xero_api;
    private $admin;
	private $products_being_synced = array(); // Initialize the property

    public function __construct() {
        $this->load_dependencies();
        $this->set_locale();
	// Add Xero status column
    add_filter('manage_edit-shop_order_columns', array($this, 'add_xero_status_column'), 20);
    add_action('manage_shop_order_posts_custom_column', array($this, 'display_xero_status_column'), 10, 1);
    add_filter('manage_edit-shop_order_sortable_columns', array($this, 'make_xero_status_column_sortable'));
    add_action('pre_get_posts', array($this, 'handle_xero_status_sorting'));
    }

    private function load_dependencies() {
        require_once IX_WOOCOMM_XERO_PLUGIN_DIR . 'includes/class-ix-xero-api.php';
        require_once IX_WOOCOMM_XERO_PLUGIN_DIR . 'includes/admin/class-ix-xero-admin.php';
        
        $this->xero_api = new IX_Xero_API();
        $this->admin = new IX_Xero_Admin($this->xero_api);
    }

    private function set_locale() {
        load_plugin_textdomain(
            'ix-woocomm-xero',
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }

    public function run() {
        $this->admin->init();
        $this->define_hooks();
    }

    private function define_hooks() {
        // Create Xero invoice when WooCommerce order is created
        add_action('woocommerce_order_status_completed', array($this, 'create_xero_invoice'), 10, 1);
        add_action('woocommerce_thankyou', array($this, 'maybe_create_xero_invoice'), 10, 1);
		// Product sync hooks
        add_action('save_post_product', array($this, 'sync_product_to_xero'), 10, 3);
        add_action('woocommerce_update_product', array($this, 'sync_product_to_xero_on_update'), 10, 1);
        add_action('admin_post_ix_xero_sync_all_products', array($this, 'sync_all_products_to_xero'));
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
        
        // Check if invoice already exists in Xero
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
        
        // Add shipping as a line item
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
        
//        $invoice_data = array(
//            'Type' => 'ACCREC',
//            'Contact' => $this->prepare_contact_data($order),
//            'Date' => date('Y-m-d'),
//            'DueDate' => date('Y-m-d', strtotime('+30 days')),
//            'LineItems' => $line_items,
//            'Reference' => $order->get_order_number(),
//            'Status' => 'AUTHORISED'
//        );
		
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
                // This is a simplified mapping - you'll need to adjust based on your Xero tax rates
                $tax_type = 'OUTPUT';
                
                // For countries with GST
                if (in_array($order->get_billing_country(), array('AU', 'NZ'))) {
                    $tax_type = 'GST';
                }
                
                // For EU VAT
                if (in_array($order->get_billing_country(), array('AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GB', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK'))) {
                    $tax_type = 'OUTPUT2';
                }
            }
        }
        
        return apply_filters('ix_xero_tax_type', $tax_type, $order, $item, $type);
    }
	
	/**
 * Add Xero status column to WooCommerce orders list
 */
	public function add_xero_status_column($columns) {
		$new_columns = array();

		foreach ($columns as $key => $column) {
			$new_columns[$key] = $column;
			// Insert after 'order_status' column
			if ($key === 'order_status') {
				$new_columns['xero_status'] = __('Xero Status', 'ix-woocomm-xero');
			}
		}

		return $new_columns;
	}

/**
 * Display Xero sync status in the column
 */
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

	/**
	 * Make the Xero status column sortable
	 */
	public function make_xero_status_column_sortable($columns) {
		$columns['xero_status'] = 'xero_status';
		return $columns;
	}

	/**
	 * Add custom query for sorting by Xero status
	 */
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
	
	/**
     * Handle product sync from save_post hook
     */
    public function sync_product_to_xero($post_id, $post, $update) {
        // Skip autosaves and non-products
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if ($post->post_type != 'product') return;
        
        // Skip if we're already processing this product
        if (in_array($post_id, $this->products_being_synced)) return;
        
        $this->products_being_synced[] = $post_id;
        
        $product = wc_get_product($post_id);
        if ($product) {
            $this->sync_single_product_to_xero($product);
        }
        
        // Remove from processing list
        $this->products_being_synced = array_diff($this->products_being_synced, array($post_id));
    }

    /**
     * Handle product sync from WooCommerce update hook
     */
    public function sync_product_to_xero_on_update($product_id) {
        // Skip if we're already processing this product
        if (in_array($product_id, $this->products_being_synced)) return;
        
        $this->products_being_synced[] = $product_id;
        
        $product = wc_get_product($product_id);
        if ($product) {
            $this->sync_single_product_to_xero($product);
        }
        
        // Remove from processing list
        $this->products_being_synced = array_diff($this->products_being_synced, array($product_id));
    }

    /**
     * Core product sync method
     */
    public function sync_single_product_to_xero($product) {
        if (!$product || !$this->xero_api->is_connected()) return;
        
        try {
            $xero_item_code = $this->get_xero_item_code($product);
            $existing_item = $this->xero_api->get_xero_item_by_code($xero_item_code);
            
            $product_data = $this->prepare_xero_product_data($product);
            
            if ($existing_item) {
                // Update existing item
                $response = $this->xero_api->update_xero_item($existing_item['ItemID'], $product_data);
                $product->update_meta_data('_xero_item_id', $existing_item['ItemID']);
                $product->update_meta_data('_xero_item_code', $xero_item_code);
            } else {
                // Create new item
                $response = $this->xero_api->create_xero_item($product_data);
                if (isset($response['Items'][0]['ItemID'])) {
                    $product->update_meta_data('_xero_item_id', $response['Items'][0]['ItemID']);
                    $product->update_meta_data('_xero_item_code', $xero_item_code);
                }
            }
            
            $product->update_meta_data('_xero_last_sync', current_time('mysql'));
            $product->save();
            
            do_action('ix_xero_product_synced', $product->get_id(), $response);
            
        } catch (Exception $e) {
            error_log('Xero Product Sync Error: ' . $e->getMessage());
            do_action('ix_xero_product_sync_failed', $product->get_id(), $e->getMessage());
        }
    }

    /**
     * Generate Xero item code from product
     */
    private function get_xero_item_code($product) {
        $code = $product->get_sku() ?: 'wc-' . $product->get_id();
        return apply_filters('ix_xero_product_item_code', $code, $product);
    }

    /**
     * Prepare product data for Xero API
     */
    private function prepare_xero_product_data($product) {
        $xero_item_code = $this->get_xero_item_code($product);
        $sales_account = get_option('ix_xero_sales_account_code', '200');
        
        $product_data = array(
            'Code' => $xero_item_code,
            'Name' => $product->get_name(),
            'Description' => wp_strip_all_tags($product->get_description()),
            'IsTrackedAsInventory' => $product->managing_stock(),
            'PurchaseDescription' => wp_strip_all_tags($product->get_short_description()),
            'SalesDetails' => array(
                'UnitPrice' => $product->get_price(),
                'AccountCode' => $sales_account,
                'TaxType' => $this->get_xero_tax_type_for_product($product)
            ),
            'PurchaseDetails' => array(
                'UnitPrice' => $product->get_regular_price(),
                'AccountCode' => $sales_account,
                'TaxType' => $this->get_xero_tax_type_for_product($product)
            )
        );
        
        if ($product->managing_stock()) {
            $product_data['InventoryAssetAccountCode'] = get_option('ix_xero_inventory_account_code', '120');
            $product_data['QuantityOnHand'] = $product->get_stock_quantity();
        }
        
        return apply_filters('ix_xero_product_data', $product_data, $product);
    }

    /**
     * Get Xero tax type for a product
     */
    private function get_xero_tax_type_for_product($product) {
        $tax_status = $product->get_tax_status();
        $tax_class = $product->get_tax_class();
        
        // Default to no tax
        $tax_type = 'NONE';
        
        if ($tax_status === 'taxable') {
            // This is simplified - you'll need to map your tax classes to Xero tax types
            $tax_type = 'OUTPUT';
            
            // Example for specific tax classes
            if ($tax_class === 'reduced-rate') {
                $tax_type = 'OUTPUT2';
            } elseif ($tax_class === 'zero-rate') {
                $tax_type = 'EXEMPTOUTPUT';
            }
        }
        
        return apply_filters('ix_xero_product_tax_type', $tax_type, $product);
    }

    /**
     * Bulk sync all products
     */
    public function sync_all_products_to_xero() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'ix-woocomm-xero'));
        }
        
        check_admin_referer('ix_xero_sync_all_products');
        
        $args = array(
            'limit' => 10,
            'return' => 'ids',
            'status' => 'publish'
        );
        
        $product_ids = wc_get_products($args);
        $synced = 0;
        
        foreach ($product_ids as $product_id) {
            if (!in_array($product_id, $this->products_being_synced)) {
                $this->products_being_synced[] = $product_id;
                $product = wc_get_product($product_id);
                if ($product) {
                    $this->sync_single_product_to_xero($product);
                    $synced++;
                }
                $this->products_being_synced = array_diff($this->products_being_synced, array($product_id));
            }
        }
        
        wp_redirect(add_query_arg(
            array(
                'page' => 'ix-xero-settings',
                'synced' => $synced
            ),
            admin_url('admin.php')
        ));
        exit;
    }

    public static function activate() {
        // Add any activation code here
    }

    public static function deactivate() {
        // Add any deactivation code here
    }
}