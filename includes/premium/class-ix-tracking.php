<?php
/**
 * IX Woo Xero Integration - Tracking Categories (Premium)
 * 
 * Handles all tracking category functionality including:
 * - Synchronization with Xero
 * - Product/order assignment
 * - Admin interface
 */

namespace IX_Woo_Xero\Premium;

use IX_Woo_Xero\API\Xero_API;
use IX_Woo_Xero\Utilities\Logger;

if (!defined('ABSPATH')) {
    exit;
}

class Tracking_Categories {

    /**
     * The single instance of the class
     *
     * @var Tracking_Categories
     */
    private static $_instance = null;

    /**
     * Xero API instance
     *
     * @var Xero_API
     */
    private $xero_api;

    /**
     * Main Tracking_Categories Instance
     *
     * @return Tracking_Categories
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
        // Sync tracking categories weekly
        add_action('ix_woo_xero_weekly_sync', [$this, 'sync_tracking_categories']);

        // Add tracking fields to product edit screen
        add_action('woocommerce_product_options_general_product_data', [$this, 'add_product_tracking_fields']);

        // Save tracking fields
        add_action('woocommerce_process_product_meta', [$this, 'save_product_tracking_fields']);

        // Apply tracking to Xero data
        add_filter('ix_woo_xero_product_data', [$this, 'apply_product_tracking'], 10, 2);
        add_filter('ix_woo_xero_invoice_data', [$this, 'apply_order_tracking'], 10, 2);

        // Add bulk edit support
        add_action('woocommerce_product_bulk_edit_end', [$this, 'add_bulk_edit_tracking_fields']);
        add_action('woocommerce_product_bulk_edit_save', [$this, 'save_bulk_edit_tracking_fields']);
    }

    /**
     * Synchronize tracking categories with Xero
     */
    public function sync_tracking_categories() {
        if (!$this->xero_api->is_connected()) {
            Logger::error('Tracking sync failed: Not connected to Xero');
            return false;
        }

        try {
            $categories = $this->xero_api->get_tracking_categories();
            set_transient('ix_woo_xero_tracking_categories', $categories, WEEK_IN_SECONDS);
            Logger::info('Tracking categories synchronized successfully');
            return true;
        } catch (\Exception $e) {
            Logger::error('Tracking sync error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get tracking categories
     */
    public function get_tracking_categories() {
        $categories = get_transient('ix_woo_xero_tracking_categories');

        if (false === $categories) {
            $this->sync_tracking_categories();
            $categories = get_transient('ix_woo_xero_tracking_categories');
        }

        return is_array($categories) ? $categories : [];
    }

    /**
     * Add tracking fields to product edit screen
     */
    public function add_product_tracking_fields() {
        $categories = $this->get_tracking_categories();

        if (empty($categories)) {
            return;
        }

        echo '<div class="options_group">';
        echo '<h4>' . __('Xero Tracking Categories', 'ix-woo-xero') . '</h4>';

        foreach ($categories as $category) {
            $options = [];
            foreach ($category['Options'] as $option) {
                $options[$option['TrackingOptionID']] = $option['Name'];
            }

            woocommerce_wp_select([
                'id'          => '_xero_tracking_' . sanitize_title($category['Name']),
                'label'       => $category['Name'],
                'options'     => $options,
                'description' => __('Select Xero tracking option', 'ix-woo-xero'),
                'desc_tip'    => true,
            ]);
        }

        echo '</div>';
    }

    /**
     * Save product tracking fields
     */
    public function save_product_tracking_fields($product_id) {
        $categories = $this->get_tracking_categories();

        foreach ($categories as $category) {
            $field_name = '_xero_tracking_' . sanitize_title($category['Name']);
            if (isset($_POST[$field_name])) {
                update_post_meta($product_id, $field_name, sanitize_text_field($_POST[$field_name]));
            } else {
                delete_post_meta($product_id, $field_name);
            }
        }
    }

    /**
     * Apply tracking categories to product data
     */
    public function apply_product_tracking($product_data, $product) {
        $categories = $this->get_tracking_categories();
        $tracking_options = [];

        foreach ($categories as $category) {
            $option_id = get_post_meta($product->get_id(), '_xero_tracking_' . sanitize_title($category['Name']), true);

            if ($option_id) {
                $tracking_options[] = [
                    'TrackingCategoryID' => $category['TrackingCategoryID'],
                    'TrackingOptionID' => $option_id
                ];
            }
        }

        if (!empty($tracking_options)) {
            $product_data['Tracking'] = $tracking_options;
        }

        return $product_data;
    }

    /**
     * Apply tracking categories to order data
     */
    public function apply_order_tracking($invoice_data, $order) {
        $tracking_options = [];
        
        // Get tracking from products
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product) {
                $product_tracking = $this->get_product_tracking($product);
                if (!empty($product_tracking)) {
                    $tracking_options = array_merge($tracking_options, $product_tracking);
                }
            }
        }

        // Apply to all line items
        if (!empty($tracking_options)) {
            foreach ($invoice_data['LineItems'] as &$item) {
                $item['Tracking'] = $tracking_options;
            }
        }

        return $invoice_data;
    }

    /**
     * Get tracking options for a product
     */
    private function get_product_tracking($product) {
        $categories = $this->get_tracking_categories();
        $tracking_options = [];

        foreach ($categories as $category) {
            $option_id = get_post_meta($product->get_id(), '_xero_tracking_' . sanitize_title($category['Name']), true);

            if ($option_id) {
                $tracking_options[] = [
                    'TrackingCategoryID' => $category['TrackingCategoryID'],
                    'TrackingOptionID' => $option_id
                ];
            }
        }

        return $tracking_options;
    }

    /**
     * Add bulk edit tracking fields
     */
    public function add_bulk_edit_tracking_fields() {
        $categories = $this->get_tracking_categories();

        if (empty($categories)) {
            return;
        }
        ?>
        <div class="inline-edit-group">
            <label class="alignleft">
                <span class="title"><?php _e('Xero Tracking', 'ix-woo-xero'); ?></span>
                <?php foreach ($categories as $category) : ?>
                <span class="input-text-wrap">
                    <select class="xero_tracking" name="_xero_tracking_<?php echo sanitize_title($category['Name']); ?>">
                        <option value=""><?php _e('— No change —', 'ix-woo-xero'); ?></option>
                        <?php foreach ($category['Options'] as $option) : ?>
                        <option value="<?php echo esc_attr($option['TrackingOptionID']); ?>">
                            <?php echo esc_html($category['Name'] . ': ' . $option['Name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </span>
                <?php endforeach; ?>
            </label>
        </div>
        <?php
    }

    /**
     * Save bulk edit tracking fields
     */
    public function save_bulk_edit_tracking_fields($product) {
        $categories = $this->get_tracking_categories();

        foreach ($categories as $category) {
            $field_name = '_xero_tracking_' . sanitize_title($category['Name']);
            if (isset($_REQUEST[$field_name])) {
                $value = wc_clean($_REQUEST[$field_name]);
                if (!empty($value)) {
                    update_post_meta($product->get_id(), $field_name, $value);
                } else {
                    delete_post_meta($product->get_id(), $field_name);
                }
            }
        }
    }

    /**
     * Get tracking option name by ID
     */
    public function get_tracking_option_name($category_id, $option_id) {
        $categories = $this->get_tracking_categories();

        foreach ($categories as $category) {
            if ($category['TrackingCategoryID'] === $category_id) {
                foreach ($category['Options'] as $option) {
                    if ($option['TrackingOptionID'] === $option_id) {
                        return $category['Name'] . ': ' . $option['Name'];
                    }
                }
            }
        }

        return '';
    }
}

Tracking_Categories::instance();