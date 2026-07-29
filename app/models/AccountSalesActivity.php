<?php
// AccountSalesActivity Model - Handles Inside Sales Activities for Existing Accounts

class AccountSalesActivity extends Model {

    public function generateActivityCode() {
        $year = date('Y');
        $prefix = "ASA-{$year}-";

        $this->query("SELECT activity_code FROM account_sales_activities 
                      WHERE activity_code LIKE :prefix 
                      ORDER BY activity_id DESC LIMIT 1");
        $this->bind(':prefix', $prefix . '%');
        $row = $this->single();

        if ($row && !empty($row->activity_code)) {
            $parts = explode('-', $row->activity_code);
            $seq = intval(end($parts)) + 1;
        } else {
            $seq = 1;
        }

        return $prefix . sprintf('%05d', $seq);
    }

    public function getRecentActivities(array $filters = []) {
        $sql = 'SELECT a.*, 
                       c.customer_code, c.company_name, c.first_name, c.email,
                       u.name AS rep_name
                FROM account_sales_activities a
                JOIN customers c ON a.customer_id = c.customer_id
                LEFT JOIN employees e ON a.assigned_rep_employee_id = e.employee_id
                LEFT JOIN users u ON e.user_id = u.user_id
                WHERE 1=1';

        $params = [];

        if (!empty($filters['customer_id'])) {
            $sql .= ' AND a.customer_id = :customer_id';
            $params[':customer_id'] = (int)$filters['customer_id'];
        }

        if (!empty($filters['assigned_rep_employee_id'])) {
            $sql .= ' AND a.assigned_rep_employee_id = :rep_id';
            $params[':rep_id'] = (int)$filters['assigned_rep_employee_id'];
        }

        if (!empty($filters['activity_type'])) {
            $sql .= ' AND a.activity_type = :activity_type';
            $params[':activity_type'] = $filters['activity_type'];
        }

        $sql .= ' ORDER BY a.created_at DESC LIMIT 50';

        $this->query($sql);
        foreach ($params as $k => $v) {
            $this->bind($k, $v);
        }

        return $this->resultSet();
    }

    public function logActivity($data) {
        $code = $this->generateActivityCode();

        $this->query('INSERT INTO account_sales_activities 
            (activity_code, customer_id, assigned_rep_employee_id, activity_type, outcome, next_follow_up_date, notes) 
            VALUES 
            (:activity_code, :customer_id, :assigned_rep_employee_id, :activity_type, :outcome, :next_follow_up_date, :notes)');

        $this->bind(':activity_code', $code);
        $this->bind(':customer_id', (int)$data['customer_id']);
        $this->bind(':assigned_rep_employee_id', !empty($data['assigned_rep_employee_id']) ? (int)$data['assigned_rep_employee_id'] : null);
        $this->bind(':activity_type', $data['activity_type'] ?? 'Call');
        $this->bind(':outcome', $data['outcome'] ?? 'Successful');
        $this->bind(':next_follow_up_date', !empty($data['next_follow_up_date']) ? $data['next_follow_up_date'] : null);
        $this->bind(':notes', $data['notes'] ?? null);

        if ($this->execute()) {
            $db = Database::getInstance()->getConnection();
            return (int)$db->lastInsertId();
        }
        return false;
    }
}
