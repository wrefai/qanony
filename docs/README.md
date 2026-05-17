# Qanony (قانوني) — Legal Intelligence System

A web-based legal document management and search system for Arabic/Kuwaiti legal documents, migrated from a C# WPF desktop application to PHP CodeIgniter 4.7.0.

## Features

### Core (migrated from original)
- **Document Management** — Upload, index, and manage DOCX/DOC legal documents
- **Full-Text Search** — MySQL FULLTEXT boolean-mode search across titles, body text, normalized text, and keywords
- **Arabic Text Normalization** — Alef variants, Taa Marbuta, Alef Maqsura normalization + tashkeel stripping
- **Arabic Synonym Expansion** — 59 legal synonym groups across 13 domains (courts, lawsuits, rulings, etc.)
- **SHA-256 Deduplication** — Prevents uploading duplicate documents
- **Document Type Filtering** — Filter by type (ruling, memorandum, law, regulation, legal opinion, contract)
- **Court Level Filtering** — Filter by court (first instance, appeal, tamyeez, administrative, etc.)
- **Date Range Filtering** — Filter documents by date
- **Dashboard Statistics** — Document counts, type/court breakdowns, recent activity
- **Light/Dark Themes** — Toggle between light and dark UI themes
- **Legal Principles** — Extract and manage legal principles from documents
- **Defenses** — Link defense strategies to legal principles

### New (web-specific)
- **Authentication** — Session-based login with bcrypt password hashing (cost 12)
- **Role-Based Access Control (RBAC)** — 3 default roles (admin/manager/user), 22 permissions in 7 groups
- **User Management** — Create, edit, activate/deactivate, reset passwords
- **Audit Logging** — Track all user actions with IP, user agent, timestamps
- **Account Lockout** — 5 failed login attempts locks account for 15 minutes
- **IP Rate Limiting** — 10 POST attempts per 5-minute window per IP
- **Arabic/English i18n** — Arabic-first RTL interface with instant English toggle

## Requirements

| Component | Version |
|-----------|---------|
| PHP | >= 8.2 |
| MariaDB/MySQL | >= 10.4 / >= 8.0 |
| Composer | >= 2.0 |
| PHP Extensions | zip, intl, gd, mbstring, mysqli, pdo_mysql, openssl, curl, bcmath |

## Installation

### 1. Clone / Extract

Place the project at your web root (e.g., `C:\xampp\htdocs\qanony`).

### 2. Install Dependencies

```bash
composer install
```

### 3. Configure Environment

Copy and edit the environment file:

```bash
cp env .env
```

Edit `.env` with your database credentials:

```ini
CI_ENVIRONMENT = production

app.baseURL = 'http://localhost/qanony/public/'

database.default.hostname = localhost
database.default.database = qanony
database.default.username = root
database.default.password =
database.default.DBDriver = MySQLi
database.default.charset  = utf8mb4
database.default.DBCollat = utf8mb4_unicode_ci
```

### 4. Create Database

```sql
CREATE DATABASE qanony CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 5. Run Migrations

```bash
php spark migrate
```

### 6. Seed Default Data

```bash
php spark db:seed InitialSeeder
```

This creates:
- 3 roles: admin, manager, user
- 22 permissions in 7 groups
- Default admin user (username: `admin`, password: `Admin@456!`)

### 7. Configure Web Server

**Apache** (XAMPP): Ensure `mod_rewrite` is enabled. The `public/.htaccess` handles URL rewriting.

**CI4 Development Server**:
```bash
php spark serve --port=8080
```

### 8. PHP Extensions

Ensure these extensions are enabled in `php.ini`:
```ini
extension=zip
extension=intl
extension=gd
```

Restart Apache after enabling extensions.

## Default Admin Account

| Field | Value |
|-------|-------|
| Username | admin |
| Password | Admin@456! |

On first login, you may be prompted to change your password (if `force_password_change` is enabled in the database).

## Running Tests

```bash
# Run all tests
composer test

# Or directly
vendor/bin/phpunit --no-coverage

# Run specific test suite
vendor/bin/phpunit --no-coverage tests/unit/
vendor/bin/phpunit --no-coverage tests/database/
vendor/bin/phpunit --no-coverage tests/feature/
```

Current status: **260 tests, 1028 assertions — all passing**.

## Project Structure

```
qanony/
├── app/
│   ├── Config/          # App, Database, Routes, Filters, Security config
│   ├── Controllers/     # 8 controllers + BaseController
│   ├── Database/
│   │   ├── Migrations/  # 10 migration files
│   │   └── Seeds/       # InitialSeeder
│   ├── Filters/         # AuthFilter, PermissionFilter, RateLimitFilter
│   ├── Language/
│   │   ├── ar/          # Arabic translations
│   │   └── en/          # English translations
│   ├── Libraries/       # ArabicTextNormalizer, ArabicSynonymDictionary, DocumentParser
│   ├── Models/          # 7 models
│   └── Views/           # 2 layouts + 14 page views
├── docs/                # Project documentation
├── public/
│   ├── assets/
│   │   ├── css/app.css  # RTL/LTR + theme styles
│   │   └── js/app.js    # AJAX, toast, CSRF, theme toggle
│   └── index.php        # Front controller
├── tests/
│   ├── unit/            # Library + Filter tests
│   ├── database/        # Model tests
│   ├── feature/         # Controller/integration tests
│   └── _support/        # Test helpers
├── writable/
│   └── uploads/documents/  # Document upload storage
├── .env                 # Environment config (not committed)
├── composer.json
└── phpunit.xml.dist
```

## Documentation

- [Architecture](architecture.md) — System design, layers, data flow
- [Permissions](permissions.md) — RBAC model, roles, permission matrix
- [Security](security.md) — Security measures and hardening
- [Decisions](decisions.md) — Technical decision log with rationale
