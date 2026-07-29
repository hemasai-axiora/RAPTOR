<?php
// Raptor CRM Campaign Model

class Campaign extends Model {

    public function generateCampaignCode(): string {
        $year = date('Y');
        $this->query('SELECT MAX(campaign_id) AS max_id FROM campaigns');
        $row = $this->single();
        $nextId = ($row && $row->max_id) ? ((int) $row->max_id + 1) : 1;
        return sprintf('CMP-%s-%05d', $year, $nextId);
    }

    // Get all campaigns with client name & owner employee name
    public function getCampaigns() {
        $this->query('SELECT c.*, cl.company_name, emp_u.name AS owner_name, emp.job_title AS owner_job_title 
                      FROM campaigns c 
                      JOIN clients cl ON c.client_id = cl.client_id 
                      LEFT JOIN employees emp ON c.owner_employee_id = emp.employee_id
                      LEFT JOIN users emp_u ON emp.user_id = emp_u.user_id
                      ORDER BY c.created_at DESC');
        return $this->resultSet();
    }

    // Get campaigns by client ID
    public function getCampaignsByClient($clientId) {
        $this->query('SELECT c.*, cl.company_name, emp_u.name AS owner_name, emp.job_title AS owner_job_title 
                      FROM campaigns c 
                      JOIN clients cl ON c.client_id = cl.client_id 
                      LEFT JOIN employees emp ON c.owner_employee_id = emp.employee_id
                      LEFT JOIN users emp_u ON emp.user_id = emp_u.user_id
                      WHERE c.client_id = :client_id 
                      ORDER BY c.created_at DESC');
        $this->bind(':client_id', $clientId);
        return $this->resultSet();
    }

    // Get campaign by ID
    public function getCampaignById($id) {
        $this->query('SELECT c.*, cl.company_name, emp_u.name AS owner_name, emp.job_title AS owner_job_title 
                      FROM campaigns c 
                      JOIN clients cl ON c.client_id = cl.client_id 
                      LEFT JOIN employees emp ON c.owner_employee_id = emp.employee_id
                      LEFT JOIN users emp_u ON emp.user_id = emp_u.user_id
                      WHERE c.campaign_id = :id');
        $this->bind(':id', $id);
        return $this->single();
    }

    // Add campaign
    public function addCampaign($data) {
        if (empty($data['campaign_code']) || strpos($data['campaign_code'], 'Auto-generated') !== false) {
            $data['campaign_code'] = $this->generateCampaignCode();
        }

        $this->query('INSERT INTO campaigns 
            (campaign_code, client_id, owner_employee_id, name, channel, campaign_type, vendor_name, location, reach_estimate, proof_of_execution, budget, spend, revenue_influenced, start_date, end_date, status) 
            VALUES 
            (:campaign_code, :client_id, :owner_employee_id, :name, :channel, :campaign_type, :vendor_name, :location, :reach_estimate, :proof_of_execution, :budget, :spend, :revenue_influenced, :start_date, :end_date, :status)');
        
        $this->bind(':campaign_code', $data['campaign_code']);
        $this->bind(':client_id', $data['client_id']);
        $this->bind(':owner_employee_id', !empty($data['owner_employee_id']) ? (int) $data['owner_employee_id'] : null);
        $this->bind(':name', $data['name']);
        $this->bind(':channel', $data['channel']);
        $this->bind(':campaign_type', $data['campaign_type'] ?? 'online');
        $this->bind(':vendor_name', !empty($data['vendor_name']) ? $data['vendor_name'] : null);
        $this->bind(':location', !empty($data['location']) ? $data['location'] : null);
        $this->bind(':reach_estimate', isset($data['reach_estimate']) && $data['reach_estimate'] !== '' ? (int) $data['reach_estimate'] : null);
        $this->bind(':proof_of_execution', !empty($data['proof_of_execution']) ? $data['proof_of_execution'] : null);
        $this->bind(':budget', $data['budget']);
        $this->bind(':spend', $data['spend'] ?? 0.00);
        $this->bind(':revenue_influenced', $data['revenue_influenced'] ?? 0.00);
        $this->bind(':start_date', $data['start_date']);
        $this->bind(':end_date', !empty($data['end_date']) ? $data['end_date'] : null);
        $this->bind(':status', $data['status']);

        return $this->execute();
    }

    // Update campaign
    public function updateCampaign($data) {
        $this->query('UPDATE campaigns 
                      SET client_id = :client_id, owner_employee_id = :owner_employee_id, name = :name, 
                          channel = :channel, campaign_type = :campaign_type, vendor_name = :vendor_name, 
                          location = :location, reach_estimate = :reach_estimate, 
                          proof_of_execution = COALESCE(:proof_of_execution, proof_of_execution), 
                          budget = :budget, spend = :spend, revenue_influenced = :revenue_influenced, 
                          start_date = :start_date, end_date = :end_date, status = :status 
                      WHERE campaign_id = :id');
        
        $this->bind(':id', $data['campaign_id']);
        $this->bind(':client_id', $data['client_id']);
        $this->bind(':owner_employee_id', !empty($data['owner_employee_id']) ? (int) $data['owner_employee_id'] : null);
        $this->bind(':name', $data['name']);
        $this->bind(':channel', $data['channel']);
        $this->bind(':campaign_type', $data['campaign_type'] ?? 'online');
        $this->bind(':vendor_name', !empty($data['vendor_name']) ? $data['vendor_name'] : null);
        $this->bind(':location', !empty($data['location']) ? $data['location'] : null);
        $this->bind(':reach_estimate', isset($data['reach_estimate']) && $data['reach_estimate'] !== '' ? (int) $data['reach_estimate'] : null);
        $this->bind(':proof_of_execution', !empty($data['proof_of_execution']) ? $data['proof_of_execution'] : null);
        $this->bind(':budget', $data['budget']);
        $this->bind(':spend', $data['spend']);
        $this->bind(':revenue_influenced', $data['revenue_influenced']);
        $this->bind(':start_date', $data['start_date']);
        $this->bind(':end_date', !empty($data['end_date']) ? $data['end_date'] : null);
        $this->bind(':status', $data['status']);

        return $this->execute();
    }

    // Physical deletion is disabled by governance policy.
    public function deleteCampaign($id) {
        return false;
    }
}
