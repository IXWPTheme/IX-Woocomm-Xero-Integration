<?php
if (!defined('ABSPATH')) {
    exit;
}

class IX_Xero_API {
	
    protected $client_id;
    protected $client_secret;
    protected $tenant_id;
    protected $access_token;
    protected $refresh_token;
    protected $token_expires;
	
    protected $api_url = 'https://api.xero.com/api.xro/2.0/';

    public function __construct() 
	{
        $this->client_id = get_option('ix_xero_client_id');
        $this->client_secret = get_option('ix_xero_client_secret');
        $this->tenant_id = get_option('ix_xero_tenant_id');
        $this->access_token = get_option('ix_xero_access_token');
        $this->refresh_token = get_option('ix_xero_refresh_token');
        $this->token_expires = get_option('ix_xero_token_expires');
    }

    public function is_connected() {
        return !empty($this->access_token) && !empty($this->refresh_token);
    }

    public function get_auth_url() {
        $redirect_uri = admin_url('admin.php?page=ix-xero-settings');
        
        $params = array(
            'response_type' => 'code',
            'client_id' => $this->client_id,
            'redirect_uri' => $redirect_uri,
            'scope' => 'openid profile email accounting.transactions offline_access',
            'state' => wp_create_nonce('xero_auth_state')
        );
        
        return 'https://login.xero.com/identity/connect/authorize?' . http_build_query($params);
    }

    public function exchange_code_for_token($code) {
        $response = wp_remote_post('https://identity.xero.com/connect/token', array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->client_id . ':' . $this->client_secret),
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'body' => array(
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => admin_url('admin.php?page=ix-xero-settings')
            )
        ));
        
        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            throw new Exception($body['error_description']);
        }
        
        $this->access_token = $body['access_token'];
        $this->refresh_token = $body['refresh_token'];
        $this->token_expires = time() + $body['expires_in'];
        
        update_option('ix_xero_access_token', $this->access_token);
        update_option('ix_xero_refresh_token', $this->refresh_token);
        update_option('ix_xero_token_expires', $this->token_expires);
        
        $this->get_tenants();
        
        return true;
    }

    public function refresh_token() {
        $response = wp_remote_post('https://identity.xero.com/connect/token', array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->client_id . ':' . $this->client_secret),
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'body' => array(
                'grant_type' => 'refresh_token',
                'refresh_token' => $this->refresh_token
            )
        ));
        
        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            throw new Exception($body['error_description']);
        }
        
        $this->access_token = $body['access_token'];
        $this->refresh_token = $body['refresh_token'];
        $this->token_expires = time() + $body['expires_in'];
        
        update_option('ix_xero_access_token', $this->access_token);
        update_option('ix_xero_refresh_token', $this->refresh_token);
        update_option('ix_xero_token_expires', $this->token_expires);
        
        return true;
    }

    protected function get_tenants() {
        $response = wp_remote_get('https://api.xero.com/connections', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token
            )
        ));
        
        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }
        
        $tenants = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!empty($tenants[0]['tenantId'])) {
            $this->tenant_id = $tenants[0]['tenantId'];
            update_option('ix_xero_tenant_id', $this->tenant_id);
            return $this->tenant_id;
        }
        
        throw new Exception('No tenants found');
    }

    protected function maybe_refresh_token() {
        if (time() > ($this->token_expires - 300)) {
            $this->refresh_token();
        }
    }

    public function disconnect() {
        delete_option('ix_xero_access_token');
        delete_option('ix_xero_refresh_token');
        delete_option('ix_xero_token_expires');
        delete_option('ix_xero_tenant_id');
        
        $this->access_token = null;
        $this->refresh_token = null;
        $this->token_expires = null;
        $this->tenant_id = null;
    }

    protected function log($message) {
        if (WP_DEBUG === true) {
            error_log('[Xero Integration] ' . $message);
        }
    }

    protected function make_api_request($endpoint, $method = 'GET', $data = array()) {
        $this->maybe_refresh_token();
        
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
                'Xero-tenant-id' => $this->tenant_id,
                'Accept' => 'application/json'
            ),
            'method' => $method
        );
        
        if ($method !== 'GET') {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = json_encode($data);
        }
        
        $response = wp_remote_request($this->api_url . $endpoint, $args);
        
        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['ErrorNumber'])) {
            throw new Exception($body['Message']);
        }
        
        return $body;
    }
}