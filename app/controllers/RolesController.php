<?php
/**
 * RolesController — Manage RBAC roles and permissions matrix.
 */
class RolesController extends Controller {
    private $roleModel;

    public function __construct() {
        $this->requireAuth();
        $this->requirePermission('roles', 'manage');
        $this->roleModel = $this->model('Role');
    }

    /**
     * List roles and display add role form.
     */
    public function index() {
        $roles = $this->roleModel->getRoles();
        $data = [
            'title' => 'Roles & Permissions | Raptor CRM',
            'active_tab' => 'settings',
            'roles' => $roles
        ];
        $this->viewWithLayout('roles/index', 'main', $data);
    }

    /**
     * Create a new custom role.
     */
    public function add() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
            $name = trim($_POST['role_name'] ?? '');
            $desc = trim($_POST['description'] ?? '');

            if ($name !== '') {
                $roleId = $this->roleModel->addRole($name, $desc);
                if ($roleId > 0) {
                    $this->audit("Created role: {$name}", 'roles', $roleId);
                    $_SESSION['roles_success'] = 'Role created successfully.';
                } else {
                    $_SESSION['roles_error'] = 'Failed to create role. It might already exist.';
                }
            } else {
                $_SESSION['roles_error'] = 'Role name is required.';
            }
        }
        $this->redirect('index.php?route=roles/index');
    }

    /**
     * Edit a role and manage its permission matrix.
     */
    public function edit($id = null) {
        $id = (int)$id;
        $role = $this->roleModel->getRoleById($id);
        if (!$role) {
            $this->redirect('index.php?route=roles/index');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
            
            // 1. Update role description/name
            $name = trim($_POST['role_name'] ?? $role->role_name);
            $desc = trim($_POST['description'] ?? '');
            $this->roleModel->updateRole($id, $name, $desc);

            // 2. Process permission scopes matrix
            // Format received from form: permission_scopes[permission_id] = 'own'|'team'|'all'|'none'
            $submitted = $_POST['permission_scopes'] ?? [];
            $toSync = [];
            foreach ($submitted as $pid => $scope) {
                if ($scope !== 'none') {
                    $toSync[(int)$pid] = $scope;
                }
            }

            if ($this->roleModel->syncPermissions($id, $toSync)) {
                $this->audit("Updated permissions for role: {$role->role_name}", 'roles', $id);
                $_SESSION['roles_success'] = 'Permissions updated successfully.';
            } else {
                $_SESSION['roles_error'] = 'Failed to update permissions.';
            }
            $this->redirect('index.php?route=roles/edit/' . $id);
            return;
        }

        $permissions = $this->roleModel->getPermissions();
        $rolePerms = $this->roleModel->getRolePermissionsMap($id);

        // Group permissions by module for easier tab display in UI
        $groupedPerms = [];
        foreach ($permissions as $p) {
            $groupedPerms[$p->module][] = $p;
        }

        $data = [
            'title' => 'Configure Role: ' . ucwords(str_replace('_', ' ', $role->role_name)) . ' | Raptor CRM',
            'active_tab' => 'settings',
            'role' => $role,
            'grouped_permissions' => $groupedPerms,
            'role_permissions' => $rolePerms
        ];

        $this->viewWithLayout('roles/edit', 'main', $data);
    }

    /**
     * Delete custom role.
     */
    public function delete($id = null) {
        $id = (int)$id;
        $role = $this->roleModel->getRoleById($id);
        if (!$role) {
            $this->redirect('index.php?route=roles/index');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($role->is_system) {
                $_SESSION['roles_error'] = 'System-protected roles cannot be deleted.';
            } else {
                if ($this->roleModel->deleteRole($id)) {
                    $this->audit("Deleted role: {$role->role_name}", 'roles', $id);
                    $_SESSION['roles_success'] = 'Role deleted successfully.';
                } else {
                    $_SESSION['roles_error'] = 'Failed to delete role. It may be assigned to active users.';
                }
            }
        }
        $this->redirect('index.php?route=roles/index');
    }
}
