# Architecture

## Overview

Qanony follows CodeIgniter 4's MVC architecture with additional layers for security (filters), domain logic (libraries), and access control (RBAC).

## Stack

| Layer | Technology |
|-------|------------|
| Framework | CodeIgniter 4.7.0 |
| Language | PHP 8.2 |
| Database | MariaDB 10.4 (utf8mb4_unicode_ci) |
| UI | Bootstrap 5 RTL + Vanilla JS |
| Document Parsing | PhpOffice/PhpWord 1.4.0 |
| Testing | PHPUnit 10.5 |

## Request Lifecycle

```
Browser Request
    → public/index.php (front controller)
    → Routing (Config/Routes.php)
    → Global Filters (CSRF, InvalidChars)
    → Route-specific Filters (Auth, Permission, RateLimit)
    → Controller
    → Model (database)
    → View (HTML response)
    → After Filters (SecureHeaders)
    → Response
```

## Layers

### 1. Filters (app/Filters/)

Filters intercept requests before/after controllers execute.

| Filter | Type | Purpose |
|--------|------|---------|
| `AuthFilter` | before | Session authentication check + force password change redirect |
| `PermissionFilter` | before | RBAC permission enforcement |
| `RateLimitFilter` | before | IP-based login brute-force protection |
| CSRF | before | Cross-Site Request Forgery protection (CI4 built-in, session-based) |
| InvalidChars | before | Block malicious characters in input (CI4 built-in) |
| SecureHeaders | after | Add security HTTP headers (CI4 built-in) |

### 2. Controllers (app/Controllers/)

| Controller | Responsibility |
|------------|---------------|
| `BaseController` | Abstract base — session init, viewData helper, jsonResponse helper, permission check |
| `AuthController` | Login, logout, password change |
| `DashboardController` | Dashboard with statistics |
| `UserController` | CRUD users, toggle active status, reset passwords |
| `RoleController` | CRUD roles, sync permissions |
| `DocumentController` | Upload, index, view, edit, delete documents |
| `SearchController` | Full-text search with filters and synonym expansion |
| `AuditController` | View audit logs with filtering |
| `LanguageController` | Switch between Arabic/English |

### 3. Models (app/Models/)

| Model | Table | Key Methods |
|-------|-------|-------------|
| `UserModel` | users | findByLogin, isLocked, recordFailedLogin, getPermissions, hasPermission |
| `RoleModel` | roles | getWithPermissions, syncPermissions, countUsers |
| `PermissionModel` | permissions | getGrouped |
| `LegalDocumentModel` | legal_documents | existsByHash, fullTextSearch, getStats |
| `LegalPrincipleModel` | legal_principles | getByDocument, search |
| `DefenseModel` | defenses | getByPrinciple |
| `AuditLogModel` | audit_logs | log, getFiltered, getDistinctActions |

### 4. Libraries (app/Libraries/)

Domain-specific logic, stateless, all static methods.

| Library | Purpose |
|---------|---------|
| `ArabicTextNormalizer` | 5-step Arabic text normalization pipeline |
| `ArabicSynonymDictionary` | 59 legal synonym groups with lazy-built lookup |
| `DocumentParser` | DOCX/DOC parsing via PhpWord with security limits |

### 5. Views (app/Views/)

Two layout templates with 14 page views:

- `layouts/auth.php` — Minimal layout for login/change-password
- `layouts/main.php` — Full layout with sidebar, navbar, breadcrumbs

All views support RTL (Arabic) and LTR (English) with Bootstrap 5 RTL.

## Database Schema

### Entity Relationship

```
roles (1) ←── (N) users
roles (N) ──── (N) permissions     [via role_permissions]
users (N) ──── (N) permissions     [via user_permissions, overrides]
users (1) ←── (N) audit_logs
users (1) ←── (N) legal_documents  [indexed_by]
legal_documents (1) ←── (N) legal_principles
legal_principles (1) ←── (N) defenses
```

### Tables

| Table | Description | Indexes |
|-------|-------------|---------|
| `roles` | System and custom roles | PK, unique(name) |
| `permissions` | Permission definitions | PK, unique(name), idx(group_name) |
| `role_permissions` | Role-permission mapping | Composite PK |
| `users` | User accounts | PK, unique(username), unique(email), FK(role_id) |
| `user_permissions` | Per-user permission overrides | Composite PK, FK(user_id), FK(permission_id) |
| `legal_documents` | Legal document metadata + text | PK, FULLTEXT(title, full_text, normalized_text, keywords), unique(content_hash) |
| `legal_principles` | Extracted legal principles | PK, FULLTEXT(title, description, legal_basis, source_quote), FK(document_id) |
| `defenses` | Defense strategies | PK, FK(principle_id) |
| `audit_logs` | Action audit trail | PK, idx(user_id, action, entity_type, created_at) |
| `ci_sessions` | PHP session storage | PK, idx(timestamp) |

## i18n

- Default locale: Arabic (`ar`)
- Supported locales: `ar`, `en`
- Language files: `app/Language/{ar,en}/App.php` (150+ translation keys)
- Locale switching: `GET /lang/{locale}` stores preference in session
- Direction: `rtl` for Arabic, `ltr` for English — set automatically in BaseController

## Normalization Pipeline

When a document is uploaded, text goes through:

1. **Extraction** — PhpWord parses DOCX/DOC into plain text
2. **Normalization** — ArabicTextNormalizer processes the text:
   - Alef variants (أ إ آ ٱ) → ا
   - Taa Marbuta (ة) → ه
   - Alef Maqsura (ى) → ي
   - Strip tashkeel/diacritics
   - Collapse whitespace
3. **Storage** — Original in `full_text`, normalized in `normalized_text`
4. **Indexing** — MySQL FULLTEXT index covers both columns for search

## Search Flow

1. User enters query
2. Query is normalized via `ArabicTextNormalizer`
3. Synonyms expanded via `ArabicSynonymDictionary`
4. MySQL `MATCH...AGAINST` in BOOLEAN MODE on 4 columns
5. Results ranked by relevance score
6. Filters applied (type, court, date range)
7. Results paginated
