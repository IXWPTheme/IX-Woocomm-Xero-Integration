<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once 'class-ix-xero-api.php';

class IX_Xero_API_Customers extends IX_Xero_API 
{
	/**
     * Get all customers from Xero with pagination support
     */
    public function get_all_customers($modified_since = null) 
    {
        $all_contacts = array();
        $page = 1;
        $page_size = 100; // Xero's maximum page size
        
        do {
            $contacts = $this->get_customers_page($modified_since, $page, $page_size);
            
            if (!empty($contacts)) {
                $all_contacts = array_merge($all_contacts, $contacts);
            }
            
            $page++;
            
            // Stop if we got fewer contacts than requested (end of data)
        } while (!empty($contacts) && count($contacts) === $page_size);
        
        return $all_contacts;
    }
    /**
     * Get a single page of customers
     */
    public function get_customers_page($modified_since = null, $page = 1, $page_size = 100) 
    {
       // $endpoint = 'Contacts?page='.$page.'&where=IsCustomer==true';
		
		$endpoint = 'Contacts?page='.$page;
        
        if ($modified_since) {
            $endpoint .= '&&UpdatedDateUTC>=' . urlencode($modified_since);
        }
        
        try {
            $result = $this->make_api_request($endpoint);
            return !empty($result['Contacts']) ? $result['Contacts'] : array();
        } catch (Exception $e) {
            error_log('Error fetching Xero contacts: ' . $e->getMessage());
            return array();
        }
    }
	
    public function get_customers($modified_since = null, $page = 1, $page_size = 100) 
    {
        $endpoint = 'Contacts?page='.$page.'&where=IsCustomer==true';
        
        if ($modified_since) {
            $endpoint .= '&&UpdatedDateUTC>=' . urlencode($modified_since);
        }
        
        $result = $this->make_api_request($endpoint);
        return !empty($result['Contacts']) ? $result['Contacts'] : array();
    }

    public function get_customer_by_id($contact_id) 
    {
        $result = $this->make_api_request('Contacts/' . $contact_id);
        return !empty($result['Contacts'][0]) ? $result['Contacts'][0] : null;
    }

    public function create_customer($customer_data) 
    {
        return $this->make_api_request('Contacts', 'POST', array('Contacts' => array($customer_data)));
    }

    public function update_customer($contact_id, $customer_data) 
    {
        return $this->make_api_request('Contacts/' . $contact_id, 'POST', array('Contacts' => array($customer_data)));
    }

    public function find_customer_by_email($email) 
    {
        $result = $this->make_api_request('Contacts?where=EmailAddress=="' . urlencode($email) . '"');
        return !empty($result['Contacts'][0]) ? $result['Contacts'][0] : null;
    }
}