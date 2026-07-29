<?php
// AccountOpportunity Model - Handles Account Growth Opportunities (Upsell, Renewal, Cross-sell)

class AccountOpportunity extends Model {

    public function generateOpportunityCode() {
        $year = date('Y');
        $prefix = "OPP-{$year}-";

        $this->query("SELECT opportunity_code FROM account_opportunities 
                      WHERE opportunity_code LIKE :prefix 
                      ORDER BY opportunity_id DESC LIMIT 1");
        $this->bind(':prefix', $prefix . '%');
        $row = $this->single();

        if ($row && !empty($row->opportunity_code)) {
            $parts = explode('-', $row->opportunity_code);
            $seq = intval(end($parts)) + 1;
        } else {
            $seq = 1;
        }

        return $prefix . sprintf('%05d', $seq);
    }

    public function getOpportunitiesByStage(array $filters = []) {
        $sql = 'SELECT o.*, 
                       c.customer_code, c.company_name, c.first_name, c.email, c.renewal_date,
                       u.name AS rep_name
                FROM account_opportunities o
                JOIN customers c ON o.customer_id = c.customer_id
                LEFT JOIN employees e ON o.assigned_rep_employee_id = e.employee_id
                LEFT JOIN users u ON e.user_id = u.user_id
                WHERE 1=1';

        $params = [];

        if (!empty($filters['customer_id'])) {
            $sql .= ' AND o.customer_id = :customer_id';
            $params[':customer_id'] = (int)$filters['customer_id'];
        }

        if (!empty($filters['assigned_rep_employee_id'])) {
            $sql .= ' AND o.assigned_rep_employee_id = :rep_id';
            $params[':rep_id'] = (int)$filters['assigned_rep_employee_id'];
        }

        if (!empty($filters['opportunity_type'])) {
            $sql .= ' AND o.opportunity_type = :opp_type';
            $params[':opp_type'] = $filters['opportunity_type'];
        }

        if (!empty($filters['stage'])) {
            $sql .= ' AND o.stage = :stage';
            $params[':stage'] = $filters['stage'];
        }

        $sql .= ' ORDER BY o.created_at DESC';

        $this->query($sql);
        foreach ($params as $k => $v) {
            $this->bind($k, $v);
        }

        $results = $this->resultSet();

        $pipeline = [
            'Identified' => [],
            'Proposed' => [],
            'Negotiating' => [],
            'Won' => [],
            'Lost' => []
        ];

        foreach ($results as $opp) {
            $stage = $opp->stage;
            if (isset($pipeline[$stage])) {
                $pipeline[$stage][] = $opp;
            }
        }

        return $pipeline;
    }

    public function createOpportunity($data) {
        $code = $this->generateOpportunityCode();

        $this->query('INSERT INTO account_opportunities 
            (opportunity_code, customer_id, assigned_rep_employee_id, title, opportunity_type, stage, expected_value, probability, target_close_date, notes) 
            VALUES 
            (:opportunity_code, :customer_id, :assigned_rep_employee_id, :title, :opportunity_type, :stage, :expected_value, :probability, :target_close_date, :notes)');

        $this->bind(':opportunity_code', $code);
        $this->bind(':customer_id', (int)$data['customer_id']);
        $this->bind(':assigned_rep_employee_id', !empty($data['assigned_rep_employee_id']) ? (int)$data['assigned_rep_employee_id'] : null);
        $this->bind(':title', $data['title']);
        $this->bind(':opportunity_type', $data['opportunity_type'] ?? 'Upsell');
        $this->bind(':stage', $data['stage'] ?? 'Identified');
        $this->bind(':expected_value', (float)($data['expected_value'] ?? 0));
        $this->bind(':probability', (int)($data['probability'] ?? 50));
        $this->bind(':target_close_date', !empty($data['target_close_date']) ? $data['target_close_date'] : null);
        $this->bind(':notes', $data['notes'] ?? null);

        if ($this->execute()) {
            $db = Database::getInstance()->getConnection();
            return (int)$db->lastInsertId();
        }
        return false;
    }

    public function updateStage($id, $stage) {
        $this->query('UPDATE account_opportunities SET stage = :stage WHERE opportunity_id = :id');
        $this->bind(':stage', $stage);
        $this->bind(':id', (int)$id);
        return $this->execute();
    }

    public function getChurnRiskAccounts() {
        // Accounts with renewal_date approaching within 30 days or no activity logged in >30 days
        $this->query('SELECT c.*, 
                             u.name AS owner_name,
                             e.employee_id AS owner_employee_id,
                             MAX(a.created_at) AS last_activity_at,
                             DATEDIFF(c.renewal_date, CURDATE()) AS days_to_renewal,
                             DATEDIFF(NOW(), MAX(a.created_at)) AS days_since_activity
                      FROM customers c
                      LEFT JOIN employees e ON c.owner_employee_id = e.employee_id
                      LEFT JOIN users u ON e.user_id = u.user_id
                      LEFT JOIN account_sales_activities a ON c.customer_id = a.customer_id
                      WHERE c.status IN ("Active", "Renewal Due")
                      GROUP BY c.customer_id
                      HAVING (days_to_renewal IS NOT NULL AND days_to_renewal <= 30 AND days_to_renewal >= 0) 
                         OR last_activity_at IS NULL 
                         OR DATEDIFF(NOW(), last_activity_at) > 30
                      ORDER BY CASE 
                          WHEN days_to_renewal IS NOT NULL AND days_to_renewal <= 30 THEN 1
                          WHEN last_activity_at IS NULL THEN 2
                          ELSE 3
                      END ASC, c.customer_id DESC LIMIT 100');
        return $this->resultSet();
    }
}
