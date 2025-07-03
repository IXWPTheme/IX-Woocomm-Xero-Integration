<?php
class IX_Product_Sync {
    
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
        // Sync product on save
        add_action('save_post_product', array($this, 'sync_product_to_xero'), 10, 3);
        
        // Manual sync action
        add_action('ix_woo_xero_sync_products', array($this, 'sync_all_products'));
        
        // Add admin actions
        add_action('admin_init', array($this, 'add_bulk_actions'));
        add_action('admin_action_ix_sync_to_xero', array($this, 'handle_bulk_action'));
    }
    
    public function sync_product_to_xero($post_id, $post, $update) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        if ('product' !== $post->post_type) {
            return;
        }
        
        $product = wc_get_product($post_id);
        if (!$product) {
            return;
        }
        
        $xero_id = get_post_meta($post_id, '_xero_item_id', true);
        
        $data = $this->prepare_product_data($product);
        
        if ($xero_id) {
            // Update existing product in Xero
            $response = $this->xero_api->make_api_request(
                'https://api.xero.com/api.xro/2.0/Items/' . $xero_id,
                'POST',
                ['Items' => [$data]]
            );
        } else {
            // Create new product in Xero
            $response = $this->xero_api->make_api_request(
                'https://api.xero.com/api.xro/2.0/Items',
                'PUT',
                ['Items' => [$data]]
            );
            
            if ($response && isset($response['Items'][0]['ItemID'])) {
                update_post_meta($post_id, '_xero_item_id', $response['Items'][0]['ItemID']);
            }
        }
        
        if ($response === false) {
            IX_Logger::log('Failed to sync product ID: ' . $post_id);
        }
    }
    
    private function prepare_product_data($product) {
        $data = [
            'Code' => $product->get_sku() ?: 'WC_' . $product->get_id(),
            'Name' => $product->get_name(),
            'Description' => $product->get_description(),
            'IsTrackedAsInventory' => $product->managing_stock(),
            'PurchaseDetails' => [
                'UnitPrice' => $product->get_regular_price(),
                'AccountCode' => get_option('ix_woo_xero_purchase_account')
            ],
            'SalesDetails' => [
                'UnitPrice' => $product->get_price(),
                'AccountCode' => get_option('ix_woo_xero_sales_account')
            ]
        ];
        
        if ($product->managing_stock()) {
            $data['InventoryAssetAccountCode'] = get_option('ix_woo_xero_inventory_account');
        }
        
        return $data;
    }
    
    public function sync_all_products() {
        if (!$this->xero_api->is_connected()) {
            return false;
        }
        
        $args = [
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ];
        
        $products = get_posts($args);
        
        foreach ($products as $post) {
            $this->sync_product_to_xero($post->ID, $post, true);
        }
        
        return true;
    }
    
    public function add_bulk_actions() {
        if (!current_user_can('edit_products')) {
            return;
        }
        
        add_filter('bulk_actions-edit-product', array($this, 'register_bulk_actions'));
    }
    
    public function register_bulk_actions($bulk_actions) {
        $bulk_actions['ix_sync_to_xero'] = __('Sync to Xero', 'ix-woo-xero');
        return $bulk_actions;
    }
    
    public function handle_bulk_action($redirect_to, $doaction, $post_ids) {
        if ($doaction !== 'ix_sync_to_xero') {
            return $redirect_to;
        }
        
        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            $this->sync_product_to_xero($post_id, $post, true);
        }
        
        $redirect_to = add_query_arg('ix_synced_products', count($post_ids), $redirect_to);
        return $redirect_to;
    }
}