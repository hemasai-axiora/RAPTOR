<?php
// Raptor CRM Invoice Model

class Invoice extends Model {
    public function __construct() {
        parent::__construct();
        // Self-healing check: Ensure sender_details column exists in invoices table
        try {
            $this->db->query("SELECT sender_details FROM invoices LIMIT 1");
        } catch (Exception $e) {
            $this->db->exec("ALTER TABLE invoices ADD COLUMN sender_details TEXT NULL AFTER conversion_rate");
        }

        // Self-healing check: Ensure utr_number column exists
        try {
            $this->db->query("SELECT utr_number FROM invoices LIMIT 1");
        } catch (Exception $e) {
            $this->db->exec("ALTER TABLE invoices ADD COLUMN utr_number VARCHAR(100) NULL AFTER sender_details");
        }

        // Self-healing check: Ensure cancel_reason column exists
        try {
            $this->db->query("SELECT cancel_reason FROM invoices LIMIT 1");
        } catch (Exception $e) {
            $this->db->exec("ALTER TABLE invoices ADD COLUMN cancel_reason TEXT NULL AFTER utr_number");
        }
    }

    // Get all invoices with client name
    public function getInvoices() {
        $this->query('SELECT i.*, c.company_name 
                      FROM invoices i 
                      JOIN clients c ON i.client_id = c.client_id 
                      ORDER BY i.created_at DESC');
        return $this->resultSet();
    }

    // Get invoice by ID
    public function getInvoiceById($id) {
        $this->query('SELECT i.*, c.company_name, c.email as client_email 
                      FROM invoices i 
                      JOIN clients c ON i.client_id = c.client_id 
                      WHERE i.invoice_id = :id');
        $this->bind(':id', $id);
        return $this->single();
    }

    // Add invoice
    public function addInvoice($data) {
        $this->query('INSERT INTO invoices (client_id, invoice_number, amount, status, due_date, billing_address, currency, conversion_rate, sender_details) 
                      VALUES (:client_id, :invoice_number, :amount, :status, :due_date, :billing_address, :currency, :conversion_rate, :sender_details)');
        
        $this->bind(':client_id', $data['client_id']);
        $this->bind(':invoice_number', $data['invoice_number']);
        $this->bind(':amount', $data['amount']);
        $this->bind(':status', $data['status']);
        $this->bind(':due_date', $data['due_date']);
        $this->bind(':billing_address', $data['billing_address'] ?? '');
        $this->bind(':currency', $data['currency'] ?? 'USD');
        $this->bind(':conversion_rate', $data['conversion_rate'] ?? 1.0000);
        $this->bind(':sender_details', $data['sender_details'] ?? null);

        return $this->execute();
    }

    // Update invoice status
    public function updateStatus($id, $status) {
        $this->query('UPDATE invoices SET status = :status WHERE invoice_id = :id');
        $this->bind(':status', $status);
        $this->bind(':id', $id);
        return $this->execute();
    }

    // Mark invoice as paid with UTR number
    public function updatePayment($id, $utrNumber) {
        $this->query('UPDATE invoices SET status = :status, utr_number = :utr WHERE invoice_id = :id');
        $this->bind(':status', 'paid');
        $this->bind(':utr', $utrNumber);
        $this->bind(':id', $id);
        return $this->execute();
    }

    // Mark invoice as cancelled with a reason
    public function updateCancellation($id, $reason) {
        $this->query('UPDATE invoices SET status = :status, cancel_reason = :reason WHERE invoice_id = :id');
        $this->bind(':status', 'cancelled');
        $this->bind(':reason', $reason);
        $this->bind(':id', $id);
        return $this->execute();
    }

    // Physical deletion is disabled by governance policy.
    public function deleteInvoice($id) {
        return false;
    }
}
