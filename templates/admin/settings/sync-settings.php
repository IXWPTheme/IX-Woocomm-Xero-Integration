<?php
/**
 * Sync Settings Template
 *
 * @package IX_Woo_Xero_Integration
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Get current settings
$settings = IX_Helper::get_settings();
$order_statuses = isset($settings['order_statuses']) ? $settings['order_statuses'] : array('completed', 'processing');
$sync_frequency = isset($settings['sync_frequency']) ? $settings['sync_frequency'] : 'daily';
$sync_products = isset($settings['sync_products']) ? $settings['sync_products'] : 'yes';
$sync_customers = isset($settings['sync_customers']) ? $settings['sync_customers'] : 'yes';
$sync_invoices = isset($settings['sync_invoices']) ? $settings['sync_invoices'] : 'yes';
$invoice_prefix = isset($settings['invoice_prefix']) ? $settings['invoice_prefix'] : '';
$send_invoices = isset($settings['send_invoices']) ? $settings['send_invoices'] : 'no';

// WooCommerce order statuses
$wc_statuses = wc_get_order_statuses();

// Sync frequency options
$frequency_options = array(
    'hourly'     => __('Hourly', 'ix-woo-xero'),
    'twicedaily' => __('Twice Daily', 'ix-woo-xero'),
    'daily'      => __('Daily', 'ix-woo-xero'),
    'weekly'     => __('Weekly', 'ix-woo-xero'),
    'manual'     => __('Manual Only', 'ix-woo-xero')
);
?>

<div class="ix-settings-section">
    <h2><?php esc_html_e('Synchronization Settings', 'ix-woo-xero'); ?></h2>
    
    <table class="form-table">
        <tbody>
            <!-- Sync Frequency -->
            <tr>
                <th scope="row">
                    <label for="ix_sync_frequency">
                        <?php esc_html_e('Sync Frequency', 'ix-woo-xero'); ?>
                    </label>
                </th>
                <td>
                    <select id="ix_sync_frequency" name="ix_woo_xero_settings[sync_frequency]" class="regular-text">
                        <?php foreach ($frequency_options as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($sync_frequency, $value); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php esc_html_e('How often should the plugin automatically sync with Xero?', 'ix-woo-xero'); ?>
                    </p>
                </td>
            </tr>
            
            <!-- Order Statuses to Sync -->
            <tr>
                <th scope="row">
                    <label><?php esc_html_e('Order Statuses to Sync', 'ix-woo-xero'); ?></label>
                </th>
                <td>
                    <fieldset>
                        <?php foreach ($wc_statuses as $status => $status_name) : 
                            $status = str_replace('wc-', '', $status); // Remove wc- prefix
                        ?>
                            <label>
                                <input type="checkbox" 
                                       name="ix_woo_xero_settings[order_statuses][]" 
                                       value="<?php echo esc_attr($status); ?>" 
                                       <?php checked(in_array($status, $order_statuses)); ?>>
                                <?php echo esc_html($status_name); ?>
                            </label><br>
                        <?php endforeach; ?>
                    </fieldset>
                    <p class="description">
                        <?php esc_html_e('Only orders with these statuses will be synced to Xero.', 'ix-woo-xero'); ?>
                    </p>
                </td>
            </tr>
            
            <!-- Data Types to Sync -->
            <tr>
                <th scope="row">
                    <label><?php esc_html_e('Data Types to Sync', 'ix-woo-xero'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="ix_woo_xero_settings[sync_products]" 
                               value="yes" 
                               <?php checked($sync_products, 'yes'); ?>>
                        <?php esc_html_e('Sync Products', 'ix-woo-xero'); ?>
                    </label><br>
                    
                    <label>
                        <input type="checkbox" 
                               name="ix_woo_xero_settings[sync_customers]" 
                               value="yes" 
                               <?php checked($sync_customers, 'yes'); ?>>
                        <?php esc_html_e('Sync Customers', 'ix-woo-xero'); ?>
                    </label><br>
                    
                    <label>
                        <input type="checkbox" 
                               name="ix_woo_xero_settings[sync_invoices]" 
                               value="yes" 
                               <?php checked($sync_invoices, 'yes'); ?>>
                        <?php esc_html_e('Sync Invoices', 'ix-woo-xero'); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e('Select which types of data should be synchronized with Xero.', 'ix-woo-xero'); ?>
                    </p>
                </td>
            </tr>
            
            <!-- Invoice Settings -->
            <tr>
                <th scope="row">
                    <label for="ix_invoice_prefix">
                        <?php esc_html_e('Invoice Prefix', 'ix-woo-xero'); ?>
                    </label>
                </th>
                <td>
                    <input type="text" 
                           id="ix_invoice_prefix" 
                           name="ix_woo_xero_settings[invoice_prefix]" 
                           value="<?php echo esc_attr($invoice_prefix); ?>" 
                           class="regular-text"
                           maxlength="10">
                    <p class="description">
                        <?php esc_html_e('Optional prefix for Xero invoice numbers (max 10 characters).', 'ix-woo-xero'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="ix_send_invoices">
                        <?php esc_html_e('Invoice Email', 'ix-woo-xero'); ?>
                    </label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                               id="ix_send_invoices" 
                               name="ix_woo_xero_settings[send_invoices]" 
                               value="yes" 
                               <?php checked($send_invoices, 'yes'); ?>>
                        <?php esc_html_e('Send invoice emails from Xero', 'ix-woo-xero'); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e('When enabled, Xero will send invoice emails to customers.', 'ix-woo-xero'); ?>
                        <?php esc_html_e('Note: This will prevent WooCommerce from sending its own invoice emails.', 'ix-woo-xero'); ?>
                    </p>
                </td>
            </tr>
            
            <!-- Manual Sync -->
            <tr>
                <th scope="row">
                    <?php esc_html_e('Manual Sync', 'ix-woo-xero'); ?>
                </th>
                <td>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=ix-woo-xero-status&action=sync_now')); ?>" class="button">
                        <?php esc_html_e('Run Sync Now', 'ix-woo-xero'); ?>
                    </a>
                    <p class="description">
                        <?php esc_html_e('Manually trigger a synchronization with Xero.', 'ix-woo-xero'); ?>
                    </p>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<style>
.ix-settings-section .form-table th {
    width: 250px;
}

.ix-settings-section .description {
    color: #646970;
    font-style: normal;
    margin-top: 8px;
}
</style>