<?php
/**
 * IX Woo Xero Integration - Xero API Class
 * 
 * Handles all communication with the Xero API including:
 * - OAuth 2.0 authentication
 * - API request handling
 * - Token management
 * - Rate limiting
 */

namespace IX_Woo_Xero\API;

use Exception;
use IX_Woo_Xero\Utilities\Logger;

if (!defined('ABSPATH')) {
    exit;
}

class Xero_API {

    /**
     * The single instance of the class
     *
     * @var Xero_API
     */
    private static $_instance = null;

    /**
     * API endpoints
     *
     * @var array
     */
    private $endpoints = [
        'auth'          => 'https://identity.xero.com/connect/token',
        'authorize'     => 'https://login.xero.com/identity/connect/authorize',
        'connections'   => 'https://api.xero.com/connections',
        'accounts'      => 'https://api.xero.com/api.xro/2.0/Accounts',
        'contacts'      => 'https://api.xero.com/api.xro/2.0/Contacts',
        'invoices'      => 'https://api.xero.com/api.xro/2.0/Invoices',
        'items'         => 'https://api.xero.com/api.xro/2.0/Items',
        'taxrates'      => 'https://api.xero.com/api.xro/2.0/TaxRates',
        'tracking'      => 'https://api.xero.com/api.xro/2.0/TrackingCategories',
        'webhooks'      => 'https://api.xero.com/api.xro/2.0/Webhooks'
    ];

    /**
     * API credentials
     *
     * @var array
     */
    private $credentials = [
        'client_id'     => '',
        'client_secret' => '',
        'redirect_uri'  => ''
    ];

    /**
     * Token data
     *
     * @var array
     */
    private $tokens = [
        'access_token'  => '',
        'refresh_token' => '',
        'expires_in'    => 0,
        'tenant_id'     => ''
    ];

    /**
     * Main Xero_API Instance
     *
     * @return Xero_API
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
        $this->init_credentials();
        $this->init_tokens();
    }

    /**
     * Initialize API credentials
     */
    private function init_credentials() {
        $this->credentials = [
            'client_id'     => get_option('ix_woo_xero_client_id', ''),
            'client_secret' => get_option('ix_woo_xero_client_secret', ''),
            'redirect_uri'  => admin_url('admin.php?page=ix-woo-xero')
        ];
    }

    /**
     * Initialize token data
     */
    private function init_tokens() {
        $this->tokens = [
            'access_token'  => get_option('ix_woo_xero_access_token', ''),
            'refresh_token' => get_option('ix_woo_xero_refresh_token', ''),
            'expires_in'    => get_option('ix_woo_xero_token_expires', 0),
            'tenant_id'     => get_option('ix_woo_xero_tenant_id', '')
        ];
    }

    /**
     * Get authorization URL
     */
    public function get_auth_url() {
        $args = [
            'response_type' => 'code',
            'client_id'     => $this->credentials['client_id'],
            'redirect_uri'  => $this->credentials['redirect_uri'],
            'scope'         => 'openid profile email accounting.transactions accounting.contacts offline_access',
            'state'         => wp_create_nonce('xero_auth_state')
        ];

        return add_query_arg($args, $this->endpoints['authorize']);
    }

    /**
     * Handle authorization callback
     */
    public function handle_auth_callback($code) {
        try {
            $tokens = $this->request_tokens($code);
            
            if (!empty($tokens['access_token'])) {
                $this->update_tokens($tokens);
                $this->set_tenant_id();
                return true;
            }
        } catch (Exception $e) {
            Logger::error('Xero API Auth Error: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Request tokens with authorization code
     */
    private function request_tokens($code) {
        $response = wp_remote_post($this->endpoints['auth'], [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->credentials['client_id'] . ':' . $this->credentials['client_secret']),
                'Content-Type'  => 'application/x-www-form-urlencoded'
            ],
            'body' => [
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'redirect_uri' => $this->credentials['redirect_uri']
            ]
        ]);

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            throw new Exception($body['error']);
        }

        return $body;
    }

    /**
     * Refresh access token
     */
    public function refresh_token() {
        try {
            $tokens = $this->request_refresh();
            
            if (!empty($tokens['access_token'])) {
                $this->update_tokens($tokens);
                return true;
            }
        } catch (Exception $e) {
            Logger::error('Xero API Refresh Error: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Request new tokens with refresh token
     */
    private function request_refresh() {
        $response = wp_remote_post($this->endpoints['auth'], [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->credentials['client_id'] . ':' . $this->credentials['client_secret']),
                'Content-Type'  => 'application/x-www-form-urlencoded'
            ],
            'body' => [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $this->tokens['refresh_token']
            ]
        ]);

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            throw new Exception($body['error']);
        }

        return $body;
    }

    /**
     * Update stored tokens
     */
    private function update_tokens($tokens) {
        $this->tokens = [
            'access_token'  => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? $this->tokens['refresh_token'],
            'expires_in'    => time() + $tokens['expires_in']
        ];

        update_option('ix_woo_xero_access_token', $this->tokens['access_token']);
        update_option('ix_woo_xero_refresh_token', $this->tokens['refresh_token']);
        update_option('ix_woo_xero_token_expires', $this->tokens['expires_in']);
    }

    /**
     * Set tenant ID
     */
    private function set_tenant_id() {
        $tenants = $this->make_request('GET', $this->endpoints['connections']);
        
        if (!empty($tenants[0]['tenantId'])) {
            $this->tokens['tenant_id'] = $tenants[0]['tenantId'];
            update_option('ix_woo_xero_tenant_id', $this->tokens['tenant_id']);
        }
    }

    /**
     * Make API request
     */
    public function make_request($method, $endpoint, $data = []) {
        // Check if token needs refreshing
        if (time() >= $this->tokens['expires_in']) {
            if (!$this->refresh_token()) {
                throw new Exception(__('Failed to refresh access token', 'ix-woo-xero'));
            }
        }

        $args = [
            'method'  => $method,
            'headers' => [
                'Authorization'     => 'Bearer ' . $this->tokens['access_token'],
                'Xero-tenant-id'   => $this->tokens['tenant_id'],
                'Content-Type'     => 'application/json',
                'Accept'           => 'application/json'
            ],
            'timeout' => 30
        ];

        if (!empty($data)) {
            $args['body'] = json_encode($data);
        }

        $response = wp_remote_request($endpoint, $args);

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        // Handle rate limiting
        if (wp_remote_retrieve_response_code($response) === 429) {
            $retry_after = wp_remote_retrieve_header($response, 'retry-after') ?: 60;
            sleep(min($retry_after, 60));
            return $this->make_request($method, $endpoint, $data);
        }

        if (isset($body['error'])) {
            throw new Exception($body['error']);
        }

        return $body;
    }

    /**
     * Get Xero accounts
     */
    public function get_accounts() {
        try {
            $response = $this->make_request('GET', $this->endpoints['accounts']);
            return $response['Accounts'] ?? [];
        } catch (Exception $e) {
            Logger::error('Xero API Accounts Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get Xero tax rates
     */
    public function get_tax_rates() {
        try {
            $response = $this->make_request('GET', $this->endpoints['taxrates']);
            return $response['TaxRates'] ?? [];
        } catch (Exception $e) {
            Logger::error('Xero API Tax Rates Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get Xero tracking categories
     */
    public function get_tracking_categories() {
        try {
            $response = $this->make_request('GET', $this->endpoints['tracking']);
            return $response['TrackingCategories'] ?? [];
        } catch (Exception $e) {
            Logger::error('Xero API Tracking Categories Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Create contact in Xero
     */
    public function create_contact($contact_data) {
        try {
            $response = $this->make_request('PUT', $this->endpoints['contacts'], [
                'Contacts' => [$contact_data]
            ]);
            return $response['Contacts'][0] ?? false;
        } catch (Exception $e) {
            Logger::error('Xero API Create Contact Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update contact in Xero
     */
    public function update_contact($contact_id, $contact_data) {
        try {
            $response = $this->make_request('POST', $this->endpoints['contacts'] . '/' . $contact_id, [
                'Contacts' => [$contact_data]
            ]);
            return $response['Contacts'][0] ?? false;
        } catch (Exception $e) {
            Logger::error('Xero API Update Contact Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create invoice in Xero
     */
    public function create_invoice($invoice_data) {
        try {
            $response = $this->make_request('PUT', $this->endpoints['invoices'], [
                'Invoices' => [$invoice_data]
            ]);
            return $response['Invoices'][0] ?? false;
        } catch (Exception $e) {
            Logger::error('Xero API Create Invoice Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create item in Xero
     */
    public function create_item($item_data) {
        try {
            $response = $this->make_request('PUT', $this->endpoints['items'], [
                'Items' => [$item_data]
            ]);
            return $response['Items'][0] ?? false;
        } catch (Exception $e) {
            Logger::error('Xero API Create Item Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update item in Xero
     */
    public function update_item($item_id, $item_data) {
        try {
            $response = $this->make_request('POST', $this->endpoints['items'] . '/' . $item_id, [
                'Items' => [$item_data]
            ]);
            return $response['Items'][0] ?? false;
        } catch (Exception $e) {
            Logger::error('Xero API Update Item Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if connected to Xero
     */
    public function is_connected() {
        return !empty($this->tokens['access_token']) && !empty($this->tokens['tenant_id']);
    }

    /**
     * Disconnect from Xero
     */
    public function disconnect() {
        // Revoke tokens if possible
        if (!empty($this->tokens['access_token'])) {
            try {
                wp_remote_post($this->endpoints['auth'] . '/revocation', [
                    'headers' => [
                        'Authorization' => 'Basic ' . base64_encode($this->credentials['client_id'] . ':' . $this->credentials['client_secret']),
                        'Content-Type'  => 'application/x-www-form-urlencoded'
                    ],
                    'body' => [
                        'token' => $this->tokens['access_token']
                    ]
                ]);
            } catch (Exception $e) {
                Logger::error('Xero API Revocation Error: ' . $e->getMessage());
            }
        }

        // Clear stored tokens
        delete_option('ix_woo_xero_access_token');
        delete_option('ix_woo_xero_refresh_token');
        delete_option('ix_woo_xero_token_expires');
        delete_option('ix_woo_xero_tenant_id');

        // Reset instance
        $this->init_tokens();
    }

    /**
     * Get tenant name
     */
    public function get_tenant_name() {
        try {
            $tenants = $this->make_request('GET', $this->endpoints['connections']);
            return $tenants[0]['tenantName'] ?? __('Unknown', 'ix-woo-xero');
        } catch (Exception $e) {
            Logger::error('Xero API Tenant Name Error: ' . $e->getMessage());
            return __('Unknown', 'ix-woo-xero');
        }
    }
}

Xero_API::instance();