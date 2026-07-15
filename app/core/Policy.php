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
}
