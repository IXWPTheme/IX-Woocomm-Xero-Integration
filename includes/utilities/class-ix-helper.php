<?php
/**
 * IX Helper Class
 * 
 * Provides utility functions for the IX Woo Xero Integration plugin.
 * 
 * @package IX_Woo_Xero_Integration
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class IX_Helper {

    /**
     * Check if the plugin is in debug mode
     * 
     * @return bool
     */
    public static function is_debug_mode() {
        return defined('IX_XERO_DEBUG') && IX_XERO_DEBUG;
    }

    /**
     * Get plugin settings
     * 
     * @param string $key Optional setting key
     * @return mixed
     */
    public static function get_settings($key = '') {
        $settings = get_option('ix_woo_xero_settings', array());
        
        if ($key) {
            return isset($settings[$key]) ? $settings[$key] : null;
        }
        
        return $settings;
    }

    /**
     * Format price for Xero API
     * 
     * @param float $price
     * @return string
     */
    public static function format_price($price) {
        return number_format((float)$price, 2, '.', '');
    }

    /**
     * Convert WooCommerce date to Xero format
     * 
     * @param string $date
     * @return string
     */
    public static function convert_date_to_xero_format($date) {
        return date('Y-m-d', strtotime($date));
    }

    /**
     * Get Xero account code mapping for WooCommerce item
     * 
     * @param WC_Product|WC_Order_Item $item
     * @return string
     */
    public static function get_xero_account_code($item) {
        $default_account = self::get_settings('default_account_code');
        $account_code = $default_account;
        
        // Check for product-specific account code
        if ($item instanceof WC_Product) {
            $product_account = get_post_meta($item->get_id(), '_xero_account_code', true);
            if (!empty($product_account)) {
                $account_code = $product_account;
            }
        }
        
        // Check for category mapping
        if ($item instanceof WC_Product) {
            $categories = $item->get_category_ids();
            $category_mapping = self::get_settings('category_account_mapping');
            
            if (!empty($category_mapping) && is_array($category_mapping)) {
                foreach ($categories as $category_id) {
                    if (isset($category_mapping[$category_id])) {
                        $account_code = $category_mapping[$category_id];
                        break;
                    }
                }
            }
        }
        
        return apply_filters('ix_woo_xero_account_code', $account_code, $item);
    }

    /**
     * Get Xero tax type for WooCommerce tax
     * 
     * @param string $tax_class
     * @return string
     */
    public static function get_xero_tax_type($tax_class) {
        $tax_mapping = self::get_settings('tax_mapping');
        $default_tax = self::get_settings('default_tax_type');
        
        if (!empty($tax_mapping) && isset($tax_mapping[$tax_class])) {
            return $tax_mapping[$tax_class];
        }
        
        return $default_tax ?: 'OUTPUT';
    }

    /**
     * Get Xero tracking category for order/item
     * 
     * @param WC_Order|WC_Order_Item $object
     * @return array
     */
    public static function get_tracking_categories($object) {
        $tracking_categories = array();
        $settings = self::get_settings();
        
        if (isset($settings['enable_tracking']) && $settings['enable_tracking']) {
            // Implement tracking category logic here
            // This would map WooCommerce data to Xero tracking categories
        }
        
        return apply_filters('ix_woo_xero_tracking_categories', $tracking_categories, $object);
    }

    /**
     * Sanitize Xero contact name
     * 
     * @param string $name
     * @return string
     */
    public static function sanitize_contact_name($name) {
        $name = preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $name);
        return substr($name, 0, 50);
    }

    /**
     * Get order note for Xero
     * 
     * @param WC_Order $order
     * @return string
     */
    public static function get_order_note($order) {
        $note = sprintf(
            __('WooCommerce Order #%s', 'ix-woo-xero'),
            $order->get_order_number()
        );
        
        $customer_note = $order->get_customer_note();
        if (!empty($customer_note)) {
            $note .= ' - ' . $customer_note;
        }
        
        return substr($note, 0, 200);
    }

    /**
     * Check if order should be synced to Xero
     * 
     * @param WC_Order $order
     * @return bool
     */
    public static function should_sync_order($order) {
        $statuses = self::get_settings('order_statuses');
        $order_status = $order->get_status();
        
        if (empty($statuses)) {
            $statuses = array('completed', 'processing');
        }
        
        $should_sync = in_array($order_status, $statuses);
        
        return apply_filters('ix_woo_xero_should_sync_order', $should_sync, $order);
    }

    /**
     * Get Xero currency code
     * 
     * @param string $currency
     * @return string
     */
    public static function get_xero_currency_code($currency) {
        $valid_currencies = array(
            'AUD', 'BRL', 'CAD', 'CHF', 'CNY', 'CZK', 'DKK', 'EUR', 'GBP',
            'HKD', 'HUF', 'IDR', 'ILS', 'INR', 'JPY', 'KRW', 'MXN', 'MYR',
            'NOK', 'NZD', 'PHP', 'PLN', 'RUB', 'SEK', 'SGD', 'THB', 'TRY',
            'USD', 'ZAR'
        );
        
        return in_array($currency, $valid_currencies) ? $currency : '';
    }

    /**
     * Log debug message
     * 
     * @param string $message
     * @param string $context
     */
    public static function log($message, $context = 'general') {
        if (self::is_debug_mode()) {
            if (function_exists('wc_get_logger')) {
                $logger = wc_get_logger();
                $logger->debug($message, array('source' => 'ix-woo-xero-' . $context));
            } else {
                error_log('IX Xero: ' . $message);
            }
        }
    }

    /**
     * Get WooCommerce to Xero item mapping
     * 
     * @return array
     */
    public static function get_item_mapping() {
        return array(
            'line_item' => 'Item',
            'shipping'  => 'Service',
            'fee'       => 'Service',
            'coupon'     => 'Discount'
        );
    }

    /**
     * Check if premium features are available
     * 
     * @return bool
     */
    public static function is_premium() {
        return defined('IX_WOO_XERO_PREMIUM') && IX_WOO_XERO_PREMIUM;
    }
}