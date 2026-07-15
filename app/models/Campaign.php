<?php
// Raptor CRM Campaign Model

class Campaign extends Model {
    // Get all campaigns with client name
    public function getCampaigns() {
        $this->query('SELECT c.*, cl.company_name 
                      FROM campaigns c 
                      JOIN clients cl ON c.client_id = cl.client_id 
                      ORDER BY c.created_at DESC');
        return $this->resultSet();
    }

    // Get campaigns by client ID
    public function getCampaignsByClient($clientId) {
        $this->query('SELECT c.*, cl.company_name 
                      FROM campaigns c 
                      JOIN clients cl ON c.client_id = cl.client_id 
                      WHERE c.client_id = :client_id 
                      ORDER BY c.created_at DESC');
        $this->bind(':client_id', $clientId);
        return $this->resultSet();
    }

    // Get campaign by ID
    public function getCampaignById($id) {
        $this->query('SELECT c.*, cl.company_name 
                      FROM campaigns c 
                      JOIN clients cl ON c.client_id = cl.client_id 
                      WHERE c.campaign_id = :id');
        $this->bind(':id', $id);
        return $this->single();
    }

    // Add campaign (Planned budget setup)
    public function addCampaign($data) {
        $this->query('INSERT INTO campaigns (client_id, name, channel, budget, spend, revenue_influenced, start_date, end_date, status) 
                      VALUES (:client_id, :name, :channel, :budget, :spend, :revenue_influenced, :start_date, :end_date, :status)');
        
        $this->bind(':client_id', $data['client_id']);
        $this->bind(':name', $data['name']);
        $this->bind(':channel', $data['channel']);
        $this->bind(':budget', $data['budget']);
        $this->bind(':spend', $data['spend'] ?? 0.00);
        $this->bind(':revenue_influenced', $data['revenue_influenced'] ?? 0.00);
        $this->bind(':start_date', $data['start_date']);
        $this->bind(':end_date', $data['end_date']);
        $this->bind(':status', $data['status']);

        return $this->execute();
    }

    // Update campaign
    public function updateCampaign($data) {
        $this->query('UPDATE campaigns 
                      SET client_id = :client_id, name = :name, channel = :channel, budget = :budget, 
                          spend = :spend, revenue_influenced = :revenue_influenced, 
                          start_date = :start_date, end_date = :end_date, status = :status 
                      WHERE campaign_id = :id');
        
        $this->bind(':id', $data['campaign_id']);
        $this->bind(':client_id', $data['client_id']);
        $this->bind(':name', $data['name']);
        $this->bind(':channel', $data['channel']);
        $this->bind(':budget', $data['budget']);
        $this->bind(':spend', $data['spend']);
        $this->bind(':revenue_influenced', $data['revenue_influenced']);
        $this->bind(':start_date', $data['start_date']);
        $this->bind(':end_date', $data['end_date']);
        $this->bind(':status', $data['status']);

        return $this->execute();
    }

    // Physical deletion is disabled by governance policy.
    public function deleteCampaign($id) {
        return false;
    }
}
