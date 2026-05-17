# INSTALL.md — Qanony Installation & Release Guide

## Table of Contents

1. [System Requirements](#1-system-requirements)
2. [Installation — Web Wizard](#2-installation--web-wizard)
3. [Installation — Manual SQL](#3-installation--manual-sql)
4. [Post-Install Notes](#4-post-install-notes)
5. [How the Update Mechanism Works](#5-how-the-update-mechanism-works)
6. [Releasing a New Version — Developer Checklist](#6-releasing-a-new-version--developer-checklist)
7. [Architecture Reference](#7-architecture-reference)
8. [Release Notes](#8-release-notes)

---

## 1. System Requirements

| Requirement | Minimum |
|-------------|---------|
| PHP | **8.2+** |
| MySQL | 8.0+ or MariaDB 10.4+ |
| Extensions | `pdo`, `pdo_mysql`, `mbstring`, `json`, `xml`, `fileinfo`, `intl` |
| Web server | Apache (mod_rewrite) or Nginx |

**Writable directories** — the web server user must be able to write to:

```
writable/
writable/cache/
writable/logs/
writable/session/
writable/uploads/
```

On Linux/macOS: `chmod -R 775 writable/`

---

## 2. Installation — Web Wizard

The recommended install path for new deployments.

### Steps

1. Copy the project to your web root (e.g. `C:\xampp\htdocs\qanony` or `/var/www/html/qanony`).
2. Create an empty MySQL database with collation `utf8mb4_unicode_ci`:
   ```sql
   CREATE DATABASE qanony CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
3. Open `http://localhost/qanony/install` in a browser.
4. Complete the **5-step wizard**:

   | Step | Name | What it does |
   |------|------|-------------|
   | 1 | المتطلبات (Requirements) | Auto-checks PHP version, required extensions, writable dirs |
   | 2 | الإعداد (Config) | Accepts site URL + DB credentials, tests the connection, writes `.env` |
   | 3 | الترحيلات (Migrations) | Creates all tables, seeds roles / permissions / default admin |
   | 4 | حساب المدير (Admin account) | Sets the admin username, email, and password |
   | 5 | إنهاء (Finalize) | Writes `writable/install.lock` — marks installation complete |

5. After step 5 completes, click **تسجيل الدخول** to go to the login page.
   - A forced password-change screen will appear on first login if you used the default credentials.

> The wizard can be re-run at any time (e.g. to apply new migrations after an upgrade).  
> Migrations and seeds are idempotent — already-applied changes are skipped automatically.

---

## 3. Installation — Manual SQL

Use this path when the web wizard is not suitable (e.g. CI/CD pipelines, restricted hosting).

1. Copy the project files to the server.
2. Import the standalone SQL dump:
   ```bash
   mysql -u root -p qanony < sql/install.sql
   ```
   The file is located at **`sql/install.sql`** (project root, outside the web root). It:
   - Creates all 13 tables in FK-safe order (runs with `FOREIGN_KEY_CHECKS=0`).
   - Inserts the `migrations` tracker rows for all 16 bundled migrations.
   - Seeds roles, permissions, role_permissions, and a default admin account.
   - Is wrapped in a transaction and is **idempotent** (`CREATE TABLE IF NOT EXISTS`, `INSERT IGNORE`).

3. Copy `.env.example` to `.env` and fill in your values:
   ```bash
   cp .env.example .env
   ```
   At minimum set:
   ```
   CI_ENVIRONMENT = production

   app.baseURL = 'https://your-domain.com/'

   database.default.hostname = 'localhost'
   database.default.database = 'qanony'
   database.default.username = 'dbuser'
   database.default.password = 'dbpassword'
   database.default.DBDriver = MySQLi
   database.default.port = 3306
   database.default.charset = utf8mb4
   database.default.DBCollat = utf8mb4_unicode_ci

   encryption.key = hex2bin:<generate with: php -r "echo bin2hex(random_bytes(32));echo PHP_EOL;">
   ```

4. Write the lock file manually to skip the wizard:
   ```bash
   echo '{"version":"1.0.0","installed_at":"'$(date -u +%F\ %T)'","last_updated_at":"'$(date -u +%F\ %T)'"}' \
     > writable/install.lock
   ```
   Or on Windows (PowerShell):
   ```powershell
   $now = (Get-Date -Format 'yyyy-MM-dd HH:mm:ss')
   '{"version":"1.0.0","installed_at":"' + $now + '","last_updated_at":"' + $now + '"}' |
     Set-Content writable\install.lock
   ```

5. Change the default admin password immediately after first login.

---

## 4. Post-Install Notes

### Environment

The wizard writes `CI_ENVIRONMENT = production` to `.env`. Keep it as `production` on live servers.  
Set it to `development` **only** on local machines for verbose error pages — never on production.

### Default credentials

| Field | Value |
|-------|-------|
| Username | `admin` |
| Password | `Admin@123` |
| Email | `admin@qanony.local` |

A **forced password-change** is triggered on first login (`force_password_change = 1`).  
Step 4 of the wizard overwrites all three fields with the values you provide.

### Writable directory permissions

```bash
# Linux
chmod -R 775 writable/
chown -R www-data:www-data writable/   # adjust to your web-server user
```

---

## 5. How the Update Mechanism Works

### install.lock

After a successful install, the file `writable/install.lock` is written:

```json
{
    "version": "1.0.0",
    "installed_at": "2026-05-16 12:00:00",
    "last_updated_at": "2026-05-16 12:00:00"
}
```

This file is the **single source of truth** for installation state.

### InstallCheckFilter (global)

Every request passes through `App\Filters\InstallCheckFilter` before reaching any controller:

| Lock state | Action |
|------------|--------|
| No lock file | Redirect to `GET /install` |
| Lock version < `APP_VERSION` | Redirect to `GET /update` |
| Lock version >= `APP_VERSION` | Pass through normally |

Routes under `/install*` and `/update*` are always bypassed so the wizard itself is always reachable.

### Update page (`GET /update`)

Displays the installed version vs the latest version defined in `AppVersion::APP_VERSION`, then lets the admin click **تطبيق التحديث**. This POSTs to `/update/run` which:

1. Runs `$migrate->latest()` (all pending migrations).
2. Bumps `install.lock` to the new version.

---

## 6. Releasing a New Version — Developer Checklist

Follow these steps every time you ship a release:

### Step 1 — Write your migration(s)

Create one or more migration files under `app/Database/Migrations/`:

```
app/Database/Migrations/YYYY-MM-DD-HHmmss_DescriptionOfChange.php
```

Name the file clearly so it's obvious what it does.

### Step 2 — Bump the version in AppVersion.php

Open `app/Config/AppVersion.php` and increment `APP_VERSION` following [SemVer](https://semver.org/):

```php
public const APP_VERSION = '1.1.0';  // was '1.0.0'
```

| Change type | Version bump |
|-------------|-------------|
| Bug fix / minor patch | `1.0.0` → `1.0.1` |
| New feature (backwards-compatible) | `1.0.0` → `1.1.0` |
| Breaking change | `1.0.0` → `2.0.0` |

### Step 3 — Add a Release Notes entry (this file)

Scroll to [Section 8 — Release Notes](#8-release-notes) below and add a new entry at the **top** of the list:

```markdown
### v1.1.0 — 2026-MM-DD

**What's new**
- Brief description of the feature or fix.

**Migrations added**
- `YYYY-MM-DD-HHmmss_DescriptionOfChange.php` — what it does

**Manual steps required** *(if any)*
- e.g. "Run `php spark cache:clear` after updating"
```

### Step 4 — Deploy & update

1. Deploy the updated code to the server.
2. Open `http://your-domain/update` in a browser (or it will redirect there automatically on the next page load).
3. Click **تطبيق التحديث** — the wizard runs all pending migrations and bumps the lock.

### Step 5 — Verify

Run the test suite to confirm nothing is broken:

```bash
C:\xampp\php\php.exe vendor\bin\phpunit --no-coverage
```

Expected: all tests pass (currently 260/260).

---

## 7. Architecture Reference

### Key files

| File | Role |
|------|------|
| `app/Config/AppVersion.php` | `APP_VERSION` constant, `checkRequirements()`, lock file helpers |
| `app/Filters/InstallCheckFilter.php` | Global before-filter — auto-redirects to /install or /update |
| `app/Controllers/InstallController.php` | AJAX 5-step install wizard endpoints |
| `app/Controllers/UpdateController.php` | Update page + `POST /update/run` |
| `app/Views/install/wizard.php` | Install wizard UI (step bar + 5 panels, full AJAX) |
| `app/Views/install/update.php` | Update page UI |
| `app/Config/Filters.php` | Registers `install_check` alias + adds it to `$globals['before']` |
| `app/Config/Routes.php` | `/install`, `POST /install/step/:name`, `GET /update`, `POST /update/run` |
| `app/Database/Migrations/` | All migration files (CI4 naming: `YYYY-MM-DD-HHmmss_Name.php`) |
| `app/Database/Seeds/InitialSeeder.php` | Seeds roles, permissions, admin user (idempotent) |
| `sql/install.sql` | Standalone SQL dump — alternative to the web wizard |
| `.env.example` | Template for the `.env` configuration file |
| `writable/install.lock` | JSON lock file — created by wizard, read by filter |

### Install wizard AJAX endpoints

| Route | Method | Description |
|-------|--------|-------------|
| `GET  /install` | — | Render wizard HTML page |
| `POST /install/step/requirements` | `stepRequirements()` | Check PHP version, extensions, writable dirs |
| `POST /install/step/config` | `stepConfig()` | Accept site URL + DB creds, test connection, write `.env` |
| `POST /install/step/migrate` | `stepMigrate()` | Run migrations + seed |
| `POST /install/step/admin` | `stepAdmin()` | Set admin username, email, password |
| `POST /install/step/finalize` | `stepFinalize()` | Write install.lock |

### Update endpoints

| Route | Method | Description |
|-------|--------|-------------|
| `GET  /update` | `index()` | Show version comparison page |
| `POST /update/run` | `run()` | Run migrations + bump lock |

### Test setup note

`tests/feature/ControllerTest.php::setUp()` strips `install_check` (and `csrf`) from the global before-filters so tests run without a lock file being present. This is the correct pattern — do not remove it.

---

## 8. Release Notes

> Add new entries at the TOP of this section. Oldest entries go to the bottom.

---

### v1.0.0 — 2026-05-16

**Initial release**

**Features included**
- Legal document management system (upload, index, full-text search)
- Bulk import pipeline: `docs:import` + `docs:import-one` CLI commands with subprocess isolation
- HTML document preview (LibreOffice → PhpWord pipeline, Arabic text support)
- Search: FULLTEXT + `file_name` LIKE fallback, suggestions, scope filtering
- Upload queue monitor (`upload_queue` table, `QueueProcessorService`)
- RBAC: roles, permissions, role_permissions, per-user overrides
- Audit log
- Search scopes (virtual folder tree) with per-scope access control
- PWA: `manifest.json` + service worker v2
- RTL-first Bootstrap 5 UI (Navy #1a5276 theme, force light mode)
- Setup wizard (5-step AJAX) + update wizard
- `InstallCheckFilter` — global auto-redirect guard

**Migrations included (16)**

| File | Creates |
|------|---------|
| `2026-02-28-180001_CreateRolesTable` | `roles` |
| `2026-02-28-180002_CreatePermissionsTable` | `permissions` |
| `2026-02-28-180003_CreateRolePermissionsTable` | `role_permissions` |
| `2026-02-28-180004_CreateUsersTable` | `users` |
| `2026-02-28-180005_CreateUserPermissionsTable` | `user_permissions` |
| `2026-02-28-180006_CreateLegalDocumentsTable` | `legal_documents` |
| `2026-02-28-180007_CreateLegalPrinciplesTable` | `legal_principles` |
| `2026-02-28-180008_CreateDefensesTable` | `defenses` |
| `2026-02-28-180009_CreateAuditLogsTable` | `audit_logs` |
| `2026-02-28-180010_CreateSessionsTable` | `sessions` |
| `2026-03-04-180001_CreateSearchScopesTable` | `search_scopes` |
| `2026-03-04-180002_AddScopeAndCountsToDocuments` | alters `legal_documents` |
| `2026-03-04-180003_OptimizeFulltextIndex` | FULLTEXT index on `legal_documents` |
| `2026-04-23-100001_AddScopeAccessControl` | `scope_access` |
| `2026-04-23-100002_AddScopeManagePermission` | inserts `scopes.manage` permission |
| `2026-04-23-200001_CreateUploadQueue` | `upload_queue` |

**Default credentials**

| Field | Value |
|-------|-------|
| Username | `admin` |
| Password | `Admin@123` |
| Email | `admin@qanony.local` |

Password change is forced on first login.

---

*To add a new release entry, follow the checklist in [Section 6](#6-releasing-a-new-version--developer-checklist).*
