<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once 'class-ix-xero-api.php';

class IX_Xero_API_Items extends IX_Xero_API {
	
    public function create_xero_item($product_data) {
        return $this->make_api_request('Items', 'POST', array('Items' => array($product_data)));
    }

    public function update_xero_item($item_id, $product_data) {
        return $this->make_api_request('Items/' . $item_id, 'POST', array('Items' => array($product_data)));
    }

   public function get_xero_item_by_code($code) {
		$result = $this->make_api_request('Items?code=' . urlencode($code));		
		return isset($result['Items']) && !empty($result['Items']) ? $result['Items'][0] : null;
   }

	public function get_xero_item_by_name($code) {
		$result = $this->make_api_request('Items?code=' . urlencode($code));
		
		if (isset($result['Items']) && !empty($result['Items'])) {
			error_log('Syncing Items?code=: ' . urlencode($code).'-'.$result['Items'][0]['Name']);
			return $result['Items'][0];
		}
		
		return null;
	}

	public function get_xero_item_by_id($item_id) {
		$result = $this->make_api_request('Items/' . $item_id);
		return isset($result['Items']) && !empty($result['Items']) ? $result['Items'][0] : null;
	}
	
}