<?php
class IX_Customer_Sync {
    
    private static $instance = null;
    private $xero_api;
    
    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->xero_api = IX_Xero_API::get_instance();
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Sync customer on registration
        add_action('user_register', array($this, 'sync_customer_to_xero'), 10, 1);
        
        // Sync customer on profile update
        add_action('profile_update', array($this, 'sync_customer_to_xero'), 10, 2);
        
        // Sync customer on order
        add_action('woocommerce_new_order', array($this, 'sync_order_customer_to_xero'), 10, 2);
    }
    
    public function sync_customer_to_xero($user_id, $old_user_data = null) {
        $user = get_userdata($user_id);
        
        // Only sync customers
        if (!in_array('customer', $user->roles)) {
            return;
        }
        
        $xero_contact_id = get_user_meta($user_id, '_xero_contact_id', true);
        $data = $this->prepare_customer_data($user);
        
        if ($xero_contact_id) {
            // Update existing contact in Xero
            $response = $this->xero_api->make_api_request(
                'https://api.xero.com/api.xro/2.0/Contacts/' . $xero_contact_id,
                'POST',
                ['Contacts' => [$data]]
            );
        } else {
            // Create new contact in Xero
            $response = $this->xero_api->make_api_request(
                'https://api.xero.com/api.xro/2.0/Contacts',
                'PUT',
                ['Contacts' => [$data]]
            );
            
            if ($response && isset($response['Contacts'][0]['ContactID'])) {
                update_user_meta($user_id, '_xero_contact_id', $response['Contacts'][0]['ContactID']);
            }
        }
        
        if ($response === false) {
            IX_Logger::log('Failed to sync customer ID: ' . $user_id);
        }
    }
    
    public function sync_order_customer_to_xero($order_id, $order) {
        if ($order->get_customer_id()) {
            $this->sync_customer_to_xero($order->get_customer_id());
        } else {
            // Handle guest checkout
            $this->sync_guest_customer_to_xero($order);
        }
    }
    
    private function sync_guest_customer_to_xero($order) {
        $email = $order->get_billing_email();
        $xero_contact_id = $order->get_meta('_xero_contact_id');
        
        // Check if guest customer already exists in Xero
        if (!$xero_contact_id) {
            $response = $this->xero_api->make_api_request(
                'https://api.xero.com/api.xro/2.0/Contacts?where=EmailAddress=="' . urlencode($email) . '"',
                'GET'
            );
            
            if ($response && isset($response['Contacts'][0]['ContactID'])) {
                $xero_contact_id = $response['Contacts'][0]['ContactID'];
                $order->update_meta_data('_xero_contact_id', $xero_contact_id);
                $order->save();
                return;
            }
        }
        
        // Create new contact for guest
        $data = [
            'Name' => $order->get_formatted_billing_full_name(),
            'FirstName' => $order->get_billing_first_name(),
            'LastName' => $order->get_billing_last_name(),
            'EmailAddress' => $email,
            'Addresses' => [
                [
                    'AddressType' => 'STREET',
                    'AddressLine1' => $order->get_billing_address_1(),
                    'AddressLine2' => $order->get_billing_address_2(),
                    'City' => $order->get_billing_city(),
                    'Region' => $order->get_billing_state(),
                    'PostalCode' => $order->get_billing_postcode(),
                    'Country' => $order->get_billing_country()
                ]
            ],
            'Phones' => [
                [
                    'PhoneType' => 'DEFAULT',
                    'PhoneNumber' => $order->get_billing_phone()
                ]
            ],
            'IsCustomer' => true
        ];
        
        $response = $this->xero_api->make_api_request(
            'https://api.xero.com/api.xro/2.0/Contacts',
            'PUT',
            ['Contacts' => [$data]]
        );
        
        if ($response && isset($response['Contacts'][0]['ContactID'])) {
            $order->update_meta_data('_xero_contact_id', $response['Contacts'][0]['ContactID']);
            $order->save();
        } else {
            IX_Logger::log('Failed to sync guest customer for order ID: ' . $order->get_id());
        }
    }
    
    private function prepare_customer_data($user) {
        $first_name = get_user_meta($user->ID, 'billing_first_name', true) ?: $user->first_name;
        $last_name = get_user_meta($user->ID, 'billing_last_name', true) ?: $user->last_name;
        $email = get_user_meta($user->ID, 'billing_email', true) ?: $user->user_email;
        
        $data = [
            'Name' => trim($first_name . ' ' . $last_name),
            'FirstName' => $first_name,
            'LastName' => $last_name,
            'EmailAddress' => $email,
            'IsCustomer' => true
        ];
        
        // Add address if available
        $address1 = get_user_meta($user->ID, 'billing_address_1', true);
        if ($address1) {
            $data['Addresses'] = [
                [
                    'AddressType' => 'STREET',
                    'AddressLine1' => $address1,
                    'AddressLine2' => get_user_meta($user->ID, 'billing_address_2', true),
                    'City' => get_user_meta($user->ID, 'billing_city', true),
                    'Region' => get_user_meta($user->ID, 'billing_state', true),
                    'PostalCode' => get_user_meta($user->ID, 'billing_postcode', true),
                    'Country' => get_user_meta($user->ID, 'billing_country', true)
                ]
            ];
        }
        
        // Add phone if available
        $phone = get_user_meta($user->ID, 'billing_phone', true);
        if ($phone) {
            $data['Phones'] = [
                [
                    'PhoneType' => 'DEFAULT',
                    'PhoneNumber' => $phone
                ]
            ];
        }
        
        return $data;
    }
}