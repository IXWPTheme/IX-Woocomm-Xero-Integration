<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once 'class-ix-xero-api.php';

class IX_Xero_API_Invoices extends IX_Xero_API {
    public function create_invoice($invoice_data) {
        return $this->make_api_request('Invoices', 'POST', array('Invoices' => array($invoice_data)));
    }
    
    public function update_invoice($invoice_id, $invoice_data) {
        return $this->make_api_request('Invoices/' . $invoice_id, 'POST', array('Invoices' => array($invoice_data)));
    }
    
    public function get_invoice($invoice_id) {
        return $this->make_api_request('Invoices/' . $invoice_id);
    }
    
    public function get_invoices_by_reference($reference) {
        $result = $this->make_api_request('Invoices?where=Reference=="' . urlencode($reference) . '"');
        return !empty($result['Invoices'][0]) ? $result['Invoices'][0] : null;
    }
}