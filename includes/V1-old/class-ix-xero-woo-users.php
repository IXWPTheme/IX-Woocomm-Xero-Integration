<?php
if (!defined('ABSPATH')) {
    exit;
}

class IX_Xero_Woocommerce_Users {
    private $xero_api;

    public function __construct($xero_api) {
        $this->xero_api = $xero_api;
        $this->init_hooks();
    }

   private function init_hooks() {
        add_action('user_register', array($this, 'sync_user_to_xero'), 10, 1);
        add_action('profile_update', array($this, 'sync_user_to_xero_on_update'), 10, 2);
        add_action('admin_post_ix_xero_sync_customers', array($this, 'sync_customers_admin'));
        add_action('admin_post_ix_xero_sync_contacts_from_xero', array($this, 'sync_contacts_from_xero_admin'));
    }
    public function sync_user_to_xero($user_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        $this->sync_single_user_to_xero($user_id);
    }

    public function sync_user_to_xero_on_update($user_id, $old_user_data) {
        $this->sync_single_user_to_xero($user_id);
    }

    public function sync_single_user_to_xero($user_id) {
        if (!$this->xero_api->is_connected()) return false;
        
        $user = get_userdata($user_id);
        if (!$user) return false;

        try {
            $customer_data = $this->prepare_xero_customer_data($user);
            $existing_customer = $this->xero_api->find_customer_by_email($user->user_email);

            if ($existing_customer) {
                $response = $this->xero_api->update_customer($existing_customer['ContactID'], $customer_data);
                update_user_meta($user_id, '_xero_contact_id', $existing_customer['ContactID']);
            } else {
                $response = $this->xero_api->create_customer($customer_data);
                if (isset($response['Contacts'][0]['ContactID'])) {
                    update_user_meta($user_id, '_xero_contact_id', $response['Contacts'][0]['ContactID']);
                }
            }

            update_user_meta($user_id, '_xero_last_sync', current_time('mysql'));
            do_action('ix_xero_customer_synced', $user_id, $response);
            
            return true;
        } catch (Exception $e) {
            error_log('Xero Customer Sync Error: ' . $e->getMessage());
            do_action('ix_xero_customer_sync_failed', $user_id, $e->getMessage());
            return false;
        }
    }

    private function prepare_xero_customer_data($user) {
        $first_name = get_user_meta($user->ID, 'first_name', true);
        $last_name = get_user_meta($user->ID, 'last_name', true);
        $billing_phone = get_user_meta($user->ID, 'billing_phone', true);
        $billing_address = array(
            'AddressLine1' => get_user_meta($user->ID, 'billing_address_1', true),
            'AddressLine2' => get_user_meta($user->ID, 'billing_address_2', true),
            'City' => get_user_meta($user->ID, 'billing_city', true),
            'Region' => get_user_meta($user->ID, 'billing_state', true),
            'PostalCode' => get_user_meta($user->ID, 'billing_postcode', true),
            'Country' => get_user_meta($user->ID, 'billing_country', true)
        );

        $customer_data = array(
            'Name' => trim($first_name . ' ' . $last_name),
            'FirstName' => $first_name,
            'LastName' => $last_name,
            'EmailAddress' => $user->user_email,
            'IsCustomer' => true,
            'ContactStatus' => 'ACTIVE',
            'Phones' => array(
                array(
                    'PhoneType' => 'DEFAULT',
                    'PhoneNumber' => $billing_phone
                )
            ),
            'Addresses' => array(
                array(
                    'AddressType' => 'STREET',
                    'AddressLine1' => $billing_address['AddressLine1'],
                    'AddressLine2' => $billing_address['AddressLine2'],
                    'City' => $billing_address['City'],
                    'Region' => $billing_address['Region'],
                    'PostalCode' => $billing_address['PostalCode'],
                    'Country' => $billing_address['Country']
                )
            )
        );

        return apply_filters('ix_xero_customer_data', $customer_data, $user);
    }

    public function sync_customers_admin() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'ix-woocomm-xero'));
        }

        check_admin_referer('ix_xero_sync_customers');

        $users = get_users(array(
            'role__in' => array('customer'),
            'fields' => 'ID'
        ));

        $synced = 0;
        foreach ($users as $user_id) {
            if ($this->sync_single_user_to_xero($user_id)) {
                $synced++;
            }
        }

        wp_redirect(add_query_arg(
            array(
                'page' => 'ix-xero-settings',
                'tab' => 'customers',
                'customers_synced' => $synced
            ),
            admin_url('admin.php')
        ));
        exit;
    }
	
	public function sync_contact_from_xero($contact) {
        if (empty($contact['EmailAddress'])) {
            return false;
        }

        $email = sanitize_email($contact['EmailAddress']);
        $user = get_user_by('email', $email);

        if (!$user) {
            // Create new user
            $username = $this->generate_username($contact);
            $password = wp_generate_password();
            
            $user_id = wp_create_user(
                $username,
                $password,
                $email
            );

            if (is_wp_error($user_id)) {
                error_log('Error creating user from Xero contact: ' . $user_id->get_error_message());
                return false;
            }

            $user = get_user_by('id', $user_id);
            
            // Set user role
            $user->set_role('customer');
            
            // Send notification
            wp_send_new_user_notifications($user_id, 'user');
        }

        // Update user meta
        $this->update_user_from_xero_contact($user, $contact);

        return $user->ID;
    }

    private function generate_username($contact) {
        $first_name = sanitize_user($contact['FirstName'] ?? '', true);
        $last_name = sanitize_user($contact['LastName'] ?? '', true);
        $email = sanitize_email($contact['EmailAddress']);
        
        $username = strtolower($first_name . '.' . $last_name);
        $username = preg_replace('/[^a-z0-9_]/', '', $username);
        
        if (empty($username)) {
            $username = strtok($email, '@');
        }
        
        // Ensure username is unique
        $counter = 1;
        $original_username = $username;
        
        while (username_exists($username)) {
            $username = $original_username . $counter;
            $counter++;
        }
        
        return $username;
    }

    private function update_user_from_xero_contact($user, $contact) {
        // Basic info
        update_user_meta($user->ID, 'first_name', sanitize_text_field($contact['FirstName'] ?? ''));
        update_user_meta($user->ID, 'last_name', sanitize_text_field($contact['LastName'] ?? ''));
        
        // Phone numbers
        if (!empty($contact['Phones'])) {
            foreach ($contact['Phones'] as $phone) {
                if ($phone['PhoneType'] === 'DEFAULT') {
                    update_user_meta($user->ID, 'billing_phone', sanitize_text_field($phone['PhoneNumber']));
                    break;
                }
            }
        }
        
        // Addresses
        if (!empty($contact['Addresses'])) {
            foreach ($contact['Addresses'] as $address) {
                if ($address['AddressType'] === 'STREET') {
                    update_user_meta($user->ID, 'billing_address_1', sanitize_text_field($address['AddressLine1'] ?? ''));
                    update_user_meta($user->ID, 'billing_address_2', sanitize_text_field($address['AddressLine2'] ?? ''));
                    update_user_meta($user->ID, 'billing_city', sanitize_text_field($address['City'] ?? ''));
                    update_user_meta($user->ID, 'billing_state', sanitize_text_field($address['Region'] ?? ''));
                    update_user_meta($user->ID, 'billing_postcode', sanitize_text_field($address['PostalCode'] ?? ''));
                    update_user_meta($user->ID, 'billing_country', sanitize_text_field($address['Country'] ?? ''));
                    break;
                }
            }
        }
        
        // Store Xero contact ID
        update_user_meta($user->ID, '_xero_contact_id', sanitize_text_field($contact['ContactID']));
        update_user_meta($user->ID, '_xero_last_sync', current_time('mysql'));
    }

    public function sync_contacts_from_xero_admin() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'ix-woocomm-xero'));
        }

        check_admin_referer('ix_xero_sync_contacts_from_xero');

        try {
            $contacts = $this->xero_api->get_customers();
            $synced = 0;
            
            foreach ($contacts as $contact) {
                if ($this->sync_contact_from_xero($contact)) {
                    $synced++;
                }
            }
            
            wp_redirect(add_query_arg(
                array(
                    'page' => 'ix-xero-settings',
                    'tab' => 'customers',
                    'xero_contacts_synced' => $synced
                ),
                admin_url('admin.php')
            ));
            exit;
            
        } catch (Exception $e) {
            wp_redirect(add_query_arg(
                array(
                    'page' => 'ix-xero-settings',
                    'tab' => 'customers',
                    'sync_error' => urlencode($e->getMessage())
                ),
                admin_url('admin.php')
            ));
            exit;
        }
    }
}