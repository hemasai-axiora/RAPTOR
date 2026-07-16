# Role-Based Access Control (RBAC) System Developer Guide

This document describes the structure, usage, and design of the granular Role-Based Access Control (RBAC) system implemented in Raptor CRM.

---

## 1. Core Architecture

The access control model is built on top of four main tables:
1. **`roles`**: Defines system roles (e.g., `admin`, `manager`, `hr`, `finance`, `analyst`, `employee`).
2. **`permissions`**: Defines specific actions on system modules (e.g., module: `leads`, action: `delete`, description: `Delete campaign leads`).
3. **`role_permissions`**: Maps roles to permissions, detailing the permission's `scope` (`own`, `team`, `all`).
4. **`user_permission_overrides`**: Allows explicit grants or revokes at the individual user level, overriding the role default scope.

---

## 2. Using the Permission Helpers in PHP

All controllers inherit from the base `Controller` class, which offers utility check wrappers.

### Enforcing Controller Actions (`requirePermission`)

Inside any controller constructor or individual method, invoke `$this->requirePermission($module, $action, $record = null)` to abort with a `403 Access Denied` screen if the user is unauthorized.

```php
// constructor
public function __construct() {
    $this->requireAuth();
    $this->requirePermission('invoices', 'view'); // User needs invoices.view permission
}

// Scoped action check (e.g. edit a lead)
public function edit($id) {
    $lead = $this->leadModel->getLeadById($id);
    // Enforce own/team/all scope based on ownership
    $this->requirePermission('leads', 'edit', $lead);
}
```

### Inline Gating (`Policy::can`)

For layout/view files where elements (buttons, menus, links) should be conditionally shown, use `Policy::can($module, $action, $record = null)`:

```php
<?php if (Policy::can('employees', 'create')): ?>
    <button class="btn btn-primary">Add Employee</button>
<?php endif; ?>
```

---

## 3. Scopes & Record Ownership Resolution

When checking permission scopes (`own`, `team`, `all`):
- **`all`**: User can perform the action on any record in the system.
- **`team`**: User can perform the action on records belonging to their team members or descendants (resolved recursively via `PermissionService::getTeamUserIds()`).
- **`own`**: User can perform the action only on records where they are the owner/creator.

To check scopes against records, pass the record object or associative array to the helpers. The ownership resolution logic automatically inspects the following properties of the record:
- `user_id`
- `created_by`
- `created_by_user_id`
- `assigned_to`
- `assigned_to_user_id`

---

## 4. User-Level Overrides

If a user needs temporary promotion or demotion without shifting their primary role, administrators can set **User Overrides** under `Settings > User Access Control`. Overrides support:
- **`Inherit`**: Defaults to their primary role configuration.
- **`Revoke`**: Explicitly blocks access, even if the role permits it.
- **`Grant Own` / `Grant Team` / `Grant All`**: Promotes the user to a custom scope for this action.

---

## 5. Audit Trail

All administrative and security changes (creating roles, modifying permissions matrix, updating user overrides, deactivating accounts) are automatically logged in `activity_logs`. The log contains:
- User ID & Role
- Action description
- Target component / table
- Record ID affected
- JSON metadata payload detailing the specific database changes
