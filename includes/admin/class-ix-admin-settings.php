<?php
/**
 * IX Woo Xero Integration - Admin Settings Class
 * 
 * Handles all settings-related functionality including:
 * - Settings page rendering
 * - Settings field registration
 * - Settings validation
 */

namespace IX_Woo_Xero\Admin;

use IX_Woo_Xero\API\Xero_API;

if (!defined('ABSPATH')) {
    exit;
}

class Admin_Settings {

    /**
     * The single instance of the class
     *
     * @var Admin_Settings
     */
    private static $_instance = null;

    /**
     * Xero API instance
     *
     * @var Xero_API
     */
    private $xero_api;

    /**
     * Settings tabs
     *
     * @var array
     */
    private $tabs = [];

    /**
     * Main Admin_Settings Instance
     *
     * @return Admin_Settings
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
        $this->init_tabs();
        $this->init_hooks();
    }

    /**
     * Initialize settings tabs
     */
    private function init_tabs() {
        $this->tabs = [
            'api' => [
                'label' => __('API Settings', 'ix-woo-xero'),
                'sections' => [
                    'api_credentials' => [
                        'title' => __('API Credentials', 'ix-woo-xero'),
                        'callback' => [$this, 'api_credentials_section'],
                        'fields' => [
                            'client_id' => [
                                'title' => __('Client ID', 'ix-woo-xero'),
                                'callback' => [$this, 'client_id_field'],
                                'sanitize' => 'sanitize_text_field'
                            ],
                            'client_secret' => [
                                'title' => __('Client Secret', 'ix-woo-xero'),
                                'callback' => [$this, 'client_secret_field'],
                                'sanitize' => 'sanitize_text_field'
                            ],
                            'webhook_key' => [
                                'title' => __('Webhook Signing Key', 'ix-woo-xero'),
                                'callback' => [$this, 'webhook_key_field'],
                                'sanitize' => 'sanitize_text_field'
                            ]
                        ]
                    ]
                ]
            ],
            'account_mapping' => [
                'label' => __('Account Mapping', 'ix-woo-xero'),
                'sections' => [
                    'account_codes' => [
                        'title' => __('Default Account Codes', 'ix-woo-xero'),
                        'callback' => [$this, 'account_codes_section'],
                        'fields' => [
                            'sales_account' => [
                                'title' => __('Sales Account', 'ix-woo-xero'),
                                'callback' => [$this, 'sales_account_field'],
                                'sanitize' => 'sanitize_text_field'
                            ],
                            'purchase_account' => [
                                'title' => __('Purchase Account', 'ix-woo-xero'),
                                'callback' => [$this, 'purchase_account_field'],
                                'sanitize' => 'sanitize_text_field'
                            ],
                            'inventory_account' => [
                                'title' => __('Inventory Account', 'ix-woo-xero'),
                                'callback' => [$this, 'inventory_account_field'],
                                'sanitize' => 'sanitize_text_field'
                            ],
                            'shipping_account' => [
                                'title' => __('Shipping Account', 'ix-woo-xero'),
                                'callback' => [$this, 'shipping_account_field'],
                                'sanitize' => 'sanitize_text_field'
                            ],
                            'fees_account' => [
                                'title' => __('Fees Account', 'ix-woo-xero'),
                                'callback' => [$this, 'fees_account_field'],
                                'sanitize' => 'sanitize_text_field'
                            ]
                        ]
                    ]
                ]
            ],
            'sync_settings' => [
                'label' => __('Sync Settings', 'ix-woo-xero'),
                'sections' => [
                    'auto_sync' => [
                        'title' => __('Automatic Sync', 'ix-woo-xero'),
                        'callback' => [$this, 'auto_sync_section'],
                        'fields' => [
                            'auto_sync_products' => [
                                'title' => __('Auto Sync Products', 'ix-woo-xero'),
                                'callback' => [$this, 'auto_sync_products_field'],
                                'sanitize' => 'absint'
                            ],
                            'auto_sync_orders' => [
                                'title' => __('Auto Sync Orders', 'ix-woo-xero'),
                                'callback' => [$this, 'auto_sync_orders_field'],
                                'sanitize' => 'absint'
                            ],
                            'auto_sync_customers' => [
                                'title' => __('Auto Sync Customers', 'ix-woo-xero'),
                                'callback' => [$this, 'auto_sync_customers_field'],
                                'sanitize' => 'absint'
                            ],
                            'auto_sync_subscriptions' => [
                                'title' => __('Auto Sync Subscriptions', 'ix-woo-xero'),
                                'callback' => [$this, 'auto_sync_subscriptions_field'],
                                'sanitize' => 'absint'
                            ]
                        ]
                    ]
                ]
            ],
            'tax_settings' => [
                'label' => __('Tax Settings', 'ix-woo-xero'),
                'sections' => [
                    'tax_mapping' => [
                        'title' => __('Tax Rate Mapping', 'ix-woo-xero'),
                        'callback' => [$this, 'tax_mapping_section'],
                        'fields' => [
                            'tax_mappings' => [
                                'title' => __('Tax Rate Mappings', 'ix-woo-xero'),
                                'callback' => [$this, 'tax_mappings_field'],
                                'sanitize' => [$this, 'sanitize_tax_mappings']
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register settings
        add_action('admin_init', [$this, 'register_settings']);
        
        // Add settings sections and fields
        add_action('admin_init', [$this, 'add_settings_sections']);
    }

    /**
     * Register settings
     */
    public function register_settings() {
        foreach ($this->tabs as $tab_id => $tab) {
            foreach ($tab['sections'] as $section_id => $section) {
                $option_group = "ix_woo_xero_{$tab_id}_settings";
                $option_name = "ix_woo_xero_{$section_id}";
                
                register_setting(
                    $option_group,
                    $option_name,
                    [
                        'sanitize_callback' => [$this, 'sanitize_settings']
                    ]
                );
            }
        }
    }

    /**
     * Add settings sections and fields
     */
    public function add_settings_sections() {
        foreach ($this->tabs as $tab_id => $tab) {
            foreach ($tab['sections'] as $section_id => $section) {
                $option_name = "ix_woo_xero_{$section_id}";
                
                add_settings_section(
                    $section_id,
                    $section['title'],
                    $section['callback'],
                    "ix_woo_xero_{$tab_id}_settings"
                );
                
                foreach ($section['fields'] as $field_id => $field) {
                    add_settings_field(
                        $field_id,
                        $field['title'],
                        $field['callback'],
                        "ix_woo_xero_{$tab_id}_settings",
                        $section_id,
                        [
                            'label_for' => "{$option_name}_{$field_id}",
                            'class' => "ix-woo-xero-row ix-woo-xero-{$field_id}-field"
                        ]
                    );
                }
            }
        }
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = [];
        
        foreach ($input as $key => $value) {
            if (isset($this->tabs[$key]['sections'][$key]['fields'][$key]['sanitize'])) {
                $sanitize_callback = $this->tabs[$key]['sections'][$key]['fields'][$key]['sanitize'];
                $sanitized[$key] = call_user_func($sanitize_callback, $value);
            } else {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }
        
        return $sanitized;
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

    /***********************************************************************
     * Section Callbacks
     ***********************************************************************/

    /**
     * API Credentials section
     */
    public function api_credentials_section() {
        echo '<p>' . __('Enter your Xero API credentials to connect your store to Xero.', 'ix-woo-xero') . '</p>';
        
        if ($this->xero_api->is_connected()) {
            $tenant_name = $this->xero_api->get_tenant_name();
            echo '<div class="notice notice-success inline">';
            echo '<p>' . sprintf(
                __('Connected to Xero as %s. <a href="%s">Disconnect</a>', 'ix-woo-xero'),
                '<strong>' . esc_html($tenant_name) . '</strong>',
                wp_nonce_url(admin_url('admin.php?page=ix-woo-xero&disconnect_xero=1'), 'ix_woo_xero_disconnect')
            ) . '</p>';
            echo '</div>';
        }
    }

    /**
     * Account Codes section
     */
    public function account_codes_section() {
        echo '<p>' . __('Map your WooCommerce transactions to Xero accounts.', 'ix-woo-xero') . '</p>';
        
        if ($this->xero_api->is_connected()) {
            echo '<button type="button" class="button button-secondary" id="ix-refresh-accounts">';
            echo __('Refresh Account List from Xero', 'ix-woo-xero');
            echo '</button>';
        }
    }

    /**
     * Auto Sync section
     */
    public function auto_sync_section() {
        echo '<p>' . __('Configure which data should be automatically synced to Xero.', 'ix-woo-xero') . '</p>';
    }

    /**
     * Tax Mapping section
     */
    public function tax_mapping_section() {
        echo '<p>' . __('Map your WooCommerce tax rates to Xero tax types.', 'ix-woo-xero') . '</p>';
        
        if ($this->xero_api->is_connected()) {
            echo '<button type="button" class="button button-secondary" id="ix-refresh-tax-rates">';
            echo __('Refresh Tax Rates from Xero', 'ix-woo-xero');
            echo '</button>';
        }
    }

    /***********************************************************************
     * Field Callbacks
     ***********************************************************************/

    /**
     * Client ID field
     */
    public function client_id_field() {
        $value = get_option('ix_woo_xero_api_credentials_client_id', '');
        ?>
        <input type="text" id="ix_woo_xero_api_credentials_client_id" 
               name="ix_woo_xero_api_credentials[client_id]" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text" />
        <p class="description">
            <?php _e('Enter your Xero API Client ID', 'ix-woo-xero'); ?>
        </p>
        <?php
    }

    /**
     * Client Secret field
     */
    public function client_secret_field() {
        $value = get_option('ix_woo_xero_api_credentials_client_secret', '');
        ?>
        <input type="password" id="ix_woo_xero_api_credentials_client_secret" 
               name="ix_woo_xero_api_credentials[client_secret]" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text" />
        <p class="description">
            <?php _e('Enter your Xero API Client Secret', 'ix-woo-xero'); ?>
        </p>
        <?php
    }

    /**
     * Webhook Key field
     */
    public function webhook_key_field() {
        $value = get_option('ix_woo_xero_api_credentials_webhook_key', '');
        $webhook_url = home_url('/ix-xero-webhook');
        ?>
        <input type="text" id="ix_woo_xero_api_credentials_webhook_key" 
               name="ix_woo_xero_api_credentials[webhook_key]" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text" />
        <p class="description">
            <?php _e('Enter your Xero Webhook Signing Key for two-way sync', 'ix-woo-xero'); ?>
        </p>
        <?php if ($value) : ?>
        <p class="description">
            <strong><?php _e('Webhook URL:', 'ix-woo-xero'); ?></strong> 
            <code><?php echo esc_url($webhook_url); ?></code>
        </p>
        <?php endif; ?>
        <?php
    }

    /**
     * Sales Account field
     */
    public function sales_account_field() {
        $value = get_option('ix_woo_xero_account_codes_sales_account', '');
        $accounts = $this->xero_api->get_accounts();
        ?>
        <select id="ix_woo_xero_account_codes_sales_account" 
                name="ix_woo_xero_account_codes[sales_account]" 
                class="regular-text">
            <option value=""><?php _e('-- Select Account --', 'ix-woo-xero'); ?></option>
            <?php foreach ($accounts as $account) : ?>
                <?php if ($account['Status'] === 'ACTIVE' && $account['Type'] === 'REVENUE') : ?>
                <option value="<?php echo esc_attr($account['Code']); ?>" <?php selected($value, $account['Code']); ?>>
                    <?php echo esc_html($account['Name'] . ' (' . $account['Code'] . ')'); ?>
                </option>
                <?php endif; ?>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php _e('Select the Xero account for sales revenue', 'ix-woo-xero'); ?>
        </p>
        <?php
    }

    /**
     * Auto Sync Products field
     */
    public function auto_sync_products_field() {
        $value = get_option('ix_woo_xero_auto_sync_auto_sync_products', 1);
        ?>
        <label>
            <input type="checkbox" id="ix_woo_xero_auto_sync_auto_sync_products" 
                   name="ix_woo_xero_auto_sync[auto_sync_products]" 
                   value="1" <?php checked($value, 1); ?> />
            <?php _e('Automatically sync products when created or updated', 'ix-woo-xero'); ?>
        </label>
        <?php
    }

    /**
     * Tax Mappings field
     */
    public function tax_mappings_field() {
        $mappings = get_option('ix_woo_xero_tax_mapping_tax_mappings', []);
        $tax_rates = WC_Tax::get_rates();
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
                        <select name="ix_woo_xero_tax_mapping[tax_mappings][<?php echo esc_attr($rate_id); ?>]">
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
        <?php
    }

    // Additional field methods would follow the same pattern...
}

Admin_Settings::instance();