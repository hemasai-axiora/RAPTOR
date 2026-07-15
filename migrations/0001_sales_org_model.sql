-- ============================================================================
-- Sprint 1 — Sales-force org model: roles, teams, branches, territories.
-- Extends the existing Raptor CRM schema (does not drop anything).
-- ============================================================================

-- 1) New roles for the internal sales force (idempotent).
INSERT IGNORE INTO roles (role_name, description) VALUES
    ('sales_person', 'Field sales executive — self-service via responsive web app'),
    ('team_leader',  'Leads a single sales team; approves and reviews their team');

-- 2) Branches (physical offices; used for geofencing later).
CREATE TABLE IF NOT EXISTS branches (
    branch_id   INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    address     TEXT,
    lat         DECIMAL(10,7) NULL,
    lng         DECIMAL(10,7) NULL,
    status      ENUM('active','inactive') DEFAULT 'active',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Territories (geographic/market segments for targeting & routing).
CREATE TABLE IF NOT EXISTS territories (
    territory_id INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(150) NOT NULL,
    description  TEXT,
    status       ENUM('active','inactive') DEFAULT 'active',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) Teams — the core hierarchy node. A team has a leader and rolls up to a manager.
CREATE TABLE IF NOT EXISTS teams (
    team_id             INT AUTO_INCREMENT PRIMARY KEY,
    name                VARCHAR(150) NOT NULL,
    team_leader_user_id INT NULL,
    manager_user_id     INT NULL,
    branch_id           INT NULL,
    territory_id        INT NULL,
    status              ENUM('active','inactive') DEFAULT 'active',
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (team_leader_user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (manager_user_id)     REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (branch_id)           REFERENCES branches(branch_id) ON DELETE SET NULL,
    FOREIGN KEY (territory_id)        REFERENCES territories(territory_id) ON DELETE SET NULL,
    INDEX idx_teams_leader (team_leader_user_id),
    INDEX idx_teams_manager (manager_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5) RBAC permissions for the sales module (idempotent seed).
INSERT IGNORE INTO permissions (permission_name, description) VALUES
    ('view_self_dashboard',   'Sales person self dashboard'),
    ('manage_own_attendance', 'Check in/out, breaks'),
    ('manage_own_leads',      'Create/update own assigned leads'),
    ('manage_own_tasks',      'Update own tasks'),
    ('log_communications',    'Log calls/messages/emails with proof'),
    ('manage_meetings',       'Schedule and check in to meetings/demos'),
    ('view_team_monitoring',  'Team leader/manager live monitoring board'),
    ('approve_attendance',    'Approve/reject attendance exceptions'),
    ('assign_leads',          'Assign/reassign leads within scope'),
    ('manage_targets',        'Plan and approve targets'),
    ('manage_org',            'Manage teams/branches/territories');

-- 6) Map permissions to roles by name (idempotent via INSERT IGNORE on the
--    composite PK role_permissions(role_id, permission_id)).

-- sales_person: self-service set
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM roles r JOIN permissions p
WHERE r.role_name = 'sales_person'
  AND p.permission_name IN (
    'view_self_dashboard','manage_own_attendance','manage_own_leads',
    'manage_own_tasks','log_communications','manage_meetings');

-- team_leader: sales_person set + team monitoring/approval/assignment
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM roles r JOIN permissions p
WHERE r.role_name = 'team_leader'
  AND p.permission_name IN (
    'view_self_dashboard','manage_own_attendance','manage_own_leads',
    'manage_own_tasks','log_communications','manage_meetings',
    'view_team_monitoring','approve_attendance','assign_leads','manage_targets');

-- manager: team_leader set + org management
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM roles r JOIN permissions p
WHERE r.role_name = 'manager'
  AND p.permission_name IN (
    'view_team_monitoring','approve_attendance','assign_leads',
    'manage_targets','manage_org','log_communications','manage_meetings');

-- admin has implicit all-access in code (Controller::hasPermission), but grant
-- explicitly too for completeness.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM roles r JOIN permissions p
WHERE r.role_name = 'admin';
