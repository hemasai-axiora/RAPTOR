<?php
// Raptor CRM Customer Model

class Customer extends Model {
    public const STATUSES = ['Active', 'On Hold', 'Churned', 'Renewal Due'];
    public const TYPES = ['Individual', 'Business'];

    public function generateCustomerCode(): string {
        $year = date('Y');
        $this->query('SELECT MAX(customer_id) AS max_id FROM customers');
        $row = $this->single();
        $nextId = ($row && $row->max_id) ? ((int) $row->max_id + 1) : 1;
        return sprintf('CUST-%s-%05d', $year, $nextId);
    }

    public function getAllCustomersForSelect(): array {
        try {
            $this->query('SELECT customer_id, first_name, company_name, customer_code, email FROM customers ORDER BY created_at DESC LIMIT 200');
            $res = $this->resultSet() ?: [];
            if (empty($res)) {
                $this->query('SELECT client_id AS customer_id, company_name, email, CONCAT("CUST-", client_id) AS customer_code FROM clients ORDER BY created_at DESC LIMIT 200');
                $res = $this->resultSet() ?: [];
            }
            return $res;
        } catch (Throwable $e) {
            return [];
        }
    }

    public function getCustomers(array $filters = []) {
        $sql = 'SELECT c.*, 
                       u.name AS owner_name, 
                       l.lead_code AS originating_lead_code,
                       l.first_name AS lead_first_name,
                       cl.company_name AS client_company_name
                FROM customers c
                LEFT JOIN employees e ON c.owner_employee_id = e.employee_id
                LEFT JOIN users u ON e.user_id = u.user_id
                LEFT JOIN leads l ON c.converted_from_lead_id = l.lead_id
                LEFT JOIN clients cl ON c.associated_client_id = cl.client_id
                WHERE 1=1';

        $params = [];

        if (!empty($filters['status'])) {
            $sql .= ' AND c.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['customer_type'])) {
            $sql .= ' AND c.customer_type = :customer_type';
            $params[':customer_type'] = $filters['customer_type'];
        }

        if (!empty($filters['owner_employee_id'])) {
            $sql .= ' AND c.owner_employee_id = :owner_employee_id';
            $params[':owner_employee_id'] = (int)$filters['owner_employee_id'];
        }

        if (!empty($filters['tag'])) {
            $sql .= ' AND c.tags LIKE :tag';
            $params[':tag'] = '%' . $filters['tag'] . '%';
        }

        if (!empty($filters['search'])) {
            $sql .= ' AND (c.customer_code LIKE :search OR c.first_name LIKE :search OR c.company_name LIKE :search OR c.email LIKE :search OR c.phone LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $sql .= ' ORDER BY c.created_at DESC';

        $this->query($sql);
        foreach ($params as $param => $value) {
            $this->bind($param, $value);
        }

        return $this->resultSet();
    }

    public function getCustomerById($id) {
        $this->query('SELECT c.*, 
                             u.name AS owner_name, 
                             l.lead_code AS originating_lead_code,
                             l.status AS originating_lead_status,
                             cl.company_name AS client_company_name
                      FROM customers c
                      LEFT JOIN employees e ON c.owner_employee_id = e.employee_id
                      LEFT JOIN users u ON e.user_id = u.user_id
                      LEFT JOIN leads l ON c.converted_from_lead_id = l.lead_id
                      LEFT JOIN clients cl ON c.associated_client_id = cl.client_id
                      WHERE c.customer_id = :id');
        $this->bind(':id', (int)$id);
        return $this->single();
    }

    public function addCustomer($data) {
        $customerCode = $this->generateCustomerCode();

        $this->query('INSERT INTO customers 
            (customer_code, converted_from_lead_id, first_name, company_name, customer_type, email, phone, 
             billing_address, shipping_address, owner_employee_id, associated_client_id, status, onboarding_date, 
             contract_value, payment_terms, products_subscribed, renewal_date, tags, notes) 
            VALUES 
            (:customer_code, :converted_from_lead_id, :first_name, :company_name, :customer_type, :email, :phone, 
             :billing_address, :shipping_address, :owner_employee_id, :associated_client_id, :status, :onboarding_date, 
             :contract_value, :payment_terms, :products_subscribed, :renewal_date, :tags, :notes)');

        $this->bind(':customer_code', $customerCode);
        $this->bind(':converted_from_lead_id', !empty($data['converted_from_lead_id']) ? (int)$data['converted_from_lead_id'] : null);
        $companyName = !empty($data['company_name']) ? preg_replace('/\s+\d{10,}$/', '', trim($data['company_name'])) : null;
        $this->bind(':first_name', $data['first_name'] ?? null);
        $this->bind(':company_name', $companyName);
        $this->bind(':customer_type', $data['customer_type'] ?? 'Business');
        $this->bind(':email', $data['email']);
        $this->bind(':phone', $data['phone'] ?? null);
        $this->bind(':billing_address', $data['billing_address'] ?? null);
        $this->bind(':shipping_address', $data['shipping_address'] ?? null);
        $this->bind(':owner_employee_id', !empty($data['owner_employee_id']) ? (int)$data['owner_employee_id'] : null);
        $this->bind(':associated_client_id', !empty($data['associated_client_id']) ? (int)$data['associated_client_id'] : null);
        $this->bind(':status', $data['status'] ?? 'Active');
        $this->bind(':onboarding_date', !empty($data['onboarding_date']) ? $data['onboarding_date'] : date('Y-m-d'));
        $this->bind(':contract_value', (float)($data['contract_value'] ?? 0));
        $this->bind(':payment_terms', $data['payment_terms'] ?? 'Net 30');
        $this->bind(':products_subscribed', $data['products_subscribed'] ?? null);
        $this->bind(':renewal_date', !empty($data['renewal_date']) ? $data['renewal_date'] : null);
        $this->bind(':tags', $data['tags'] ?? null);
        $this->bind(':notes', $data['notes'] ?? null);

        if ($this->execute()) {
            $db = Database::getInstance()->getConnection();
            return (int)$db->lastInsertId();
        }
        return false;
    }

    public function updateCustomer($data) {
        $this->query('UPDATE customers 
                      SET first_name = :first_name, 
                          company_name = :company_name, 
                          customer_type = :customer_type, 
                          email = :email, 
                          phone = :phone, 
                          billing_address = :billing_address, 
                          shipping_address = :shipping_address, 
                          owner_employee_id = :owner_employee_id, 
                          associated_client_id = :associated_client_id, 
                          status = :status, 
                          onboarding_date = :onboarding_date, 
                          contract_value = :contract_value, 
                          payment_terms = :payment_terms, 
                          products_subscribed = :products_subscribed, 
                          renewal_date = :renewal_date, 
                          tags = :tags, 
                          notes = :notes 
                      WHERE customer_id = :id');

        $this->bind(':id', (int)$data['customer_id']);
        $this->bind(':first_name', $data['first_name'] ?? null);
        $this->bind(':company_name', $data['company_name'] ?? null);
        $this->bind(':customer_type', $data['customer_type'] ?? 'Business');
        $this->bind(':email', $data['email']);
        $this->bind(':phone', $data['phone'] ?? null);
        $this->bind(':billing_address', $data['billing_address'] ?? null);
        $this->bind(':shipping_address', $data['shipping_address'] ?? null);
        $this->bind(':owner_employee_id', !empty($data['owner_employee_id']) ? (int)$data['owner_employee_id'] : null);
        $this->bind(':associated_client_id', !empty($data['associated_client_id']) ? (int)$data['associated_client_id'] : null);
        $this->bind(':status', $data['status'] ?? 'Active');
        $this->bind(':onboarding_date', !empty($data['onboarding_date']) ? $data['onboarding_date'] : null);
        $this->bind(':contract_value', (float)($data['contract_value'] ?? 0));
        $this->bind(':payment_terms', $data['payment_terms'] ?? 'Net 30');
        $this->bind(':products_subscribed', $data['products_subscribed'] ?? null);
        $this->bind(':renewal_date', !empty($data['renewal_date']) ? $data['renewal_date'] : null);
        $this->bind(':tags', $data['tags'] ?? null);
        $this->bind(':notes', $data['notes'] ?? null);

        return $this->execute();
    }
}
