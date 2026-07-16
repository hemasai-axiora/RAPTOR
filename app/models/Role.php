<?php
/**
 * Role Model — Handles granular RBAC role and permission management.
 */
class Role extends Model {

    /**
     * Get all roles.
     */
    public function getRoles(): array {
        $this->query('SELECT * FROM roles ORDER BY is_system DESC, role_name ASC');
        return $this->resultSet() ?: [];
    }

    /**
     * Get role by ID.
     */
    public function getRoleById(int $id) {
        $this->query('SELECT * FROM roles WHERE role_id = :id');
        $this->bind(':id', $id);
        return $this->single();
    }

    /**
     * Get all permissions.
     */
    public function getPermissions(): array {
        $this->query('SELECT * FROM permissions WHERE module IS NOT NULL ORDER BY module ASC, action ASC');
        return $this->resultSet() ?: [];
    }

    /**
     * Get role permissions mapped by permission_id => scope.
     */
    public function getRolePermissionsMap(int $roleId): array {
        $this->query('SELECT permission_id, scope FROM role_permissions WHERE role_id = :rid');
        $this->bind(':rid', $roleId);
        $rows = $this->resultSet() ?: [];
        
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
        $this->query('INSERT INTO roles (role_name, description, is_system) VALUES (:n, :d, 0)');
        $this->bind(':n', $name);
        $this->bind(':d', trim($description));
        if ($this->execute()) {
            return (int) $this->lastInsertId();
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
            $this->query('UPDATE roles SET description = :d WHERE role_id = :id');
            $this->bind(':d', trim($description));
        } else {
            $name = strtolower(str_replace(' ', '_', trim($name)));
            $this->query('UPDATE roles SET role_name = :n, description = :d WHERE role_id = :id');
            $this->bind(':n', $name);
            $this->bind(':d', trim($description));
        }
        $this->bind(':id', $id);
        return $this->execute();
    }

    /**
     * Delete role.
     */
    public function deleteRole(int $id): bool {
        $role = $this->getRoleById($id);
        if (!$role || $role->is_system) return false;

        $conn = $this->db; // $this->db is PDO in Model
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
        $conn = $this->db; // $this->db is PDO in Model
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
