<?php
/**
 * Role Model — Handles granular RBAC role and permission management.
 */
class Role {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Get all roles.
     */
    public function getRoles(): array {
        $this->db->query('SELECT * FROM roles ORDER BY is_system DESC, role_name ASC');
        return $this->db->resultSet() ?: [];
    }

    /**
     * Get role by ID.
     */
    public function getRoleById(int $id) {
        $this->db->query('SELECT * FROM roles WHERE role_id = :id');
        $this->db->bind(':id', $id);
        return $this->db->single();
    }

    /**
     * Get all permissions.
     */
    public function getPermissions(): array {
        $this->db->query('SELECT * FROM permissions WHERE module IS NOT NULL ORDER BY module ASC, action ASC');
        return $this->db->resultSet() ?: [];
    }

    /**
     * Get role permissions mapped by permission_id => scope.
     */
    public function getRolePermissionsMap(int $roleId): array {
        $this->db->query('SELECT permission_id, scope FROM role_permissions WHERE role_id = :rid');
        $this->db->bind(':rid', $roleId);
        $rows = $this->db->resultSet() ?: [];
        
        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row->permission_id] = $row->scope;
        }
        return $map;
    }

    /**
     * Add new role.
     */
    public function addRole(string $name, string $description): int {
        $name = strtolower(str_replace(' ', '_', trim($name)));
        $this->db->query('INSERT INTO roles (role_name, description, is_system) VALUES (:n, :d, 0)');
        $this->db->bind(':n', $name);
        $this->db->bind(':d', trim($description));
        if ($this->db->execute()) {
            return (int) $this->db->lastInsertId();
        }
        return 0;
    }

    /**
     * Update role details.
     */
    public function updateRole(int $id, string $name, string $description): bool {
        $role = $this->getRoleById($id);
        if (!$role) return false;
        
        // Cannot rename system roles
        if ($role->is_system) {
            $this->db->query('UPDATE roles SET description = :d WHERE role_id = :id');
            $this->db->bind(':d', trim($description));
        } else {
            $name = strtolower(str_replace(' ', '_', trim($name)));
            $this->db->query('UPDATE roles SET role_name = :n, description = :d WHERE role_id = :id');
            $this->db->bind(':n', $name);
            $this->db->bind(':d', trim($description));
        }
        $this->db->bind(':id', $id);
        return $this->db->execute();
    }

    /**
     * Delete role.
     */
    public function deleteRole(int $id): bool {
        $role = $this->getRoleById($id);
        if (!$role || $role->is_system) return false;

        $conn = $this->db->getConnection();
        try {
            $conn->beginTransaction();

            // 1. Delete role permissions assignments
            $stmt = $conn->prepare('DELETE FROM role_permissions WHERE role_id = :rid');
            $stmt->execute([':rid' => $id]);

            // 2. Delete role itself
            $stmt2 = $conn->prepare('DELETE FROM roles WHERE role_id = :rid');
            $stmt2->execute([':rid' => $id]);

            $conn->commit();
            return true;
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            return false;
        }
    }

    /**
     * Sync role permissions.
     * Expects permissionScopes format: [ permission_id => scope_or_null_or_empty, ... ]
     */
    public function syncPermissions(int $roleId, array $permissionScopes): bool {
        $conn = $this->db->getConnection();
        try {
            $conn->beginTransaction();

            // 1. Delete current assignments
            $stmt = $conn->prepare('DELETE FROM role_permissions WHERE role_id = :rid');
            $stmt->execute([':rid' => $roleId]);

            // 2. Insert new ones
            $stmt2 = $conn->prepare('INSERT INTO role_permissions (role_id, permission_id, scope) VALUES (:rid, :pid, :scope)');
            foreach ($permissionScopes as $pid => $scope) {
                $pid = (int) $pid;
                $scope = $scope === '' ? null : $scope;
                $stmt2->execute([
                    ':rid' => $roleId,
                    ':pid' => $pid,
                    ':scope' => $scope
                ]);
            }

            $conn->commit();
            return true;
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            return false;
        }
    }
}
