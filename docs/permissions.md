# Permissions & RBAC

## Overview

Qanony uses a role-based access control (RBAC) system with per-user overrides. Each user has exactly one role, and permissions are checked at the route level via `PermissionFilter`.

## Roles

### System Roles (cannot be deleted)

| ID | Role | Description |
|----|------|-------------|
| 1 | admin | Full system administrator |
| 2 | manager | Document and user manager |
| 3 | user | Standard user |

Custom roles can be created via the Roles management UI.

## Permissions

### 22 Permissions in 7 Groups

| Group | Permission | Description |
|-------|-----------|-------------|
| **users** | users.read | View user list |
| | users.create | Create new users |
| | users.update | Edit users |
| | users.delete | Delete/deactivate users |
| **roles** | roles.read | View role list |
| | roles.create | Create new roles |
| | roles.update | Edit roles and permissions |
| | roles.delete | Delete roles |
| **documents** | documents.read | View documents |
| | documents.create | Upload documents |
| | documents.update | Edit document metadata |
| | documents.delete | Delete documents |
| **search** | search.use | Use search functionality |
| **audit** | audit.read | View audit logs |
| | audit.export | Export audit logs |
| **settings** | settings.read | View system settings |
| | settings.update | Modify system settings |
| **principles** | principles.read | View legal principles |
| | principles.create | Create legal principles |
| | principles.update | Edit legal principles |
| | principles.delete | Delete legal principles |
| **defenses** | defenses.read | View defenses |
| | defenses.create | Create defenses |

## Role-Permission Matrix

| Permission | Admin | Manager | User |
|-----------|:-----:|:-------:|:----:|
| users.read | x | x | |
| users.create | x | | |
| users.update | x | | |
| users.delete | x | | |
| roles.read | x | | |
| roles.create | x | | |
| roles.update | x | | |
| roles.delete | x | | |
| documents.read | x | x | x |
| documents.create | x | x | |
| documents.update | x | x | |
| documents.delete | x | | |
| search.use | x | x | x |
| audit.read | x | x | |
| audit.export | x | | |
| settings.read | x | | |
| settings.update | x | | |
| principles.read | x | x | x |
| principles.create | x | x | |
| principles.update | x | x | |
| principles.delete | x | | |
| defenses.read | x | x | x |
| defenses.create | x | x | |

## User-Level Overrides

Individual users can have permissions granted or revoked beyond their role assignment via the `user_permissions` table:

| Field | Type | Description |
|-------|------|-------------|
| `user_id` | FK | Target user |
| `permission_id` | FK | Target permission |
| `granted` | 0/1 | 1 = grant (add to role), 0 = revoke (remove from role) |

The permission resolution order:
1. Start with all role permissions
2. Apply user-level grants (add permissions not in role)
3. Apply user-level revocations (remove permissions that are in role)

## How Permissions Are Enforced

### Route Level

Routes are protected in `Config/Routes.php`:

```php
$routes->get('users', 'UserController::index', ['filter' => 'permission:users.read']);
$routes->post('users/store', 'UserController::store', ['filter' => 'permission:users.create']);
```

### Filter Level

`PermissionFilter::before()` checks each required permission against the session's `permissions` array.

### Controller Level

Controllers can check permissions programmatically:

```php
if ($this->can('documents.delete')) {
    // allow delete
}
```

### Session Storage

On login, all resolved permissions are loaded into the session as a flat array:

```php
$_SESSION['permissions'] = ['users.read', 'documents.read', 'search.use', ...];
```

This avoids repeated DB queries during the request lifecycle.

## Admin Safety

- The system prevents deleting/deactivating the last active admin user
- System roles (is_system=1) cannot be deleted
- The `admin` role always has all permissions in the seeder
