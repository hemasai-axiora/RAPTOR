<?php
/**
 * PermissionService — Central RBAC enforcement for Raptor CRM.
 *
 * Usage:
 *   PermissionService::can('invoices', 'view')             // current user
 *   PermissionService::can('payroll', 'view', $record)     // with scope check
 *   PermissionService::can('employees', 'edit', null, $uid) // explicit user
 *   PermissionService::scope('attendance', 'view')          // returns 'own'|'team'|'all'|null
 */
class PermissionService {

    /**
     * Check if the current (or given) user has a specific module.action permission.
     *
     * @param string       $module    e.g. 'invoices'
     * @param string       $action    e.g. 'view'
     * @param object|null  $record    The target record (for own/team scope validation)
     * @param int|null     $userId    Explicit user ID (defaults to $_SESSION['user_id'])
     * @return bool
     */
    public static function can(string $module, string $action, $record = null, ?int $userId = null): bool {
        // Must be logged in
        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        $uid  = $userId ?? (int) $_SESSION['user_id'];
        $role = $_SESSION['user_role'] ?? '';

        // Fetch latest role and status for real-time access updates
        try {
            $db = Database::getInstance()->getConnection();
            $stmtUser = $db->prepare('SELECT u.status, r.role_name, u.role_id FROM users u JOIN roles r ON u.role_id = r.role_id WHERE u.user_id = :uid LIMIT 1');
            $stmtUser->execute([':uid' => $uid]);
            $uRow = $stmtUser->fetch(PDO::FETCH_OBJ);
            if ($uRow) {
                if ($uRow->status === 'suspended') {
                    return false;
                }
                $role = strtolower($uRow->role_name);
                if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $uid) {
                    $_SESSION['user_role'] = $role;
                    $_SESSION['role_name'] = $role;
                    $_SESSION['role_id'] = (int)$uRow->role_id;
                }
            }
        } catch (Throwable $e) {
            // Fallback to session values
        }

        // Suspended users have no access
        if (($_SESSION['user_status'] ?? 'active') === 'suspended') {
            return false;
        }

        // Admins and CEO bypass all permission checks
        if (in_array($role, ['admin', 'ceo'], true)) {
            return true;
        }

        // Check user-level explicit overrides first (real-time Admin settings changes)
        try {
            $stmtOv = $db->prepare(
                'SELECT upo.scope, upo.type
                 FROM user_permission_overrides upo
                 JOIN permissions p ON upo.permission_id = p.permission_id
                 WHERE upo.user_id = :uid AND p.module = :mod AND (p.action = :act OR p.permission_name = :pname)
                 LIMIT 1'
            );
            $stmtOv->execute([':uid' => $uid, ':mod' => $module, ':act' => $action, ':pname' => $module . '.' . $action]);
            $ov = $stmtOv->fetch(PDO::FETCH_OBJ);
            if ($ov) {
                if ($ov->type === 'revoke') {
                    return false;
                }
                if ($ov->type === 'grant') {
                    return true;
                }
            }
        } catch (Throwable $e) {
            // Ignore DB error and proceed to role defaults
        }

        // Operational roles default modules check
        if (in_array($role, ['employee', 'sales_person', 'manager', 'team_leader', 'hr', 'finance', 'analyst'], true)) {
            $opModules = [
                'dashboard', 'attendance', 'leaves', 'leave', 'calendar', 'targets', 
                'performance', 'crm_leads', 'leads', 'customers', 'communications', 
                'meetings', 'followups', 'tasks', 'campaigns', 'social_media', 
                'payroll', 'reports', 'notifications', 'hrms', 'location', 'clients', 'invoices', 'editrequests'
            ];
            if (in_array($module, $opModules, true)) {
                return true;
            }
        }

        // Load permissions from database for active role
        $roleId = (int) ($_SESSION['role_id'] ?? 0);
        $perms = self::loadForUser($uid, $roleId);
        $_SESSION['rbac_permissions'] = $perms;

        $permKey = $module . '.' . $action;

        // Check if the user has this permission or an alias module permission
        if (!array_key_exists($permKey, $perms)) {
            $aliases = [
                'crm_leads' => ['leads', 'crm_leads'],
                'leads'     => ['crm_leads', 'leads'],
                'customers' => ['customers', 'crm_leads', 'leads'],
            ];
            $found = false;
            if (isset($aliases[$module])) {
                foreach ($aliases[$module] as $altMod) {
                    $altKey = $altMod . '.' . $action;
                    if (array_key_exists($altKey, $perms)) {
                        $permKey = $altKey;
                        $found = true;
                        break;
                    }
                }
            }
            if (!$found) {
                return false;
            }
        }

        $scope = $perms[$permKey] ?? null;

        // No record provided or scope is 'all'/null/empty → permission is granted
        if ($record === null || $scope === 'all' || $scope === null || $scope === '') {
            return true;
        }

        // Scope = 'own': record must belong to this user
        if ($scope === 'own') {
            $ownerId = is_array($record)
                ? ($record['user_id'] ?? $record['created_by'] ?? $record['assigned_to'] ?? $record['assigned_to_user_id'] ?? null)
                : (is_object($record) ? ($record->user_id ?? $record->created_by ?? $record->assigned_to ?? $record->assigned_to_user_id ?? null) : null);
            return $ownerId === null || (int) $ownerId === $uid;
        }

        // Scope = 'team': record owner must be in this user's team
        if ($scope === 'team') {
            $ownerId = is_array($record)
                ? ($record['user_id'] ?? $record['created_by'] ?? $record['assigned_to'] ?? $record['assigned_to_user_id'] ?? null)
                : (is_object($record) ? ($record->user_id ?? $record->created_by ?? $record->assigned_to ?? $record->assigned_to_user_id ?? null) : null);
            if ($ownerId === null) return true;
            if ((int) $ownerId === $uid) return true;
            return in_array((int) $ownerId, self::getTeamUserIds($uid), true);
        }

        return true;
    }

    /**
     * Return the scope for this user's permission on a module.action.
     * Returns null if user does not have the permission at all.
     */
    public static function scope(string $module, string $action): ?string {
        if (!isset($_SESSION['user_id'])) return null;
        if (($_SESSION['user_role'] ?? '') === 'admin') return 'all';
        $perms = $_SESSION['rbac_permissions'] ?? [];
        $key   = $module . '.' . $action;
        return array_key_exists($key, $perms) ? $perms[$key] : null;
    }

    /**
     * Check multiple module.action pairs — returns true if ANY match.
     *
     * @param array $checks [ ['module', 'action'], ... ]
     */
    public static function canAny(array $checks): bool {
        foreach ($checks as [$mod, $act]) {
            if (self::can($mod, $act)) return true;
        }
        return false;
    }

    /**
     * Check multiple module.action pairs — returns true only if ALL match.
     *
     * @param array $checks [ ['module', 'action'], ... ]
     */
    public static function canAll(array $checks): bool {
        foreach ($checks as [$mod, $act]) {
            if (!self::can($mod, $act)) return false;
        }
        return true;
    }

    /**
     * Load the full effective permissions for a user into a key=>scope map.
     * Merges role permissions with per-user overrides.
     *
     * Format returned: ['module.action' => 'scope_or_null', ...]
     *
     * @param  int    $userId
     * @param  int    $roleId
     * @return array
     */
    public static function loadForUser(int $userId, int $roleId): array {
        try {
            $db = Database::getInstance()->getConnection();

            // 1. Load role base permissions
            $stmt = $db->prepare(
                'SELECT p.module, p.action, rp.scope
                 FROM role_permissions rp
                 JOIN permissions p ON rp.permission_id = p.permission_id
                 WHERE rp.role_id = :rid AND p.module IS NOT NULL'
            );
            $stmt->execute([':rid' => $roleId]);
            $rows = $stmt->fetchAll(PDO::FETCH_OBJ);

            $perms = [];
            foreach ($rows as $row) {
                $key = $row->module . '.' . $row->action;
                $perms[$key] = $row->scope;
            }

            // 2. Apply per-user overrides
            $stmt = $db->prepare(
                'SELECT p.module, p.action, upo.scope, upo.type
                 FROM user_permission_overrides upo
                 JOIN permissions p ON upo.permission_id = p.permission_id
                 WHERE upo.user_id = :uid AND p.module IS NOT NULL'
            );
            $stmt->execute([':uid' => $userId]);
            $overrides = $stmt->fetchAll(PDO::FETCH_OBJ);

            foreach ($overrides as $ov) {
                $key = $ov->module . '.' . $ov->action;
                if ($ov->type === 'revoke') {
                    unset($perms[$key]); // Explicit revoke removes permission
                } else {
                    $perms[$key] = $ov->scope; // Explicit grant (possibly with custom scope)
                }
            }

            // 3. Ensure employee and sales_person roles have default CRM module access
            $stmtRole = $db->prepare('SELECT role_name FROM roles WHERE role_id = :rid LIMIT 1');
            $stmtRole->execute([':rid' => $roleId]);
            $rName = strtolower($stmtRole->fetchColumn() ?: '');

            if (in_array($rName, ['employee', 'sales_person'], true)) {
                $defaults = [
                    'dashboard.view' => 'all',
                    'attendance.view' => 'own',
                    'attendance.create' => 'own',
                    'leaves.view' => 'own',
                    'leaves.create' => 'own',
                    'leave.view' => 'own',
                    'leave.create' => 'own',
                    'calendar.view' => 'all',
                    'targets.view' => 'own',
                    'performance.view' => 'own',
                    'crm_leads.view' => 'own',
                    'crm_leads.create' => 'own',
                    'crm_leads.edit' => 'own',
                    'leads.view' => 'own',
                    'leads.create' => 'own',
                    'leads.edit' => 'own',
                    'customers.view' => 'all',
                    'customers.create' => 'own',
                    'communications.view' => 'own',
                    'communications.create' => 'own',
                    'meetings.view' => 'own',
                    'meetings.create' => 'own',
                    'followups.view' => 'own',
                    'followups.create' => 'own',
                    'tasks.view' => 'own',
                    'tasks.edit' => 'own',
                    'social_media.view' => 'own',
                    'social_media.create' => 'own',
                    'payroll.view' => 'own',
                    'reports.view' => 'own',
                    'notifications.view' => 'own',
                    'hrms.view' => 'own',
                ];
                foreach ($defaults as $dk => $ds) {
                    if (!isset($perms[$dk])) {
                        $perms[$dk] = $ds;
                    }
                }
            }

            return $perms;
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Get IDs of users in the current user's team (direct reports + team members).
     * Used for 'team' scope checks.
     */
    public static function getTeamUserIds(int $managerId): array {
        static $cache = [];
        if (isset($cache[$managerId])) return $cache[$managerId];

        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare(
                "SELECT DISTINCT e.user_id
                 FROM employees e
                 LEFT JOIN teams t ON e.team_id = t.team_id
                 WHERE e.reporting_manager_id = :uid
                    OR t.team_leader_user_id = :uid2
                    OR t.manager_user_id = :uid3"
            );
            $stmt->execute([':uid' => $managerId, ':uid2' => $managerId, ':uid3' => $managerId]);
            $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
            $ids[] = $managerId; // include self
            $cache[$managerId] = array_values(array_unique($ids));
            return $cache[$managerId];
        } catch (Throwable $e) {
            return [$managerId];
        }
    }

    /**
     * Return a human-readable list of a user's effective permissions for display.
     * Format: [ ['module' => '...', 'action' => '...', 'scope' => '...', 'source' => 'role'|'override'] ]
     */
    public static function getEffectivePermissionDetails(int $userId, int $roleId): array {
        try {
            $db = Database::getInstance()->getConnection();

            $stmt = $db->prepare(
                'SELECT p.module, p.action, p.description, rp.scope, "role" as source
                 FROM role_permissions rp
                 JOIN permissions p ON rp.permission_id = p.permission_id
                 WHERE rp.role_id = :rid AND p.module IS NOT NULL
                 ORDER BY p.module, p.action'
            );
            $stmt->execute([':rid' => $roleId]);
            $base = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $db->prepare(
                'SELECT p.module, p.action, p.description, upo.scope, upo.type as source
                 FROM user_permission_overrides upo
                 JOIN permissions p ON upo.permission_id = p.permission_id
                 WHERE upo.user_id = :uid AND p.module IS NOT NULL
                 ORDER BY p.module, p.action'
            );
            $stmt->execute([':uid' => $userId]);
            $overrides = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Merge into base (overrides win)
            $merged = [];
            foreach ($base as $row) {
                $key = $row['module'] . '.' . $row['action'];
                $merged[$key] = $row;
            }
            foreach ($overrides as $ov) {
                $key = $ov['module'] . '.' . $ov['action'];
                if ($ov['source'] === 'revoke') {
                    unset($merged[$key]);
                } else {
                    $merged[$key] = $ov;
                }
            }

            ksort($merged);
            return array_values($merged);
        } catch (Throwable $e) {
            return [];
        }
    }
}
