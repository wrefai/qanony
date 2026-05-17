# Qanony — Live Server Deployment Guide

## Requirements

| Item | Minimum |
|---|---|
| PHP | 8.0 – 8.3 |
| MySQL / MariaDB | 5.7+ / 10.3+ |
| PHP extensions | pdo, pdo_mysql, mbstring, json, xml, fileinfo, intl |
| Web server | Apache 2.4+ with mod_rewrite enabled |
| Disk space | ~100 MB for app + uploads |

---

## Step 1 — Export the database from local

On your local machine run this in XAMPP Shell (or phpMyAdmin Export):

```
C:\xampp\mysql\bin\mysqldump.exe -u root qanony > qanony_export.sql
```

---

## Step 2 — Upload files to cPanel

### What to upload

Upload the **entire project folder** to your cPanel hosting. Two common layouts:

### Layout A — Domain root (`yourdomain.com`)
Upload everything **inside** `C:\xampp\htdocs\qanony\` to `public_html/`.
Then move everything inside `public/` up into `public_html/` and delete the now-empty `public/` folder.
The `index.php` at root stays where it is.

```
public_html/
├── index.php          ← CI4 front controller (already at project root)
├── .htaccess          ← already at project root
├── .user.ini
├── assets/            ← move from public/assets/ to here
│   ├── css/
│   ├── js/
│   ├── fonts/
│   └── icons/
├── manifest.json
├── sw.js
├── favicon.ico
├── app/
├── vendor/
├── writable/
└── ...
```

After moving, update the two layout files:
- `app/Views/layouts/main.php` lines 31 and 247: change `public/assets/` back to `assets/`
- `app/Views/layouts/auth.php` line 22: change `public/assets/` back to `assets/`

### Layout B — Subdirectory (`yourdomain.com/qanony`)
Upload the full folder to `public_html/qanony/` as-is. No file moving needed.
The `public/` folder stays, `.htaccess` at root handles routing, `public/.htaccess` blocks PHP execution.
Asset paths remain `public/assets/...` as currently set.

---

## Step 3 — Create the database in cPanel

1. In cPanel → **MySQL Databases** → create a new database, e.g. `cpuser_qanony`
2. Create a DB user with a strong password, e.g. `cpuser_quser`
3. Add the user to the database with **All Privileges**
4. In cPanel → **phpMyAdmin** → select the new database → **Import** → upload `qanony_export.sql`

---

## Step 4 — Create `.env` on the server

Create/upload `.env` in the project root with the following content.
**Edit every value marked `← CHANGE THIS`.**

```ini
CI_ENVIRONMENT = production

# ── App ──────────────────────────────────────────────────────────
app.baseURL            = 'https://yourdomain.com/'          # ← CHANGE THIS
app.allowedHostnames   = 'yourdomain.com'                   # ← CHANGE THIS
app.forceGlobalSecureRequests = true
app.CSPEnabled         = false
app.defaultLocale      = 'ar'
app.supportedLocales   = 'ar,en'

# ── Database ─────────────────────────────────────────────────────
database.default.hostname = 'localhost'
database.default.database = 'cpuser_qanony'                 # ← CHANGE THIS
database.default.username = 'cpuser_quser'                  # ← CHANGE THIS
database.default.password = 'YOUR_DB_PASSWORD'              # ← CHANGE THIS
database.default.DBDriver = MySQLi
database.default.DBPrefix =
database.default.port     = 3306
database.default.charset  = utf8mb4
database.default.DBCollat = utf8mb4_unicode_ci

# ── Encryption key (copy exact value from your local .env) ───────
encryption.key = hex2bin:3b542ea0b56f0421ba20eac76ce3e056a9ff4d6b5eed1f1598713b487e517d58

# ── Session ──────────────────────────────────────────────────────
session.driver             = 'CodeIgniter\Session\Handlers\FileHandler'
session.savePath           = '/home/CPUSER/public_html/qanony/writable/session'  # ← CHANGE CPUSER
session.expiration         = 7200
session.regenerateDestroy  = true

# ── Logging ──────────────────────────────────────────────────────
logger.threshold = 4

# ── Auth ─────────────────────────────────────────────────────────
auth.minPasswordLength = 8
auth.maxLoginAttempts  = 5
auth.lockoutDuration   = 900

# ── Upload limits ────────────────────────────────────────────────
upload.maxFileSize  = 52428800
upload.allowedTypes = 'docx,doc'

# ── Cloud integrations (optional) ────────────────────────────────
cloud.googlePickerApiKey  =
cloud.googlePickerClientId =
cloud.googlePickerAppId   =
cloud.dropboxAppKey       =
cloud.onedriveClientId    =
```

---

## Step 5 — Set writable directory permissions

SSH into your server (or use cPanel File Manager) and run:

```bash
chmod -R 755 writable/
chmod -R 755 writable/cache/
chmod -R 755 writable/logs/
chmod -R 755 writable/session/
chmod -R 755 writable/uploads/
```

If the directories don't exist, create them first:

```bash
mkdir -p writable/cache writable/logs writable/session writable/uploads/documents
chmod -R 755 writable/
```

---

## Step 6 — Verify `.htaccess` and mod_rewrite

The root `.htaccess` already handles CI4 routing. Confirm Apache has `AllowOverride All` for your directory. On most cPanel hosts this is already enabled.

If you get 404s on all pages except the homepage, add this to the top of `.htaccess`:

```apache
Options +FollowSymLinks
```

---

## Step 7 — Point the install wizard to create the lock file

The app uses `writable/install.lock` to know it's installed. Since you imported the database (not ran the wizard), create the lock file manually:

```bash
echo '{"version":"1.0.0","installed_at":"2026-05-17 00:00:00","last_updated_at":"2026-05-17 00:00:00"}' > writable/install.lock
```

Or via cPanel File Manager: create `writable/install.lock` with that JSON content.

---

## Step 8 — Test

1. Visit `https://yourdomain.com/` (or `/qanony/` for subdirectory installs)
2. You should be redirected to `/auth/login`
3. Log in with your admin credentials
4. Verify all nav links work: Documents, Search, Users, Roles, Audit, Queue, Settings

---

## Checklist

- [ ] PHP 8.0+ confirmed on host
- [ ] Required extensions enabled (pdo_mysql, mbstring, intl, fileinfo, xml)
- [ ] Database created and SQL imported
- [ ] `.env` updated with production values
- [ ] `writable/` directories exist and are writable
- [ ] `writable/install.lock` created
- [ ] `app.baseURL` matches your actual domain
- [ ] `session.savePath` is an absolute path on the server
- [ ] `app.forceGlobalSecureRequests = true` if using HTTPS

---

## Troubleshooting

### Blank page / 500 error
- Check `writable/logs/log-YYYY-MM-DD.log`
- Temporarily set `CI_ENVIRONMENT = development` in `.env` to see errors in browser
- Ensure `vendor/` was uploaded (it's large — ~20 MB)

### "The page isn't working" on all routes
- mod_rewrite is not enabled or `.htaccess` is being ignored
- Check that `AllowOverride All` is set in Apache config

### Session errors
- `session.savePath` must be an **absolute** path the PHP process can write to
- On cPanel it is typically `/home/yourusername/public_html/qanony/writable/session`

### Assets (CSS/JS) not loading
- Verify `app.baseURL` ends with a trailing slash: `https://yourdomain.com/`
- If Layout A: ensure `assets/` folder is in `public_html/` and paths in views say `assets/` not `public/assets/`
- If Layout B (subdirectory): paths should say `public/assets/` as currently set

### "Not installed" redirect loop
- `writable/install.lock` is missing — create it as described in Step 7

### Database connection failed
- Double-check credentials in `.env`
- cPanel DB hostnames are always `localhost`
- Ensure the DB user is added to the database with All Privileges
