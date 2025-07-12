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
	   
	   // Add user list columns for Xero sync status
        add_filter('manage_users_columns', array($this, 'add_xero_sync_column'));
        add_filter('manage_users_custom_column', array($this, 'show_xero_sync_status'), 10, 3);
    }
	
	// NEW METHOD: Add Xero Sync column to users list
    public function add_xero_sync_column($columns) {
        $columns['xero_sync'] = __('Xero Sync', 'ix-woocomm-xero');
        return $columns;
    }

    // NEW METHOD: Show sync status in users list
    public function show_xero_sync_status($value, $column_name, $user_id) {
        if ($column_name === 'xero_sync') {
            $last_sync = get_user_meta($user_id, '_xero_last_sync', true);
            $error = get_user_meta($user_id, '_xero_sync_error', true);
            
            if ($error) {
                return '<span style="color:#dc3232;">' . __('Failed', 'ix-woocomm-xero') . '</span>';
            } elseif ($last_sync) {
                return '<span style="color:#46b450;">' . __('Synced', 'ix-woocomm-xero') . '</span>';
            } else {
                return '<span style="color:#72777c;">' . __('Not synced', 'ix-woocomm-xero') . '</span>';
            }
        }
        return $value;
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
			} else {
				$response = $this->xero_api->create_customer($customer_data);
			}

			// Check for validation errors in the response
			if (!empty($response['Elements'][0]['ValidationErrors'])) {
				$error_messages = array_map(function($error) {
					return $error['Message'];
				}, $response['Elements'][0]['ValidationErrors']);

				throw new Exception(implode(', ', $error_messages));
			}

			// Success case
			if ($existing_customer) {
				update_user_meta($user_id, '_xero_contact_id', $existing_customer['ContactID']);
			} elseif (isset($response['Contacts'][0]['ContactID'])) {
				update_user_meta($user_id, '_xero_contact_id', $response['Contacts'][0]['ContactID']);
			}

			update_user_meta($user_id, '_xero_last_sync', current_time('mysql'));
			do_action('ix_xero_customer_synced', $user_id, $response);

			return true;
			
		} catch (Exception $e) {
			
			$error_message = 'Xero Customer Sync Error for user ID ' . $user_id . ': ' . $e->getMessage();

			// If it's a validation error from the API response
			if (strpos($e->getMessage(), 'ValidationException') !== false && !empty($response)) {
				$error_message .= ' | Full response: ' . json_encode($response);
			}

			error_log($error_message);
			update_user_meta($user_id, '_xero_sync_error', $error_message);
			do_action('ix_xero_customer_sync_failed', $user_id, $error_message);
			return false;
		}
	}   

	private function prepare_xero_customer_data($user) {
	
    $first_name = get_user_meta($user->ID, 'first_name', true);
    $last_name = get_user_meta($user->ID, 'last_name', true);
    
    // Ensure we have a valid name
    $display_name = trim($first_name . ' ' . $last_name);
    if (empty($display_name)) {
        $display_name = $user->display_name ?: $user->user_login;
    }

    $customer_data = array(
        'Name' => $display_name,
        'FirstName' => $first_name ?: substr($display_name, 0, 50), // Xero has 50 char limit
        'LastName' => $last_name ?: substr($display_name, 50), // Remainder for last name
        'EmailAddress' => $user->user_email,
        'IsCustomer' => true,
        'ContactStatus' => 'ACTIVE',
        'Phones' => array(
            array(
                'PhoneType' => 'DEFAULT',
                'PhoneNumber' => get_user_meta($user->ID, 'billing_phone', true) ?: ''
            )
        ),
        'Addresses' => array(
            array(
                'AddressType' => 'STREET',
                'AddressLine1' => get_user_meta($user->ID, 'billing_address_1', true) ?: '',
                'AddressLine2' => get_user_meta($user->ID, 'billing_address_2', true) ?: '',
                'City' => get_user_meta($user->ID, 'billing_city', true) ?: '',
                'Region' => get_user_meta($user->ID, 'billing_state', true) ?: '',
                'PostalCode' => get_user_meta($user->ID, 'billing_postcode', true) ?: '',
                'Country' => get_user_meta($user->ID, 'billing_country', true) ?: ''
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
		update_user_meta($user->ID, 'b2bking_customergroup', '4014' ?? '4014');
		update_user_meta($user->ID, 'salesking_assigned_agent', '4' ?? '4');
		        
        // Phone numbers
        if (!empty($contact['Phones'])) {
            foreach ($contact['Phones'] as $phone) {
                if ($phone['PhoneType'] === 'DEFAULT') {
                    update_user_meta($user->ID, 'billing_phone', sanitize_text_field($phone['PhoneNumber']));
                    break;
                }
            }
        }
		
		 // billing_company Name
        if (!empty($contact['Name'])) {
            foreach ($contact['Name'] as $name) {                
                    update_user_meta($user->ID, 'billing_company', sanitize_text_field($name['Name']));
                    break;               
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
			// Get all contacts with pagination
			$contacts = $this->xero_api->get_all_customers();
			$synced = 0;
			$skipped = 0;
			$errors = 0;

			foreach ($contacts as $contact) {
				try {
					if (empty($contact['EmailAddress'])) {
						$skipped++;
						continue;
					}

					$result = $this->sync_contact_from_xero($contact);
					if ($result) {
						$synced++;
					} else {
						$skipped++;
					}
				} catch (Exception $e) {
					error_log('Error syncing Xero contact ' . ($contact['ContactID'] ?? '') . ': ' . $e->getMessage());
					$errors++;
				}
			}

			wp_redirect(add_query_arg(
				array(
					'page' => 'ix-xero-settings',
					'tab' => 'customers',
					'xero_contacts_synced' => $synced,
					'xero_contacts_skipped' => $skipped,
					'xero_contacts_errors' => $errors
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