<?php
/**
 * IX Woo Xero Integration - Webhooks Handler (Premium)
 * 
 * Handles all webhook-related functionality including:
 * - Webhook registration
 * - Payload validation
 * - Event processing
 */

namespace IX_Woo_Xero\Premium;

use IX_Woo_Xero\API\Xero_API;
use IX_Woo_Xero\Utilities\Logger;

if (!defined('ABSPATH')) {
    exit;
}

class Webhooks_Handler {

    /**
     * The single instance of the class
     *
     * @var Webhooks_Handler
     */
    private static $_instance = null;

    /**
     * Xero API instance
     *
     * @var Xero_API
     */
    private $xero_api;

    /**
     * Main Webhooks_Handler Instance
     *
     * @return Webhooks_Handler
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
        // Register webhook endpoint
        add_action('init', [$this, 'register_webhook_endpoint']);
        add_action('parse_request', [$this, 'handle_webhook_request']);

        // Register webhook when settings are saved
        add_action('update_option_ix_woo_xero_webhook_key', [$this, 'register_xero_webhook'], 10, 2);

        // Add admin settings
        add_action('admin_init', [$this, 'register_webhook_settings']);
    }

    /**
     * Register webhook endpoint
     */
    public function register_webhook_endpoint() {
        add_rewrite_rule('^ix-xero-webhook/?$', 'index.php?ix_xero_webhook=1', 'top');
        add_rewrite_tag('%ix_xero_webhook%', '([^&]+)');
    }

    /**
     * Handle incoming webhook request
     */
    public function handle_webhook_request() {
        if (!get_query_var('ix_xero_webhook')) {
            return;
        }

        try {
            // Verify request method
            if ('POST' !== $_SERVER['REQUEST_METHOD']) {
                throw new \Exception('Invalid request method');
            }

            // Verify signature
            $signature = isset($_SERVER['HTTP_X_XERO_SIGNATURE']) ? $_SERVER['HTTP_X_XERO_SIGNATURE'] : '';
            $payload = file_get_contents('php://input');
            $expected_signature = hash_hmac('sha256', $payload, get_option('ix_woo_xero_webhook_key'));

            if (!hash_equals($expected_signature, $signature)) {
                throw new \Exception('Invalid webhook signature');
            }

            // Process payload
            $data = json_decode($payload, true);

            if (empty($data['events'])) {
                status_header(200);
                exit;
            }

            foreach ($data['events'] as $event) {
                $this->process_webhook_event($event);
            }

            status_header(200);
            exit;
        } catch (\Exception $e) {
            Logger::error('Webhook Error: ' . $e->getMessage());
            status_header(401);
            exit;
        }
    }

    /**
     * Process webhook event
     */
    private function process_webhook_event($event) {
        $resource_type = $event['eventCategory'];
        $resource_id = $event['resourceId'];
        $event_type = $event['eventType'];

        Logger::info("Webhook Event: {$resource_type} {$event_type}");

        switch ($resource_type) {
            case 'CONTACT':
                $this->handle_contact_event($resource_id, $event_type);
                break;
            case 'INVOICE':
                $this->handle_invoice_event($resource_id, $event_type);
                break;
            case 'ITEM':
                $this->handle_item_event($resource_id, $event_type);
                break;
            default:
                Logger::info("Unhandled webhook resource type: {$resource_type}");
        }
    }

    /**
     * Handle contact events
     */
    private function handle_contact_event($contact_id, $event_type) {
        try {
            $contact = $this->xero_api->get_contact($contact_id);
            $user_id = $this->get_user_id_by_xero_contact_id($contact_id);

            if (!$user_id) {
                Logger::info("No local user found for Xero contact: {$contact_id}");
                return;
            }

            switch ($event_type) {
                case 'UPDATE':
                    $this->update_local_contact($user_id, $contact);
                    break;
                case 'CREATE':
                    // No action needed for new contacts
                    break;
                case 'DELETE':
                    delete_user_meta($user_id, '_xero_contact_id');
                    break;
            }
        } catch (\Exception $e) {
            Logger::error("Contact webhook error: " . $e->getMessage());
        }
    }

    /**
     * Handle invoice events
     */
    private function handle_invoice_event($invoice_id, $event_type) {
        try {
            $invoice = $this->xero_api->get_invoice($invoice_id);
            $order_id = $this->get_order_id_by_xero_invoice_id($invoice_id);

            if (!$order_id) {
                Logger::info("No local order found for Xero invoice: {$invoice_id}");
                return;
            }

            switch ($event_type) {
                case 'UPDATE':
                    $this->update_local_invoice($order_id, $invoice);
                    break;
                case 'CREATE':
                    // No action needed for new invoices
                    break;
                case 'DELETE':
                    delete_post_meta($order_id, '_xero_invoice_id');
                    break;
            }
        } catch (\Exception $e) {
            Logger::error("Invoice webhook error: " . $e->getMessage());
        }
    }

    /**
     * Handle item events
     */
    private function handle_item_event($item_id, $event_type) {
        try {
            $item = $this->xero_api->get_item($item_id);
            $product_id = $this->get_product_id_by_xero_item_id($item_id);

            if (!$product_id) {
                Logger::info("No local product found for Xero item: {$item_id}");
                return;
            }

            switch ($event_type) {
                case 'UPDATE':
                    $this->update_local_product($product_id, $item);
                    break;
                case 'CREATE':
                    // No action needed for new items
                    break;
                case 'DELETE':
                    delete_post_meta($product_id, '_xero_item_id');
                    break;
            }
        } catch (\Exception $e) {
            Logger::error("Item webhook error: " . $e->getMessage());
        }
    }

    /**
     * Update local contact data
     */
    private function update_local_contact($user_id, $contact) {
        update_user_meta($user_id, 'billing_first_name', $contact['FirstName']);
        update_user_meta($user_id, 'billing_last_name', $contact['LastName']);
        update_user_meta($user_id, 'billing_email', $contact['EmailAddress']);

        if (!empty($contact['Addresses'][0])) {
            $address = $contact['Addresses'][0];
            update_user_meta($user_id, 'billing_address_1', $address['AddressLine1']);
            update_user_meta($user_id, 'billing_address_2', $address['AddressLine2']);
            update_user_meta($user_id, 'billing_city', $address['City']);
            update_user_meta($user_id, 'billing_state', $address['Region']);
            update_user_meta($user_id, 'billing_postcode', $address['PostalCode']);
            update_user_meta($user_id, 'billing_country', $address['Country']);
        }

        if (!empty($contact['Phones'][0])) {
            update_user_meta($user_id, 'billing_phone', $contact['Phones'][0]['PhoneNumber']);
        }

        Logger::info("Updated local contact data for user: {$user_id}");
    }

    /**
     * Update local invoice data
     */
    private function update_local_invoice($order_id, $invoice) {
        $order = wc_get_order($order_id);

        if (!$order) {
            throw new \Exception("Order not found: {$order_id}");
        }

        switch ($invoice['Status']) {
            case 'PAID':
                $order->update_status('completed');
                break;
            case 'VOIDED':
                $order->update_status('cancelled');
                break;
        }

        Logger::info("Updated local order status for order: {$order_id}");
    }

    /**
     * Update local product data
     */
    private function update_local_product($product_id, $item) {
        $product = wc_get_product($product_id);

        if (!$product) {
            throw new \Exception("Product not found: {$product_id}");
        }

        $product->set_name($item['Name']);
        $product->set_description($item['Description']);

        if (isset($item['SalesDetails']['UnitPrice'])) {
            $product->set_price($item['SalesDetails']['UnitPrice']);
            $product->set_regular_price($item['SalesDetails']['UnitPrice']);
        }

        $product->save();

        Logger::info("Updated local product data for product: {$product_id}");
    }

    /**
     * Register webhook with Xero
     */
    public function register_xero_webhook($old_value, $new_value) {
        if (empty($new_value)) {
            return;
        }

        $webhook_url = home_url('/ix-xero-webhook');

        try {
            $response = $this->xero_api->make_request('POST', $this->xero_api->get_endpoint('webhooks'), [
                'webhookUrl' => $webhook_url,
                'events' => ['INVOICE', 'CONTACT', 'ITEM'],
                'status' => 'ACTIVE'
            ]);

            if ($response && isset($response['id'])) {
                update_option('ix_woo_xero_webhook_id', $response['id']);
                Logger::info('Webhook registered successfully with Xero');
            }
        } catch (\Exception $e) {
            Logger::error('Webhook registration error: ' . $e->getMessage());
        }
    }

    /**
     * Register webhook settings
     */
    public function register_webhook_settings() {
        register_setting(
            'ix_woo_xero_api_settings',
            'ix_woo_xero_webhook_key',
            'sanitize_text_field'
        );

        add_settings_field(
            'ix_woo_xero_webhook_key',
            __('Webhook Signing Key', 'ix-woo-xero'),
            [$this, 'render_webhook_key_field'],
            'ix_woo_xero_api_settings',
            'ix_woo_xero_api_credentials'
        );
    }

    /**
     * Render webhook key field
     */
    public function render_webhook_key_field() {
        $value = get_option('ix_woo_xero_webhook_key', '');
        $webhook_url = home_url('/ix-xero-webhook');
        ?>
        <input type="text" id="ix_woo_xero_webhook_key" 
               name="ix_woo_xero_webhook_key" 
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
     * Get user ID by Xero contact ID
     */
    private function get_user_id_by_xero_contact_id($contact_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '_xero_contact_id' AND meta_value = %s",
            $contact_id
        ));
    }

    /**
     * Get order ID by Xero invoice ID
     */
    private function get_order_id_by_xero_invoice_id($invoice_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_xero_invoice_id' AND meta_value = %s",
            $invoice_id
        ));
    }

    /**
     * Get product ID by Xero item ID
     */
    private function get_product_id_by_xero_item_id($item_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_xero_item_id' AND meta_value = %s",
            $item_id
        ));
    }
}

Webhooks_Handler::instance();