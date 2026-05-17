# Security

## Overview

Security measures implemented across authentication, session management, input validation, and HTTP response hardening.

## Authentication

### Password Hashing
- Algorithm: bcrypt (`PASSWORD_BCRYPT`)
- Cost factor: 12
- Hashes stored in `users.password_hash` (VARCHAR 255)

### Account Lockout
- After 5 failed login attempts (configurable via `auth.maxLoginAttempts` in `.env`), the account is locked
- Lockout duration: 15 minutes (configurable via `auth.lockoutDuration` in `.env`, value in seconds)
- Failed attempts are tracked per-user in the database
- Successful login resets the counter

### IP Rate Limiting
- `RateLimitFilter` limits POST requests to login endpoint
- 10 attempts per 5-minute window per IP address
- Uses CI4 cache (file-based by default)
- Belt-and-suspenders complement to per-user lockout

### Force Password Change
- New users can be flagged with `force_password_change=1`
- `AuthFilter` intercepts all requests and redirects to change-password page
- Only allowed paths during force change: `auth/change-password`, `auth/logout`, `lang/ar`, `lang/en`

### Password Complexity
Passwords must contain:
- At least one uppercase letter (A-Z)
- At least one lowercase letter (a-z)
- At least one digit (0-9)
- At least one special character
- Minimum length: 8 characters (configurable via `auth.minPasswordLength` in `.env`)

## Session Management

- Storage: Database sessions (`ci_sessions` table)
- Protection: Session-based CSRF (not cookie-based)
- Session regeneration on login
- Session destruction on logout

## CSRF Protection

- Method: Session-based (`csrfProtection = 'session'`)
- Token randomization: Enabled (`tokenRandomize = true`)
- Token name: `csrf_token`
- Token regeneration: Per-request
- Failed CSRF: Redirect (not exception)
- Applied globally to all POST/PUT/DELETE requests

## Input Validation

### InvalidChars Filter
- Enabled globally as a `before` filter
- Blocks requests containing invalid/malicious character sequences

### SQL Injection Prevention
- All database queries use CI4's Query Builder with parameter binding
- The `fullTextSearch()` method uses `$this->db->escape()` for MATCH...AGAINST clauses (returns quoted+escaped values)
- Original implementation had a vulnerability using `$this->db->escapeString()` inside single quotes — this was fixed

### Validation Rules
- Models define validation rules for all user-input fields
- Controller methods validate input before processing
- Username: alphanumeric + punctuation, 3-50 chars, unique
- Email: valid format, unique
- Passwords: minimum length + complexity requirements

## HTTP Security Headers

`SecureHeaders` filter (CI4 built-in, enabled globally as `after` filter) adds:
- `X-Frame-Options`
- `X-Content-Type-Options`
- `X-XSS-Protection`
- `Referrer-Policy`
- `Content-Security-Policy` (if configured)

## File Upload Security

### Document Uploads
- Maximum file size: 200 MB (enforced in DocumentParser)
- Maximum text length: 50 million characters (after extraction)
- Allowed extensions: `.docx`, `.doc` only
- Files stored in `writable/uploads/documents/`
- Directory listing protected by `index.html` (returns 403)
- SHA-256 hash computed for deduplication

### Upload Directory
- Located under `writable/` (outside web root)
- Protected from direct web access
- `index.html` placed in upload directory as additional protection

## Configuration Security

- All sensitive values in `.env` file (database credentials, encryption key, auth settings)
- `.env` excluded from version control via `.gitignore`
- `.env` not accessible from web (outside `public/` directory)

## Audit Trail

All security-relevant actions are logged to `audit_logs`:

| Action | Trigger |
|--------|---------|
| `login_success` | Successful authentication |
| `login_failed` | Failed authentication attempt |
| `logout` | User logout |
| `password_changed` | Password change |
| `user_created` | New user created |
| `user_updated` | User profile modified |
| `user_deleted` | User deleted |
| `role_created` | New role created |
| `role_updated` | Role permissions changed |
| `document_uploaded` | Document uploaded |
| `document_deleted` | Document deleted |

Each log entry includes: user ID, IP address, user agent, timestamp, entity type/ID, old/new values (for changes).

## Known Limitations

1. **No HTTPS enforcement in code** — HTTPS should be configured at the web server level (Apache/Nginx)
2. **File cache for rate limiting** — In high-traffic scenarios, consider switching to Redis/Memcached
3. **Session storage** — Database sessions work for single-server deployments; for multi-server, use Redis
4. **No Content-Security-Policy nonce** — Inline scripts use event handlers; CSP should be configured per deployment
