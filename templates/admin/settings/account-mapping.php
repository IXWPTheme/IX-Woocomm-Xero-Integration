<?php
/**
 * Account Mapping Settings Template
 *
 * @package IX_Woo_Xero_Integration
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Get current settings
$settings = IX_Helper::get_settings();
$default_account = isset($settings['default_account_code']) ? $settings['default_account_code'] : '';
$category_mapping = isset($settings['category_account_mapping']) ? $settings['category_account_mapping'] : array();
$tax_mapping = isset($settings['tax_mapping']) ? $settings['tax_mapping'] : array();

// Get WooCommerce tax classes
$tax_classes = WC_Tax::get_tax_classes();
array_unshift($tax_classes, 'standard');

// Get product categories
$product_categories = get_terms(array(
    'taxonomy' => 'product_cat',
    'hide_empty' => false,
));

// Xero tax types
$xero_tax_types = array(
    'NONE' => __('No Tax', 'ix-woo-xero'),
    'INPUT' => __('Input Tax', 'ix-woo-xero'),
    'INPUT2' => __('Input Tax 2', 'ix-woo-xero'),
    'OUTPUT' => __('Output Tax', 'ix-woo-xero'),
    'OUTPUT2' => __('Output Tax 2', 'ix-woo-xero'),
    'ZERORATED' => __('Zero Rated', 'ix-woo-xero'),
    'EXEMPTINPUT' => __('Exempt Input', 'ix-woo-xero'),
    'EXEMPTOUTPUT' => __('Exempt Output', 'ix-woo-xero'),
    'RRP' => __('Reduced Rate', 'ix-woo-xero'),
);
?>

<div class="ix-settings-section">
    <h2><?php esc_html_e('Account Mapping', 'ix-woo-xero'); ?></h2>
    
    <table class="form-table">
        <tbody>
            <!-- Default Account Code -->
            <tr>
                <th scope="row">
                    <label for="ix_default_account_code">
                        <?php esc_html_e('Default Account Code', 'ix-woo-xero'); ?>
                    </label>
                </th>
                <td>
                    <input type="text" 
                           id="ix_default_account_code" 
                           name="ix_woo_xero_settings[default_account_code]" 
                           value="<?php echo esc_attr($default_account); ?>" 
                           class="regular-text"
                           placeholder="e.g. 200">
                    <p class="description">
                        <?php esc_html_e('Default Xero account code for products without specific mapping.', 'ix-woo-xero'); ?>
                    </p>
                </td>
            </tr>
            
            <!-- Category to Account Mapping -->
            <tr>
                <th scope="row">
                    <label><?php esc_html_e('Category Mapping', 'ix-woo-xero'); ?></label>
                </th>
                <td>
                    <div class="ix-account-mapping-table">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Product Category', 'ix-woo-xero'); ?></th>
                                    <th><?php esc_html_e('Xero Account Code', 'ix-woo-xero'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($product_categories as $category) : ?>
                                    <tr>
                                        <td>
                                            <label for="ix_category_<?php echo esc_attr($category->term_id); ?>">
                                                <?php echo esc_html($category->name); ?>
                                            </label>
                                        </td>
                                        <td>
                                            <input type="text" 
                                                   id="ix_category_<?php echo esc_attr($category->term_id); ?>" 
                                                   name="ix_woo_xero_settings[category_account_mapping][<?php echo esc_attr($category->term_id); ?>]" 
                                                   value="<?php echo isset($category_mapping[$category->term_id]) ? esc_attr($category_mapping[$category->term_id]) : ''; ?>"
                                                   class="regular-text"
                                                   placeholder="<?php echo esc_attr($default_account); ?>">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p class="description">
                            <?php esc_html_e('Map product categories to specific Xero account codes. Leave blank to use default account.', 'ix-woo-xero'); ?>
                        </p>
                    </div>
                </td>
            </tr>
            
            <!-- Tax Class to Xero Tax Type Mapping -->
            <tr>
                <th scope="row">
                    <label><?php esc_html_e('Tax Type Mapping', 'ix-woo-xero'); ?></label>
                </th>
                <td>
                    <div class="ix-tax-mapping-table">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('WooCommerce Tax Class', 'ix-woo-xero'); ?></th>
                                    <th><?php esc_html_e('Xero Tax Type', 'ix-woo-xero'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tax_classes as $tax_class) : 
                                    $class_name = ('standard' === $tax_class) ? __('Standard', 'ix-woo-xero') : $tax_class;
                                    $class_slug = ('standard' === $tax_class) ? 'standard' : sanitize_title($tax_class);
                                ?>
                                    <tr>
                                        <td>
                                            <label for="ix_tax_<?php echo esc_attr($class_slug); ?>">
                                                <?php echo esc_html($class_name); ?>
                                            </label>
                                        </td>
                                        <td>
                                            <select id="ix_tax_<?php echo esc_attr($class_slug); ?>" 
                                                    name="ix_woo_xero_settings[tax_mapping][<?php echo esc_attr($class_slug); ?>]" 
                                                    class="regular-text">
                                                <?php foreach ($xero_tax_types as $value => $label) : ?>
                                                    <option value="<?php echo esc_attr($value); ?>" <?php selected(isset($tax_mapping[$class_slug]) ? $tax_mapping[$class_slug] : '', $value); ?>>
                                                        <?php echo esc_html($label); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p class="description">
                            <?php esc_html_e('Map WooCommerce tax classes to Xero tax types.', 'ix-woo-xero'); ?>
                        </p>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<style>
.ix-account-mapping-table,
.ix-tax-mapping-table {
    max-height: 400px;
    overflow-y: auto;
    margin-bottom: 15px;
    border: 1px solid #ddd;
}

.ix-account-mapping-table table,
.ix-tax-mapping-table table {
    margin: 0;
    border: none;
}

.ix-account-mapping-table th,
.ix-tax-mapping-table th {
    font-weight: 600;
}
</style>