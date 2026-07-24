<?php
// Raptor CRM Social Account Model

class SocialAccount extends Model {
    public function __construct() {
        parent::__construct();
        $this->ensureCredentialsColumns();
    }

    /** Ensure credentials columns exist in social_accounts table */
    private function ensureCredentialsColumns() {
        $cols = [
            'username' => 'VARCHAR(100) NULL',
            'account_password' => 'VARCHAR(255) NULL',
            'account_notes' => 'TEXT NULL',
            'manager_remarks' => 'TEXT NULL'
        ];
        foreach ($cols as $colName => $colDef) {
            try {
                $this->query("ALTER TABLE social_accounts ADD COLUMN $colName $colDef");
                $this->execute();
            } catch (Exception $e) {
                // Column already exists
            }
        }
    }

    // Get all accounts with assigned employee names
    public function getAccounts() {
        $this->query('SELECT s.*, p.name as platform_name, p.icon as platform_icon, c.company_name,
                             GROUP_CONCAT(DISTINCT u.name SEPARATOR ", ") as assigned_employees,
                             GROUP_CONCAT(DISTINCT u.user_id) as assigned_user_ids
                      FROM social_accounts s
                      LEFT JOIN platforms p ON s.platform_id = p.platform_id
                      LEFT JOIN clients c ON s.client_id = c.client_id
                      LEFT JOIN assignments a ON s.account_id = a.account_id
                      LEFT JOIN users u ON a.user_id = u.user_id
                      GROUP BY s.account_id
                      ORDER BY c.company_name ASC, p.name ASC');
        return $this->resultSet();
    }

    // Get account by ID
    public function getAccountById($id) {
        $this->query('SELECT s.*, p.name as platform_name, p.icon as platform_icon, c.company_name 
                      FROM social_accounts s
                      LEFT JOIN platforms p ON s.platform_id = p.platform_id
                      LEFT JOIN clients c ON s.client_id = c.client_id
                      WHERE s.account_id = :id');
        $this->bind(':id', $id);
        return $this->single();
    }

    // Add account with credentials
    public function addAccount($data) {
        $this->query('INSERT INTO social_accounts (client_id, platform_id, platform, profile_name, username, account_password, account_notes, profile_url, status) 
                      VALUES (:client_id, :platform_id, :platform, :profile_name, :username, :account_password, :account_notes, :profile_url, :status)');
        $this->bind(':client_id', $data['client_id']);
        $this->bind(':platform_id', $data['platform_id']);
        $this->bind(':platform', $data['platform']);
        $this->bind(':profile_name', $data['profile_name']);
        $this->bind(':username', $data['username'] ?? null);
        $this->bind(':account_password', $data['account_password'] ?? null);
        $this->bind(':account_notes', $data['account_notes'] ?? null);
        $this->bind(':profile_url', $data['profile_url'] ?? null);
        $this->bind(':status', $data['status'] ?? 'active');
        return $this->execute();
    }

    // Update account with credentials
    public function updateAccount($data) {
        $this->query('UPDATE social_accounts 
                      SET client_id = :client_id, platform_id = :platform_id, platform = :platform, profile_name = :profile_name, 
                          username = :username, account_password = :account_password, account_notes = :account_notes, 
                          profile_url = :profile_url, status = :status 
                      WHERE account_id = :id');
        $this->bind(':client_id', $data['client_id']);
        $this->bind(':platform_id', $data['platform_id']);
        $this->bind(':platform', $data['platform']);
        $this->bind(':profile_name', $data['profile_name']);
        $this->bind(':username', $data['username'] ?? null);
        $this->bind(':account_password', $data['account_password'] ?? null);
        $this->bind(':account_notes', $data['account_notes'] ?? null);
        $this->bind(':profile_url', $data['profile_url'] ?? null);
        $this->bind(':status', $data['status']);
        $this->bind(':id', $data['account_id']);
        return $this->execute();
    }

    // Archive / Disable account (soft delete)
    public function archiveAccount($id) {
        $this->query('UPDATE social_accounts SET status = "disconnected" WHERE account_id = :id');
        $this->bind(':id', $id);
        return $this->execute();
    }

    // Physically remove account
    public function removeAccount($id) {
        $this->query('DELETE FROM social_accounts WHERE account_id = :id');
        $this->bind(':id', $id);
        return $this->execute();
    }

    // Save manager review remarks / comment
    public function saveManagerRemarks($accountId, $remarks) {
        $this->query('UPDATE social_accounts SET manager_remarks = :remarks WHERE account_id = :id');
        $this->bind(':remarks', $remarks);
        $this->bind(':id', $accountId);
        return $this->execute();
    }

    // --- Assignments Layer ---

    // Get assignments for an account
    public function getAssignmentsByAccount($accountId) {
        $this->query('SELECT a.*, u.name as user_name, u.email as user_email 
                      FROM assignments a
                      JOIN users u ON a.user_id = u.user_id
                      WHERE a.account_id = :account_id');
        $this->bind(':account_id', $accountId);
        return $this->resultSet();
    }

    // Get all assignments with account and platform names
    public function getAssignments() {
        $this->query('SELECT a.*, u.name as employee_name, s.profile_name, p.name as platform_name
                      FROM assignments a
                      JOIN users u ON a.user_id = u.user_id
                      JOIN social_accounts s ON a.account_id = s.account_id
                      JOIN platforms p ON s.platform_id = p.platform_id');
        return $this->resultSet();
    }

    // Get accounts assigned to a user
    public function getAssignedAccountsForUser($userId) {
        $this->query('SELECT s.*, p.name as platform_name, p.icon as platform_icon, c.company_name
                      FROM assignments a
                      JOIN social_accounts s ON a.account_id = s.account_id
                      LEFT JOIN platforms p ON s.platform_id = p.platform_id
                      LEFT JOIN clients c ON s.client_id = c.client_id
                      WHERE a.user_id = :user_id AND s.status = "active"');
        $this->bind(':user_id', $userId);
        $results = $this->resultSet();

        if (empty($results)) {
            $this->query('SELECT s.*, p.name as platform_name, p.icon as platform_icon, c.company_name
                          FROM social_accounts s
                          LEFT JOIN platforms p ON s.platform_id = p.platform_id
                          LEFT JOIN clients c ON s.client_id = c.client_id
                          WHERE s.status = "active"');
            $results = $this->resultSet();
        }

        return $results;
    }

    // Check if account is assigned to anyone
    public function isAccountAssigned($accountId) {
        $this->query('SELECT COUNT(*) FROM assignments WHERE account_id = :account_id');
        $this->bind(':account_id', $accountId);
        return (int)$this->stmt->fetchColumn() > 0;
    }

    // Add assignment
    public function assignEmployee($accountId, $userId, $assignedBy, $isShared = false) {
        // If not shared, check if already assigned to someone else
        if (!$isShared) {
            $this->query('SELECT COUNT(*) FROM assignments WHERE account_id = :account_id AND user_id != :user_id');
            $this->bind(':account_id', $accountId);
            $this->bind(':user_id', $userId);
            if ((int)$this->stmt->fetchColumn() > 0) {
                return false; // Already assigned to another user and shared access not enabled
            }
        }

        $this->query('INSERT INTO assignments (account_id, user_id, assigned_by, is_shared) 
                      VALUES (:account_id, :user_id, :assigned_by, :is_shared)
                      ON DUPLICATE KEY UPDATE is_shared = :is_shared2');
        $this->bind(':account_id', $accountId);
        $this->bind(':user_id', $userId);
        $this->bind(':assigned_by', $assignedBy);
        $this->bind(':is_shared', $isShared, PDO::PARAM_BOOL);
        $this->bind(':is_shared2', $isShared, PDO::PARAM_BOOL);
        return $this->execute();
    }

    // Clear assignments for an account
    public function clearAssignments($accountId) {
        $this->query('DELETE FROM assignments WHERE account_id = :account_id');
        $this->bind(':account_id', $accountId);
        return $this->execute();
    }

    // Remove single assignment
    public function unassignEmployee($accountId, $userId) {
        $this->query('DELETE FROM assignments WHERE account_id = :account_id AND user_id = :user_id');
        $this->bind(':account_id', $accountId);
        $this->bind(':user_id', $userId);
        return $this->execute();
    }
}
