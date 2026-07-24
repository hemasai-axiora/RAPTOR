<?php
// Raptor CRM — Team / Branch / Territory model (Sprint 1)

class Team extends Model {

    // ---------------- Teams ----------------

    public function getTeams() {
        $this->query('SELECT t.*, b.name AS branch_name, tr.name AS territory_name,
                             tl.name AS leader_name, mg.name AS manager_name,
                             (SELECT COUNT(*) FROM employees e WHERE e.team_id = t.team_id) AS member_count
                      FROM teams t
                      LEFT JOIN branches b     ON t.branch_id = b.branch_id
                      LEFT JOIN territories tr ON t.territory_id = tr.territory_id
                      LEFT JOIN users tl       ON t.team_leader_user_id = tl.user_id
                      LEFT JOIN users mg       ON t.manager_user_id = mg.user_id
                      ORDER BY t.name ASC');
        return $this->resultSet();
    }

    public function getTeamById($id) {
        $this->query('SELECT * FROM teams WHERE team_id = :id');
        $this->bind(':id', (int) $id);
        return $this->single();
    }

    public function addTeam($d) {
        $this->query('INSERT INTO teams (name, team_leader_user_id, manager_user_id, branch_id, territory_id, status)
                      VALUES (:name, :leader, :manager, :branch, :territory, :status)');
        $this->bindTeam($d);
        return $this->execute();
    }

    public function updateTeam($d) {
        $this->query('UPDATE teams SET name = :name, team_leader_user_id = :leader,
                             manager_user_id = :manager, branch_id = :branch,
                             territory_id = :territory, status = :status
                      WHERE team_id = :id');
        $this->bind(':id', (int) $d['team_id']);
        $this->bindTeam($d);
        return $this->execute();
    }

    public function deleteTeam($id) {
        $this->query('DELETE FROM teams WHERE team_id = :id');
        $this->bind(':id', $id);
        return $this->execute();
    }

    private function bindTeam($d) {
        $this->bind(':name',      trim($d['name']));
        $this->bind(':leader',    !empty($d['team_leader_user_id']) ? (int) $d['team_leader_user_id'] : null);
        $this->bind(':manager',   !empty($d['manager_user_id'])     ? (int) $d['manager_user_id']     : null);
        $this->bind(':branch',    !empty($d['branch_id'])           ? (int) $d['branch_id']           : null);
        $this->bind(':territory', !empty($d['territory_id'])        ? (int) $d['territory_id']        : null);
        $this->bind(':status',    $d['status'] ?? 'active');
    }

    /** Assign a user to a team (and optional reporting manager). */
    public function assignMember($userId, $teamId, $reportingManagerId = null) {
        // Ensure an employees row exists for this user, then set team.
        $this->query('SELECT employee_id FROM employees WHERE user_id = :uid');
        $this->bind(':uid', (int) $userId);
        $emp = $this->single();

        if (!$emp) {
            $this->query('INSERT INTO employees (user_id, department, job_title, hire_date, team_id, reporting_manager_id)
                          VALUES (:uid, :dept, :title, CURDATE(), :team, :mgr)');
            $this->bind(':uid', (int) $userId);
            $this->bind(':dept', 'Sales');
            $this->bind(':title', 'Sales Executive');
            $this->bind(':team', $teamId !== null ? (int)$teamId : null);
            $this->bind(':mgr', $reportingManagerId ? (int)$reportingManagerId : null);
            return $this->execute();
        }

        $this->query('UPDATE employees SET team_id = :team, reporting_manager_id = :mgr WHERE user_id = :uid');
        $this->bind(':team', $teamId !== null ? (int)$teamId : null);
        $this->bind(':mgr', $reportingManagerId ? (int)$reportingManagerId : null);
        $this->bind(':uid', (int) $userId);
        return $this->execute();
    }

    // ---------------- Branches ----------------

    public function getBranches() {
        $this->query('SELECT * FROM branches ORDER BY name ASC');
        return $this->resultSet();
    }

    public function addBranch($d) {
        $this->query('INSERT INTO branches (name, address, lat, lng, status)
                      VALUES (:name, :addr, :lat, :lng, :status)');
        $this->bind(':name', trim($d['name']));
        $this->bind(':addr', $d['address'] ?? null);
        $this->bind(':lat', $d['lat'] !== '' ? $d['lat'] : null);
        $this->bind(':lng', $d['lng'] !== '' ? $d['lng'] : null);
        $this->bind(':status', $d['status'] ?? 'active');
        return $this->execute();
    }

    public function deleteBranch($id) {
        $this->query('DELETE FROM branches WHERE branch_id = :id');
        $this->bind(':id', $id);
        return $this->execute();
    }

    // ---------------- Territories ----------------

    public function getTerritories() {
        $this->query('SELECT * FROM territories ORDER BY name ASC');
        return $this->resultSet();
    }

    public function addTerritory($d) {
        $this->query('INSERT INTO territories (name, description, status)
                      VALUES (:name, :desc, :status)');
        $this->bind(':name', trim($d['name']));
        $this->bind(':desc', $d['description'] ?? null);
        $this->bind(':status', $d['status'] ?? 'active');
        return $this->execute();
    }

    public function deleteTerritory($id) {
        $this->query('DELETE FROM territories WHERE territory_id = :id');
        $this->bind(':id', $id);
        return $this->execute();
    }

    // ---------------- Geofences ----------------

    public function getGeofences() {
        $this->query('SELECT * FROM geofences ORDER BY type ASC, name ASC');
        return $this->resultSet();
    }

    public function addGeofence($d) {
        $this->query('INSERT INTO geofences (name, type, ref_id, center_lat, center_lng, radius_m, active)
                      VALUES (:name, :type, :ref, :lat, :lng, :radius, 1)');
        $this->bind(':name', trim($d['name']));
        $this->bind(':type', in_array($d['type'] ?? '', ['office','client','territory']) ? $d['type'] : 'office');
        $this->bind(':ref', !empty($d['ref_id']) ? (int) $d['ref_id'] : null);
        $this->bind(':lat', $d['center_lat']);
        $this->bind(':lng', $d['center_lng']);
        $this->bind(':radius', !empty($d['radius_m']) ? (int) $d['radius_m'] : 200, PDO::PARAM_INT);
        return $this->execute();
    }

    public function deleteGeofence($id) {
        $this->query('DELETE FROM geofences WHERE geofence_id = :id');
        $this->bind(':id', $id);
        return $this->execute();
    }

    public function isGeofenceEnabled(): bool {
        $this->query("SELECT setting_value FROM settings WHERE setting_key = 'attendance.geofence_enabled'");
        $row = $this->single();
        return $row && (string) $row->setting_value === '1';
    }

    public function setGeofenceEnabled(bool $on): bool {
        $this->query("INSERT INTO settings (setting_key, setting_value) VALUES ('attendance.geofence_enabled', :v)
                      ON DUPLICATE KEY UPDATE setting_value = :v2");
        $this->bind(':v', $on ? '1' : '0');
        $this->bind(':v2', $on ? '1' : '0');
        return $this->execute();
    }

    // ---------------- Helpers for dropdowns ----------------

    /** Users eligible to lead/manage (managers, team leaders, admins). */
    public function getLeadershipUsers() {
        $this->query("SELECT u.user_id, u.name, r.role_name
                      FROM users u JOIN roles r ON u.role_id = r.role_id
                      WHERE r.role_name IN ('admin','manager','team_leader')
                        AND u.status = 'active'
                      ORDER BY u.name ASC");
        return $this->resultSet();
    }

    /** Employees available for team assignment. */
    public function getSalesPersons() {
        $this->query("SELECT u.user_id, u.name, e.team_id
                      FROM users u
                      JOIN roles r ON u.role_id = r.role_id
                      LEFT JOIN employees e ON u.user_id = e.user_id
                      WHERE r.role_name IN ('employee','sales_person') AND u.status = 'active'
                      ORDER BY u.name ASC");
        return $this->resultSet();
    }

    /** Retrieve assigned team members for a manager (or all if admin). */
    public function getTeamMembers($managerUserId = null) {
        $sql = "SELECT u.user_id, u.name, u.email, t.name AS team_name, mgr.name AS manager_name
                FROM users u
                JOIN roles r ON u.role_id = r.role_id
                JOIN employees e ON u.user_id = e.user_id
                LEFT JOIN teams t ON e.team_id = t.team_id
                LEFT JOIN users mgr ON e.reporting_manager_id = mgr.user_id
                WHERE r.role_name IN ('employee', 'sales_person') AND u.status = 'active'";
        
        if ($managerUserId !== null) {
            $sql .= " AND (t.manager_user_id = :mgr_id OR e.reporting_manager_id = :mgr_id2)";
        }
        
        $sql .= " ORDER BY t.name ASC, u.name ASC";
        
        $this->query($sql);
        if ($managerUserId !== null) {
            $this->bind(':mgr_id', (int) $managerUserId);
            $this->bind(':mgr_id2', (int) $managerUserId);
        }
        return $this->resultSet();
    }

    /** Retrieve all active users with role and reporting manager for organization tree. */
    public function getOrgHierarchy() {
        $this->query("SELECT u.user_id, u.name, r.role_name, e.reporting_manager_id, e.job_title, e.department, e.employee_code, e.team_id
                      FROM users u
                      JOIN roles r ON u.role_id = r.role_id
                      LEFT JOIN employees e ON u.user_id = e.user_id
                      WHERE u.status = 'active'
                      ORDER BY r.role_name ASC, u.name ASC");
        return $this->resultSet();
    }
}
