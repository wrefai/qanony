# Technical Decisions

A log of key technical decisions made during the migration from C# WPF to PHP CodeIgniter 4.

## 1. Framework Choice: CodeIgniter 4.7.0

**Decision**: Use CodeIgniter 4 instead of Laravel, Symfony, or other PHP frameworks.

**Rationale**:
- Lightweight footprint suitable for the project scope
- Simple deployment on shared hosting (XAMPP)
- Built-in support for sessions, validation, database migrations
- Mature testing infrastructure (CIUnitTestCase, FeatureTestCase, DatabaseTestTrait)
- No overkill — the project doesn't need Laravel's queue system, event broadcasting, etc.

## 2. Database: MariaDB with utf8mb4

**Decision**: Use MariaDB 10.4 (XAMPP default) with `utf8mb4_unicode_ci` collation.

**Rationale**:
- Full Unicode support required for Arabic text
- FULLTEXT indexes needed for search (SQLite FTS5 was used in original, but MySQL FULLTEXT is the standard for web apps)
- MariaDB ships with XAMPP (zero additional setup)
- `utf8mb4` handles all Arabic characters including supplementary Unicode

## 3. Session-Based CSRF (not Cookie-Based)

**Decision**: Use `csrfProtection = 'session'` instead of the default `'cookie'`.

**Rationale**:
- Session-based tokens are more secure — they can't be read by JavaScript (unlike cookies with `httpOnly=false`)
- Token randomization (`tokenRandomize = true`) adds an additional layer
- The application already uses database sessions, so storing CSRF state in session adds no overhead
- Cookie-based CSRF is vulnerable to subdomain attacks

## 4. bcrypt with Cost 12

**Decision**: Use `PASSWORD_BCRYPT` with cost factor 12.

**Rationale**:
- bcrypt is the industry standard for password hashing in PHP
- Cost 12 provides good security/performance balance (~250ms per hash on modern hardware)
- PHP's `password_hash()` / `password_verify()` handle salt generation automatically
- Argon2id was considered but bcrypt has wider compatibility and is sufficient for this use case

## 5. Arabic-First RTL Design

**Decision**: Default locale is Arabic (RTL), with English as secondary.

**Rationale**:
- Target users are Kuwaiti legal professionals
- All legal documents are in Arabic
- Bootstrap 5 has built-in RTL support via `dir="rtl"` attribute
- Language toggle stores preference in session (not URL-based)

## 6. Document Parsing: PhpWord

**Decision**: Use PhpOffice/PhpWord for DOCX and DOC parsing.

**Rationale**:
- Supports both `.docx` (Word2007/OOXML) and `.doc` (MsDoc/OLE) formats
- The original C# app used Microsoft.Office.Interop.Word — not available on Linux/PHP
- PhpWord is the de facto standard PHP library for Word document processing
- Recursive text extraction handles nested elements (TextRun, Table cells, etc.)

## 7. SQLite In-Memory for Tests

**Decision**: Use SQLite3 in-memory database for PHPUnit tests, with manually created SQLite-compatible tables.

**Rationale**:
- Zero setup — no test database needed
- Fast — in-memory operations
- Isolated — each test run starts fresh
- MySQL-specific features (ENUM, FULLTEXT, JSON) handled by:
  - ENUM → VARCHAR in test tables
  - FULLTEXT → skipped in tests (tested via integration tests on real MySQL)
  - JSON → TEXT in test tables

**Trade-off**: `fullTextSearch()` cannot be unit-tested with SQLite. This is acceptable because:
- The search logic is covered by integration tests (15/15 passed on real MySQL)
- The SQL generation can be visually inspected
- All other model methods are fully tested

## 8. Stateless Libraries (All Static Methods)

**Decision**: `ArabicTextNormalizer`, `ArabicSynonymDictionary`, and `DocumentParser` use only static methods.

**Rationale**:
- These classes have no mutable state
- Static methods are simpler to call and test
- No dependency injection needed
- The original C# implementations were also stateless
- `ArabicSynonymDictionary` uses a lazy-built static lookup table (built once, reused across requests)

## 9. Permission Resolution: Role + User Overrides

**Decision**: Permissions are resolved from role, then user-level overrides applied (grant/revoke).

**Rationale**:
- Role-based gives a good baseline for most users
- Per-user overrides allow fine-grained exceptions without creating new roles
- The `granted` column (0/1) in `user_permissions` allows both adding and removing permissions
- Permissions loaded into session on login to avoid repeated DB queries

## 10. Dual Rate Limiting (IP + User)

**Decision**: Implement both IP-based rate limiting (RateLimitFilter) and per-user account lockout (UserModel).

**Rationale**:
- IP-based: catches distributed attacks and protects against credential stuffing
- Per-user: protects individual accounts even if attacker rotates IPs
- Belt-and-suspenders approach — if one fails, the other still protects
- IP rate limit: 10 POST/5min (generous for legitimate users)
- Account lockout: 5 attempts / 15-minute lock (per env config)

## 11. Language Files vs. Database i18n

**Decision**: Use PHP language files (`app/Language/{ar,en}/App.php`) instead of database-stored translations.

**Rationale**:
- Only 2 languages needed (Arabic and English)
- ~150 translation keys — manageable in files
- No admin UI needed for translation management
- CI4 has native support for language files
- Faster than database lookups
- Easy to version control

## 12. Single CSS/JS Files (No Build Step)

**Decision**: Use single `app.css` and `app.js` files with vanilla JavaScript instead of Webpack/Vite/npm.

**Rationale**:
- Simpler deployment (no Node.js required)
- Project scope doesn't warrant a build pipeline
- Bootstrap 5 loaded via CDN
- Vanilla JS sufficient for AJAX, toasts, theme toggle, and pagination
- RTL/LTR styles handled via CSS `[dir="rtl"]` selectors

## 13. Idempotent Seeder

**Decision**: `InitialSeeder` checks for existing data before inserting.

**Rationale**:
- Safe to run multiple times (e.g., during development, after partial failures)
- Uses `WHERE NOT EXISTS` pattern for each insert
- Prevents duplicate key errors on re-run
- Production databases may already have data from a previous seed
