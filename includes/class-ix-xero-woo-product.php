<?php
if (!defined('ABSPATH')) {
    exit;
}

class IX_Xero_Woocommerce_Product {
    private $xero_api;
    private $products_being_synced = array();

    public function __construct($xero_api) {
        $this->xero_api = $xero_api;
        $this->init_hooks();
    }

    private function init_hooks() {
	// Product sync hooks
	add_action('save_post_product', array($this, 'sync_product_to_xero'), 20, 3);
	add_action('woocommerce_update_product', array($this, 'sync_product_to_xero_on_update'), 10, 1);
	add_action('admin_post_ix_xero_sync_all_products', array($this, 'sync_all_products_to_xero'));
    }

    public function sync_product_to_xero($post_id, $post, $update) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if ($post->post_type != 'product') return;
        
        if (in_array($post_id, $this->products_being_synced)) return;
        
        $this->products_being_synced[] = $post_id;
        
        $product = wc_get_product($post_id);
        if ($product) {
            $this->sync_single_product_to_xero($product);
        }
        
        $this->products_being_synced = array_diff($this->products_being_synced, array($post_id));
    }

    public function sync_product_to_xero_on_update($product_id) {
        if (in_array($product_id, $this->products_being_synced)) return;
        
        $this->products_being_synced[] = $product_id;
        
        $product = wc_get_product($product_id);
        if ($product) {
            $this->sync_single_product_to_xero($product);
        }
        
        $this->products_being_synced = array_diff($this->products_being_synced, array($product_id));
    }

//    public function sync_single_product_to_xero($product) {
//		if (!$product || !$this->xero_api->is_connected()) return;
//
//		try {
//			$xero_item_code = $this->get_xero_item_code($product);
//			$existing_item = $this->xero_api->get_xero_item_by_code($xero_item_code);
//			$existing_item_name = $this->xero_api->get_xero_item_by_name($xero_item_code);
//			$product_data = $this->prepare_xero_product_data($product);			
//			
//
//			// If product exists in Xero, update it
//			//if ($existing_item) {
//			if ($product->get_name() == $existing_item_name['Name']) {
//				error_log('Syncing Existing item product ID and Name: ' . $xero_item_code.'#'.$existing_item_name['Name'].'#'.$product->get_name());
//				$response = $this->xero_api->update_xero_item($existing_item['ItemID'], $product_data);
//				$product->update_meta_data('_xero_item_id', $existing_item['ItemID']);
//				$product->update_meta_data('_xero_item_code', $xero_item_code);
//				$action = 'updated';
//			} 
//			// If product doesn't exist in Xero, create it
//			else {
//				$response = $this->xero_api->create_xero_item($product_data);
//				if (isset($response['Items'][0]['ItemID'])) {
//					$product->update_meta_data('_xero_item_id', $response['Items'][0]['ItemID']);
//					$product->update_meta_data('_xero_item_code', $xero_item_code);
//				}
//				$action = 'created';
//			}
//
//			$product->update_meta_data('_xero_last_sync', current_time('mysql'));
//			$product->save();
//
//			do_action('ix_xero_product_synced', $product->get_id(), $response, $action);
//
//		} catch (Exception $e) {
//			error_log('Xero Product Sync Error: ' . $e->getMessage());
//			do_action('ix_xero_product_sync_failed', $product->get_id(), $e->getMessage());
//		}
//}
	
	public function sync_single_product_to_xero($product) {
		if (!$product || !$this->xero_api->is_connected()) return;

		try {
			$xero_item_code = $this->get_xero_item_code($product);
			$existing_item = $this->xero_api->get_xero_item_by_code($xero_item_code);
			$existing_item_name = $this->xero_api->get_xero_item_by_name($xero_item_code);
			$product_data = $this->prepare_xero_product_data($product);			

			// Check if item exists in Xero and names match
			if ($existing_item_name && isset($existing_item_name['Name']) && 
				$product->get_name() == $existing_item_name['Name']) {
				
				error_log('Syncing Existing item product ID and Name: ' . $xero_item_code.'# '.$existing_item_name['Name'].' #'.$product->get_name());
				
				$response = $this->xero_api->update_xero_item($existing_item['ItemID'], $product_data);
				$product->update_meta_data('_xero_item_id', $existing_item['ItemID']);
				$product->update_meta_data('_xero_item_code', $xero_item_code);
				$action = 'updated';
			} 
			// If product doesn't exist in Xero, create it
			else {
				$response = $this->xero_api->create_xero_item($product_data);
				if (isset($response['Items'][0]['ItemID'])) {
					$product->update_meta_data('_xero_item_id', $response['Items'][0]['ItemID']);
					$product->update_meta_data('_xero_item_code', $xero_item_code);
				}
				error_log('Syncing Create Item product ID Xero ID and Name: ' . $product->get_id() .' - '. $response['Items'][0]['ItemID'].'# '.$product->get_name());
				$action = 'created';
			}

			$product->update_meta_data('_xero_last_sync', current_time('mysql'));
			$product->save();

			do_action('ix_xero_product_synced', $product->get_id(), $response, $action);

		} catch (Exception $e) {
			error_log('Xero Product Sync Error: ' . $e->getMessage());
			do_action('ix_xero_product_sync_failed', $product->get_id(), $e->getMessage());
		}
}

    private function get_xero_item_code($product) {
        $code = $product->get_sku() ?: 'wc-' . $product->get_id();
        return apply_filters('ix_xero_product_item_code', $code, $product);
    }

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

    private function get_xero_tax_type_for_product($product) {
        $tax_status = $product->get_tax_status();
        $tax_class = $product->get_tax_class();
        
        $tax_type = 'NONE';
        
        if ($tax_status === 'taxable') {
            $tax_type = 'OUTPUT';
            
            if ($tax_class === 'reduced-rate') {
                $tax_type = 'OUTPUT2';
            } elseif ($tax_class === 'zero-rate') {
                $tax_type = 'EXEMPTOUTPUT';
            }
        }
        
        return apply_filters('ix_xero_product_tax_type', $tax_type, $product);
    }

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
            admin_url('admin.php?page=ix-xero-settings&tab=products')
        ));
        exit;
    }
}