<?php
/**
 * IX Woo Xero Integration - Tax Class (Premium)
 * 
 * Handles all tax-related functionality including:
 * - Tax rate synchronization
 * - Tax rate mapping
 * - Tax calculation adjustments
 */

namespace IX_Woo_Xero\Premium;

use IX_Woo_Xero\API\Xero_API;
use IX_Woo_Xero\Utilities\Logger;

if (!defined('ABSPATH')) {
    exit;
}

class Tax_Handler {

    /**
     * The single instance of the class
     *
     * @var Tax_Handler
     */
    private static $_instance = null;

    /**
     * Xero API instance
     *
     * @var Xero_API
     */
    private $xero_api;

    /**
     * Main Tax_Handler Instance
     *
     * @return Tax_Handler
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        $this->xero_api = Xero_API::instance();
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Sync tax rates daily
        add_action('ix_woo_xero_daily_sync', [$this, 'sync_tax_rates']);

        // Add tax mapping field to settings
        add_action('admin_init', [$this, 'add_tax_mapping_field']);

        // Apply tax mappings to invoices
        add_filter('ix_woo_xero_invoice_data', [$this, 'apply_tax_mappings'], 10, 2);

        // Apply tax mappings to products
        add_filter('ix_woo_xero_product_data', [$this, 'apply_product_tax_mappings'], 10, 2);
    }

    /**
     * Synchronize tax rates with Xero
     */
    public function sync_tax_rates() {
        if (!$this->xero_api->is_connected()) {
            Logger::error('Tax rate sync failed: Not connected to Xero');
            return false;
        }

        try {
            $xero_tax_rates = $this->xero_api->get_tax_rates();
            $wc_tax_rates = \WC_Tax::get_rates();
            $mappings = get_option('ix_woo_xero_tax_mappings', []);

            $new_mappings = $this->match_tax_rates($wc_tax_rates, $xero_tax_rates, $mappings);

            update_option('ix_woo_xero_tax_mappings', $new_mappings);
            Logger::info('Tax rates synchronized successfully');

            return true;
        } catch (\Exception $e) {
            Logger::error('Tax rate sync error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Match WooCommerce tax rates to Xero tax types
     */
    private function match_tax_rates($wc_rates, $xero_rates, $existing_mappings = []) {
        $mappings = $existing_mappings;

        foreach ($wc_rates as $wc_rate_id => $wc_rate) {
            // Skip if already mapped
            if (isset($mappings[$wc_rate_id])) {
                continue;
            }

            // Find matching Xero rate
            foreach ($xero_rates as $xero_rate) {
                if ($this->tax_rates_match($wc_rate, $xero_rate)) {
                    $mappings[$wc_rate_id] = $xero_rate['TaxType'];
                    break;
                }
            }
        }

        return $mappings;
    }

    /**
     * Check if tax rates match
     */
    private function tax_rates_match($wc_rate, $xero_rate) {
        $wc_rate_value = (float) $wc_rate['rate'];
        $xero_rate_value = (float) $xero_rate['EffectiveRate'] * 100;

        // Compare rates with 0.01% tolerance
        return abs($wc_rate_value - $xero_rate_value) < 0.01;
    }

    /**
     * Add tax mapping field to settings
     */
    public function add_tax_mapping_field() {
        add_settings_field(
            'ix_woo_xero_tax_mappings',
            __('Tax Rate Mappings', 'ix-woo-xero'),
            [$this, 'render_tax_mapping_field'],
            'ix_woo_xero_tax_settings',
            'ix_woo_xero_tax_settings_section'
        );

        register_setting(
            'ix_woo_xero_tax_settings',
            'ix_woo_xero_tax_mappings',
            [$this, 'sanitize_tax_mappings']
        );
    }

    /**
     * Render tax mapping field
     */
    public function render_tax_mapping_field() {
        $mappings = get_option('ix_woo_xero_tax_mappings', []);
        $tax_rates = \WC_Tax::get_rates();
        $xero_tax_rates = $this->xero_api->get_tax_rates();
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php _e('WooCommerce Tax Rate', 'ix-woo-xero'); ?></th>
                    <th><?php _e('Xero Tax Type', 'ix-woo-xero'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tax_rates as $rate_id => $rate) : ?>
                <tr>
                    <td>
                        <?php echo esc_html($rate['tax_rate_name'] . ' (' . $rate['rate'] . '%)'); ?>
                    </td>
                    <td>
                        <select name="ix_woo_xero_tax_mappings[<?php echo esc_attr($rate_id); ?>]">
                            <option value=""><?php _e('-- Not Mapped --', 'ix-woo-xero'); ?></option>
                            <?php foreach ($xero_tax_rates as $xero_rate) : ?>
                                <?php if ($xero_rate['Status'] === 'ACTIVE') : ?>
                                <option value="<?php echo esc_attr($xero_rate['TaxType']); ?>" <?php selected(isset($mappings[$rate_id]) ? $mappings[$rate_id] : '', $xero_rate['TaxType']); ?>>
                                    <?php echo esc_html($xero_rate['Name']); ?>
                                </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="description">
            <?php _e('Map your WooCommerce tax rates to Xero tax types for accurate reporting.', 'ix-woo-xero'); ?>
        </p>
        <?php
    }

    /**
     * Sanitize tax mappings
     */
    public function sanitize_tax_mappings($input) {
        $sanitized = [];
        
        if (is_array($input)) {
            foreach ($input as $wc_rate_id => $xero_tax_type) {
                $sanitized[sanitize_key($wc_rate_id)] = sanitize_text_field($xero_tax_type);
            }
        }
        
        return $sanitized;
    }

    /**
     * Apply tax mappings to invoice data
     */
    public function apply_tax_mappings($invoice_data, $order) {
        $mappings = get_option('ix_woo_xero_tax_mappings', []);
        
        if (empty($mappings) || empty($invoice_data['LineItems'])) {
            return $invoice_data;
        }

        foreach ($invoice_data['LineItems'] as &$item) {
            $tax_rate_id = $this->get_line_item_tax_rate_id($item, $order);
            
            if ($tax_rate_id && isset($mappings[$tax_rate_id])) {
                $item['TaxType'] = $mappings[$tax_rate_id];
            }
        }

        return $invoice_data;
    }

    /**
     * Apply tax mappings to product data
     */
    public function apply_product_tax_mappings($product_data, $product) {
        $mappings = get_option('ix_woo_xero_tax_mappings', []);
        $tax_class = $product->get_tax_class();
        $tax_rates = \WC_Tax::get_rates_for_tax_class($tax_class);
        
        if (empty($mappings) || empty($tax_rates)) {
            return $product_data;
        }

        foreach ($tax_rates as $rate_id => $rate) {
            if (isset($mappings[$rate_id])) {
                $product_data['SalesDetails']['TaxType'] = $mappings[$rate_id];
                $product_data['PurchaseDetails']['TaxType'] = $mappings[$rate_id];
                break;
            }
        }

        return $product_data;
    }

    /**
     * Get tax rate ID for line item
     */
    private function get_line_item_tax_rate_id($item, $order) {
        // For shipping items
        if (isset($item['Description']) && $item['Description'] === __('Shipping', 'ix-woo-xero')) {
            $shipping_taxes = $order->get_shipping_taxes();
            if (!empty($shipping_taxes)) {
                $tax_rate_id = key($shipping_taxes);
                return $this->get_tax_rate_id_from_tax_total($tax_rate_id);
            }
            return false;
        }

        // For product items
        $tax_items = $order->get_items('tax');
        foreach ($tax_items as $tax_item) {
            if ($tax_item->get_rate_percent() == $item['TaxAmount']) {
                return $tax_item->get_rate_id();
            }
        }

        return false;
    }

    /**
     * Get tax rate ID from tax total
     */
    private function get_tax_rate_id_from_tax_total($tax_total_id) {
        global $wpdb;
        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT tax_rate_id FROM {$wpdb->prefix}woocommerce_tax_rates 
                 WHERE tax_rate_id = %d",
                $tax_total_id
            )
        );
    }

    /**
     * Get Xero tax type for WooCommerce tax rate
     */
    public function get_xero_tax_type($wc_rate_id) {
        $mappings = get_option('ix_woo_xero_tax_mappings', []);
        return isset($mappings[$wc_rate_id]) ? $mappings[$wc_rate_id] : null;
    }
}

Tax_Handler::instance();