<?php
/**
 * API Settings Template
 *
 * @package IX_Woo_Xero_Integration
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Get current settings
$settings = IX_Helper::get_settings();
$client_id = isset($settings['client_id']) ? $settings['client_id'] : '';
$client_secret = isset($settings['client_secret']) ? $settings['client_secret'] : '';
$redirect_uri = isset($settings['redirect_uri']) ? $settings['redirect_uri'] : '';
$auth_status = get_option('ix_woo_xero_connection_status', 'not_connected');
$auth_button_text = ('connected' === $auth_status) ? __('Reconnect to Xero', 'ix-woo-xero') : __('Connect to Xero', 'ix-woo-xero');
?>

<div class="ix-settings-section">
    <h2><?php esc_html_e('Xero API Settings', 'ix-woo-xero'); ?></h2>
    
    <table class="form-table">
        <tbody>
            <!-- Connection Status -->
            <tr>
                <th scope="row">
                    <?php esc_html_e('Connection Status', 'ix-woo-xero'); ?>
                </th>
                <td>
                    <?php if ('connected' === $auth_status) : ?>
                        <span class="ix-status-connected">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php esc_html_e('Connected to Xero', 'ix-woo-xero'); ?>
                        </span>
                    <?php else : ?>
                        <span class="ix-status-disconnected">
                            <span class="dashicons dashicons-no-alt"></span>
                            <?php esc_html_e('Not Connected', 'ix-woo-xero'); ?>
                        </span>
                    <?php endif; ?>
                </td>
            </tr>
            
            <!-- Client ID -->
            <tr>
                <th scope="row">
                    <label for="ix_client_id">
                        <?php esc_html_e('Client ID', 'ix-woo-xero'); ?>
                    </label>
                </th>
                <td>
                    <input type="text" 
                           id="ix_client_id" 
                           name="ix_woo_xero_settings[client_id]" 
                           value="<?php echo esc_attr($client_id); ?>" 
                           class="regular-text"
                           required>
                    <p class="description">
                        <?php esc_html_e('Your Xero App Client ID from the developer portal.', 'ix-woo-xero'); ?>
                    </p>
                </td>
            </tr>
            
            <!-- Client Secret -->
            <tr>
                <th scope="row">
                    <label for="ix_client_secret">
                        <?php esc_html_e('Client Secret', 'ix-woo-xero'); ?>
                    </label>
                </th>
                <td>
                    <input type="password" 
                           id="ix_client_secret" 
                           name="ix_woo_xero_settings[client_secret]" 
                           value="<?php echo esc_attr($client_secret); ?>" 
                           class="regular-text"
                           required>
                    <p class="description">
                        <?php esc_html_e('Your Xero App Client Secret from the developer portal.', 'ix-woo-xero'); ?>
                    </p>
                </td>
            </tr>
            
            <!-- Redirect URI -->
            <tr>
                <th scope="row">
                    <label for="ix_redirect_uri">
                        <?php esc_html_e('Redirect URI', 'ix-woo-xero'); ?>
                    </label>
                </th>
                <td>
                    <input type="url" 
                           id="ix_redirect_uri" 
                           name="ix_woo_xero_settings[redirect_uri]" 
                           value="<?php echo esc_attr($redirect_uri); ?>" 
                           class="regular-text"
                           readonly
                           onclick="this.select()">
                    <p class="description">
                        <?php esc_html_e('Copy this to your Xero app configuration in the developer portal.', 'ix-woo-xero'); ?>
                    </p>
                </td>
            </tr>
            
            <!-- Auth Button -->
            <tr>
                <th scope="row"></th>
                <td>
                    <?php if (!empty($client_id) && !empty($client_secret)) : ?>
                        <a href="<?php echo esc_url(IX_Xero_API::get_auth_url()); ?>" class="button button-primary">
                            <?php echo esc_html($auth_button_text); ?>
                        </a>
                        <?php if ('connected' === $auth_status) : ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=ix-woo-xero&action=disconnect')); ?>" class="button">
                                <?php esc_html_e('Disconnect', 'ix-woo-xero'); ?>
                            </a>
                        <?php endif; ?>
                    <?php else : ?>
                        <button type="button" class="button" disabled>
                            <?php esc_html_e('Enter Client ID and Secret to connect', 'ix-woo-xero'); ?>
                        </button>
                    <?php endif; ?>
                </td>
            </tr>
            
            <!-- API Mode -->
            <tr>
                <th scope="row">
                    <label for="ix_api_mode">
                        <?php esc_html_e('API Mode', 'ix-woo-xero'); ?>
                    </label>
                </th>
                <td>
                    <select id="ix_api_mode" name="ix_woo_xero_settings[api_mode]" class="regular-text">
                        <option value="live" <?php selected(isset($settings['api_mode']) ? $settings['api_mode'] : '', 'live'); ?>>
                            <?php esc_html_e('Live - Production Data', 'ix-woo-xero'); ?>
                        </option>
                        <option value="test" <?php selected(isset($settings['api_mode']) ? $settings['api_mode'] : '', 'test'); ?>>
                            <?php esc_html_e('Test - Demo Company', 'ix-woo-xero'); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php esc_html_e('Use "Test" mode for development and testing with Xero demo company.', 'ix-woo-xero'); ?>
                    </p>
                </td>
            </tr>
            
            <!-- Debug Mode -->
            <tr>
                <th scope="row">
                    <label for="ix_debug_mode">
                        <?php esc_html_e('Debug Mode', 'ix-woo-xero'); ?>
                    </label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                               id="ix_debug_mode" 
                               name="ix_woo_xero_settings[debug_mode]" 
                               value="1" 
                               <?php checked(isset($settings['debug_mode']) ? $settings['debug_mode'] : '', 1); ?>>
                        <?php esc_html_e('Enable debug logging', 'ix-woo-xero'); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e('Log API requests and responses for troubleshooting.', 'ix-woo-xero'); ?>
                        <?php if (isset($settings['debug_mode']) && $settings['debug_mode']) : ?>
                            <br>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=ix-woo-xero-status&tab=logs')); ?>">
                                <?php esc_html_e('View logs', 'ix-woo-xero'); ?>
                            </a>
                        <?php endif; ?>
                    </p>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<style>
.ix-status-connected {
    color: #46b450;
    font-weight: 600;
}

.ix-status-disconnected {
    color: #dc3232;
    font-weight: 600;
}

.ix-status-connected .dashicons,
.ix-status-disconnected .dashicons {
    font-size: 18px;
    vertical-align: middle;
    margin-right: 5px;
}
</style>