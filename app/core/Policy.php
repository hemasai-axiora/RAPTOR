<?php
// Central role and governance helpers.

class Policy {
    public static function role(): string {
        return $_SESSION['user_role'] ?? '';
    }

    public static function isAdmin(): bool {
        return self::role() === 'admin';
    }

    public static function isHr(): bool {
        return self::role() === 'hr';
    }

    public static function isFinance(): bool {
        return self::role() === 'finance';
    }

    public static function isManager(): bool {
        return in_array(self::role(), ['manager', 'team_leader'], true);
    }

    public static function isEmployee(): bool {
        return in_array(self::role(), ['employee', 'sales_person'], true);
    }

    public static function canManageEmployees(): bool {
        return self::isAdmin() || self::isHr();
    }

    public static function canRequestDataEdit(): bool {
        return self::role() === 'manager';
    }

    public static function canApproveDataEdit(): bool {
        return self::isAdmin();
    }

    public static function canCreateDashboardTemplate(): bool {
        return in_array(self::role(), ['admin', 'analyst'], true);
    }

    public static function roleLabel(?string $role = null): string {
        $role = $role ?? self::role();
        if ($role === 'sales_person') {
            return 'Employee';
        }
        return ucwords(str_replace('_', ' ', $role));
    }

    // ── Granular permission helpers (delegate to PermissionService) ──────────

    /**
     * Check if the current user has a module.action permission.
     * Optionally pass a record object for own/team scope validation.
     *
     * Example: Policy::can('invoices', 'view')
     *          Policy::can('attendance', 'approve', $record)
     */
    public static function can(string $module, string $action, ?object $record = null): bool {
        return PermissionService::can($module, $action, $record);
    }

    /**
     * Returns true if the user has ANY of the given module.action pairs.
     * @param array $checks [ ['module', 'action'], ... ]
     */
    public static function canAny(array $checks): bool {
        return PermissionService::canAny($checks);
    }

    /**
     * Convenience: is the user admin or finance?
     */
    public static function isFinanceOrAdmin(): bool {
        return self::isAdmin() || self::isFinance();
    }

    /**
     * Convenience: is the user admin or HR?
     */
    public static function isHrOrAdmin(): bool {
        return self::isAdmin() || self::isHr();
    }
}
