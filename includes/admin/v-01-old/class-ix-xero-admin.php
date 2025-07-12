<?php
class IX_Xero_Admin
{
    private $xero_api;
    private $current_tab = 'settings';

    public function __construct($xero_api)
    {
        $this->xero_api = $xero_api;

        // Add settings link to plugin page
        add_filter('plugin_action_links_' . IX_WOOCOMM_XERO_PLUGIN_BASENAME, array($this, 'add_settings_link'));
    }

    public function add_settings_link($links)
    {
        $settings_link = '<a href="' . admin_url('admin.php?page=ix-xero-settings') . '">' . __('Settings', 'ix-woocomm-xero') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function init()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function enqueue_scripts($hook)
    {
        if ('woocommerce_page_ix-xero-settings' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'ix-woocomm-xero-admin',
            IX_WOOCOMM_XERO_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            IX_WOOCOMM_XERO_VERSION
        );

        wp_enqueue_script(
            'ix-woocomm-xero-admin',
            IX_WOOCOMM_XERO_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            IX_WOOCOMM_XERO_VERSION,
            true
        );
    }

    public function add_admin_menu()
    {
        add_submenu_page(
            'woocommerce',
            __('Xero Integration Settings', 'ix-woocomm-xero'),
            __('Xero Integration', 'ix-woocomm-xero'),
            'manage_options',
            'ix-xero-settings',
            array($this, 'settings_page')
        );
    }

    public function settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Get current tab
        $this->current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'settings';

        // Handle OAuth callback
        if (isset($_GET['code']) && isset($_GET['state'])) {
            if (wp_verify_nonce($_GET['state'], 'xero_auth_state')) {
                try {
                    $this->xero_api->exchange_code_for_token($_GET['code']);
                    add_settings_error('ix_xero_messages', 'ix_xero_message', __('Successfully connected to Xero!', 'ix-woocomm-xero'), 'success');
                } catch (Exception $e) {
                    add_settings_error('ix_xero_messages', 'ix_xero_message', $e->getMessage(), 'error');
                }
            } else {
                add_settings_error('ix_xero_messages', 'ix_xero_message', __('Invalid state parameter', 'ix-woocomm-xero'), 'error');
            }

            // Redirect to clean URL
            wp_redirect(admin_url('admin.php?page=ix-xero-settings'));
            exit;
        }

        // Handle disconnect
        if (isset($_POST['ix_xero_disconnect'])) {
            check_admin_referer('ix_xero_disconnect', 'ix_xero_nonce');
            $this->xero_api->disconnect();
            add_settings_error('ix_xero_messages', 'ix_xero_message', __('Disconnected from Xero.', 'ix-woocomm-xero'), 'success');
        }

        settings_errors('ix_xero_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <nav class="nav-tab-wrapper">
                <a href="<?php echo admin_url('admin.php?page=ix-xero-settings&tab=settings'); ?>" class="nav-tab <?php echo $this->current_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Connection', 'ix-woocomm-xero'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=ix-xero-settings&tab=products'); ?>" class="nav-tab <?php echo $this->current_tab === 'products' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Products', 'ix-woocomm-xero'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=ix-xero-settings&tab=invoices'); ?>" class="nav-tab <?php echo $this->current_tab === 'invoices' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Invoices', 'ix-woocomm-xero'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=ix-xero-settings&tab=customers'); ?>" class="nav-tab <?php echo $this->current_tab === 'customers' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Customers', 'ix-woocomm-xero'); ?>
                </a>
            </nav>

            <div class="ix-xero-settings-tab-content">
                <?php if ($this->current_tab === 'settings'): ?>
                    <?php $this->render_settings_tab(); ?>
                <?php elseif ($this->current_tab === 'products'): ?>
                    <?php $this->render_products_tab(); ?>
                <?php elseif ($this->current_tab === 'invoices'): ?>
                    <?php $this->render_invoices_tab(); ?>
                <?php elseif ($this->current_tab === 'customers'): ?>
                    <?php $this->render_customers_tab(); ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private function render_settings_tab()
    {
        ?>
        <div class="ix-xero-connection-status">
            <?php if ($this->xero_api->is_connected()): ?>
                <div class="notice notice-success">
                    <p><?php _e('Connected to Xero.', 'ix-woocomm-xero'); ?></p>
                </div>

                <form method="post">
                    <?php wp_nonce_field('ix_xero_disconnect', 'ix_xero_nonce'); ?>
                    <input type="submit" name="ix_xero_disconnect" class="button button-secondary"
                        value="<?php esc_attr_e('Disconnect from Xero', 'ix-woocomm-xero'); ?>">
                </form>
            <?php else: ?>
                <div class="notice notice-warning">
                    <p><?php _e('Not connected to Xero.', 'ix-woocomm-xero'); ?></p>
                </div>

                <a href="<?php echo esc_url($this->xero_api->get_auth_url()); ?>" class="button button-primary">
                    <?php _e('Connect to Xero', 'ix-woocomm-xero'); ?>
                </a>
            <?php endif; ?>
        </div>

        <form action="options.php" method="post">
            <?php
            settings_fields('ix_xero');
            do_settings_sections('ix-xero-settings-connection');
            submit_button(__('Save Settings', 'ix-woocomm-xero'));
            ?>
        </form>
        <?php
    }

    private function render_products_tab()
    {
        ?>
        <form action="options.php" method="post">
            <?php
            settings_fields('ix_xero');
            do_settings_sections('ix-xero-settings-products');
            submit_button(__('Save Settings', 'ix-woocomm-xero'));
            ?>
        </form>
        <?php
    }

    private function render_invoices_tab()
    {
        ?>
        <form action="options.php" method="post">
            <?php
            settings_fields('ix_xero');
            do_settings_sections('ix-xero-settings-invoices');
            submit_button(__('Save Settings', 'ix-woocomm-xero'));
            ?>
        </form>
        <?php
    }

    private function render_customers_tab()
    {
        ?>
        <div class="ix-xero-customers-sync">
            <h2><?php _e('Customer Synchronization', 'ix-woocomm-xero'); ?></h2>
            
            <?php if (isset($_GET['customers_synced'])): ?>
                <div class="notice notice-success">
                    <p><?php printf(__('Successfully synced %d customers with Xero.', 'ix-woocomm-xero'), intval($_GET['customers_synced'])); ?></p>
                </div>
            <?php endif; ?>
            
            <p><?php _e('Sync your WooCommerce customers with Xero contacts.', 'ix-woocomm-xero'); ?></p>
            
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="ix_xero_sync_customers">
                <?php wp_nonce_field('ix_xero_sync_customers'); ?>
                <p>
                    <input type="submit" class="button button-primary" value="<?php esc_attr_e('Sync All Customers Now', 'ix-woocomm-xero'); ?>">
                </p>
            </form>
            
            <form action="options.php" method="post">
                <?php
                settings_fields('ix_xero');
                do_settings_sections('ix-xero-settings-customers');
                submit_button(__('Save Settings', 'ix-woocomm-xero'));
                ?>
            </form>
        </div>
        <?php
    }

    public function settings_init()
    {
        // Register all settings with sanitization
        $this->register_settings();

        // Connection Tab
        add_settings_section(
            'ix_xero_section_connection',
            __('API Connection', 'ix-woocomm-xero'),
            array($this, 'section_connection_cb'),
            'ix-xero-settings-connection'
        );

        add_settings_field(
            'ix_xero_client_id',
            __('Client ID', 'ix-woocomm-xero'),
            array($this, 'field_text_cb'),
            'ix-xero-settings-connection',
            'ix_xero_section_connection',
            array(
                'label_for' => 'ix_xero_client_id',
                'description' => __('Your Xero app client ID', 'ix-woocomm-xero'),
                'default' => ''
            )
        );

        add_settings_field(
            'ix_xero_client_secret',
            __('Client Secret', 'ix-woocomm-xero'),
            array($this, 'field_password_cb'),
            'ix-xero-settings-connection',
            'ix_xero_section_connection',
            array(
                'label_for' => 'ix_xero_client_secret',
                'description' => __('Your Xero app client secret', 'ix-woocomm-xero'),
                'default' => ''
            )
        );

        // Products Tab
        add_settings_section(
            'ix_xero_section_products',
            __('Product Synchronization', 'ix-woocomm-xero'),
            array($this, 'section_products_cb'),
            'ix-xero-settings-products'
        );

        add_settings_field(
            'ix_xero_auto_sync_products',
            __('Auto-sync Products', 'ix-woocomm-xero'),
            array($this, 'field_checkbox_cb'),
            'ix-xero-settings-products',
            'ix_xero_section_products',
            array(
                'label_for' => 'ix_xero_auto_sync_products',
                'description' => __('Automatically sync products when they are created or updated', 'ix-woocomm-xero'),
                'default' => 'yes'
            )
        );

        add_settings_field(
            'ix_xero_inventory_account_code',
            __('Inventory Account Code', 'ix-woocomm-xero'),
            array($this, 'field_text_cb'),
            'ix-xero-settings-products',
            'ix_xero_section_products',
            array(
                'label_for' => 'ix_xero_inventory_account_code',
                'description' => __('Xero account code for inventory items', 'ix-woocomm-xero'),
                'default' => '120'
            )
        );

        // Invoices Tab
        add_settings_section(
            'ix_xero_section_invoice',
            __('Invoice Settings', 'ix-woocomm-xero'),
            array($this, 'section_invoice_cb'),
            'ix-xero-settings-invoices'
        );

        add_settings_field(
            'ix_xero_auto_create_invoice',
            __('Automatically Create Invoice', 'ix-woocomm-xero'),
            array($this, 'field_checkbox_cb'),
            'ix-xero-settings-invoices',
            'ix_xero_section_invoice',
            array(
                'label_for' => 'ix_xero_auto_create_invoice',
                'description' => __('Create Xero invoice automatically when WooCommerce order is created', 'ix-woocomm-xero'),
                'default' => 'yes'
            )
        );

        add_settings_field(
            'ix_xero_invoice_prefix',
            __('Invoice Prefix', 'ix-woocomm-xero'),
            array($this, 'field_invoice_prefix_cb'),
            'ix-xero-settings-invoices',
            'ix_xero_section_invoice',
            array(
                'label_for' => 'ix_xero_invoice_prefix',
                'description' => __('Allows you to prefix all your invoices', 'ix-woocomm-xero'),
                'default' => 'PHSC-'
            )
        );

        add_settings_field(
            'ix_xero_sales_account_code',
            __('Sales Account Code', 'ix-woocomm-xero'),
            array($this, 'field_text_cb'),
            'ix-xero-settings-invoices',
            'ix_xero_section_invoice',
            array(
                'label_for' => 'ix_xero_sales_account_code',
                'description' => __('Default account code for sales items', 'ix-woocomm-xero'),
                'default' => '200'
            )
        );

        add_settings_field(
            'ix_xero_shipping_account_code',
            __('Shipping Account Code', 'ix-woocomm-xero'),
            array($this, 'field_text_cb'),
            'ix-xero-settings-invoices',
            'ix_xero_section_invoice',
            array(
                'label_for' => 'ix_xero_shipping_account_code',
                'description' => __('Account code for shipping charges', 'ix-woocomm-xero'),
                'default' => '201'
            )
        );

        // Customers Tab
        add_settings_section(
            'ix_xero_section_customers',
            __('Customer Settings', 'ix-woocomm-xero'),
            array($this, 'section_customers_cb'),
            'ix-xero-settings-customers'
        );

        add_settings_field(
            'ix_xero_auto_sync_customers',
            __('Auto-sync Customers', 'ix-woocomm-xero'),
            array($this, 'field_checkbox_cb'),
            'ix-xero-settings-customers',
            'ix_xero_section_customers',
            array(
                'label_for' => 'ix_xero_auto_sync_customers',
                'description' => __('Automatically sync customers when they register or update their profile', 'ix-woocomm-xero'),
                'default' => 'yes'
            )
        );
    }

    private function register_settings()
    {
        // Connection settings
        register_setting('ix_xero', 'ix_xero_client_id', 'sanitize_text_field');
        register_setting('ix_xero', 'ix_xero_client_secret', 'sanitize_text_field');

        // Product settings
        register_setting('ix_xero', 'ix_xero_auto_sync_products', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'yes'
        ));
        register_setting('ix_xero', 'ix_xero_inventory_account_code', 'sanitize_text_field');

        // Invoice settings
        register_setting('ix_xero', 'ix_xero_auto_create_invoice', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'yes'
        ));
        register_setting('ix_xero', 'ix_xero_invoice_prefix', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'PHSC-'
        ));
        register_setting('ix_xero', 'ix_xero_sales_account_code', 'sanitize_text_field');
        register_setting('ix_xero', 'ix_xero_shipping_account_code', 'sanitize_text_field');

        // Customer settings
        register_setting('ix_xero', 'ix_xero_auto_sync_customers', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'yes'
        ));
    }

    public function section_connection_cb()
    {
        echo '<p>' . esc_html__('Enter your Xero API credentials to connect.', 'ix-woocomm-xero') . '</p>';
    }

    public function section_products_cb()
    {
        echo '<p>' . esc_html__('Configure how products are synchronized with Xero inventory items.', 'ix-woocomm-xero') . '</p>';

        // Add bulk sync button
        $sync_url = wp_nonce_url(
            admin_url('admin-post.php?action=ix_xero_sync_all_products'),
            'ix_xero_sync_all_products'
        );

        echo '<p><a href="' . esc_url($sync_url) . '" class="button button-secondary">' .
            __('Sync All Products Now', 'ix-woocomm-xero') . '</a></p>';

        if (!empty($_GET['synced'])) {
            echo '<div class="notice notice-success"><p>' .
                sprintf(__('Successfully synced %d products with Xero.', 'ix-woocomm-xero'), intval($_GET['synced'])) .
                '</p></div>';
        }
    }

    public function section_invoice_cb()
    {
        echo '<p>' . esc_html__('Configure how invoices are created in Xero.', 'ix-woocomm-xero') . '</p>';
    }

    public function section_customers_cb()
    {
        echo '<p>' . esc_html__('Configure how customers are synchronized with Xero contacts.', 'ix-woocomm-xero') . '</p>';
    }

    public function field_invoice_prefix_cb($args)
    {
        $value = get_option($args['label_for'], $args['default'] ?? '');
        ?>
        <input type="text" id="<?php echo esc_attr($args['label_for']); ?>" name="<?php echo esc_attr($args['label_for']); ?>"
            value="<?php echo esc_attr($value); ?>" class="regular-text">
        <?php if (!empty($args['description'])): ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif;
    }

    public function field_text_cb($args)
    {
        $value = get_option($args['label_for'], $args['default'] ?? '');
        ?>
        <input type="text" id="<?php echo esc_attr($args['label_for']); ?>" name="<?php echo esc_attr($args['label_for']); ?>"
            value="<?php echo esc_attr($value); ?>" class="regular-text">
        <?php if (!empty($args['description'])): ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif;
    }

    public function field_password_cb($args)
    {
        $value = get_option($args['label_for'], $args['default'] ?? '');
        ?>
        <input type="password" id="<?php echo esc_attr($args['label_for']); ?>"
            name="<?php echo esc_attr($args['label_for']); ?>" value="<?php echo esc_attr($value); ?>" class="regular-text">
        <?php if (!empty($args['description'])): ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif;
    }

    public function field_checkbox_cb($args)
    {
        $value = get_option($args['label_for'], $args['default'] ?? '');
        ?>
        <input type="checkbox" id="<?php echo esc_attr($args['label_for']); ?>"
            name="<?php echo esc_attr($args['label_for']); ?>" value="yes" <?php checked('yes', $value); ?>>
        <?php if (!empty($args['description'])): ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif;
    }
}