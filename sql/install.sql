-- ============================================================
--  Qanony — Standalone Installation SQL
--  Version : 1.0.0
--  Generated: 2026-05-17
--
--  Usage:
--    mysql -u <user> -p <database> < install.sql
--
--  Requirements:
--    MySQL 8.0+ or MariaDB 10.4+
--    Character set: utf8mb4 / utf8mb4_unicode_ci
--
--  This script is equivalent to running all 16 migrations
--  plus InitialSeeder via the web wizard.  Tables are created
--  in the correct dependency order.  The script is idempotent
--  (uses IF NOT EXISTS / INSERT IGNORE).
--
--  Default admin credentials installed by this script:
--    Username : admin
--    Password : Admin@123
--  *** Change these immediately after first login. ***
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET sql_mode = 'NO_ENGINE_SUBSTITUTION';

START TRANSACTION;

-- ============================================================
-- 1. roles
-- ============================================================
CREATE TABLE IF NOT EXISTS `roles` (
    `id`          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(50)      NOT NULL,
    `description` VARCHAR(255)     DEFAULT NULL,
    `is_system`   TINYINT          NOT NULL DEFAULT 0 COMMENT 'System roles cannot be deleted',
    `created_at`  DATETIME         DEFAULT NULL,
    `updated_at`  DATETIME         DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_roles_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. permissions
-- ============================================================
CREATE TABLE IF NOT EXISTS `permissions` (
    `id`          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(100)     NOT NULL COMMENT 'e.g. users.create, documents.read',
    `group_name`  VARCHAR(50)      NOT NULL COMMENT 'Logical grouping: users, roles, documents, etc.',
    `description` VARCHAR(255)     DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_permissions_name` (`name`),
    KEY `idx_permissions_group` (`group_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. role_permissions
-- ============================================================
CREATE TABLE IF NOT EXISTS `role_permissions` (
    `role_id`       INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`role_id`, `permission_id`),
    CONSTRAINT `fk_rp_role`       FOREIGN KEY (`role_id`)       REFERENCES `roles`       (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. users
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
    `id`                     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `username`               VARCHAR(50)   NOT NULL,
    `email`                  VARCHAR(255)  NOT NULL,
    `password_hash`          VARCHAR(255)  NOT NULL,
    `full_name`              VARCHAR(150)  NOT NULL,
    `role_id`                INT UNSIGNED  NOT NULL,
    `is_active`              TINYINT       NOT NULL DEFAULT 1,
    `force_password_change`  TINYINT       NOT NULL DEFAULT 0 COMMENT 'Require password change on next login',
    `failed_login_attempts`  INT           NOT NULL DEFAULT 0,
    `locked_until`           DATETIME      DEFAULT NULL,
    `last_login_at`          DATETIME      DEFAULT NULL,
    `last_login_ip`          VARCHAR(45)   DEFAULT NULL,
    `created_at`             DATETIME      DEFAULT NULL,
    `updated_at`             DATETIME      DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_username` (`username`),
    UNIQUE KEY `uq_users_email`    (`email`),
    CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. user_permissions
-- ============================================================
CREATE TABLE IF NOT EXISTS `user_permissions` (
    `user_id`       INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    `granted`       TINYINT      NOT NULL DEFAULT 1 COMMENT '1 = grant, 0 = deny (overrides role)',
    PRIMARY KEY (`user_id`, `permission_id`),
    CONSTRAINT `fk_up_user`       FOREIGN KEY (`user_id`)       REFERENCES `users`       (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_up_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. legal_documents  (includes scope_id, word_count, char_count
--    from migration 2026-03-04-180002 and optimized FULLTEXT index
--    from migration 2026-03-04-180003 — all merged here)
-- ============================================================
CREATE TABLE IF NOT EXISTS `legal_documents` (
    `id`              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `scope_id`        INT UNSIGNED      DEFAULT NULL,
    `title`           VARCHAR(500)      NOT NULL,
    `document_type`   ENUM('ruling','memorandum','law','regulation','legal_opinion','contract') DEFAULT NULL,
    `court_level`     ENUM('first_instance','appeal','tamyeez','administrative','constitutional','commercial','criminal','personal_status','labor') DEFAULT NULL,
    `case_number`     VARCHAR(100)      DEFAULT NULL,
    `document_date`   DATE              DEFAULT NULL,
    `hijri_year`      VARCHAR(10)       DEFAULT NULL,
    `file_path`       VARCHAR(1000)     NOT NULL,
    `file_name`       VARCHAR(255)      NOT NULL,
    `file_size`       BIGINT UNSIGNED   NOT NULL DEFAULT 0,
    `file_extension`  VARCHAR(10)       NOT NULL,
    `page_count`      INT UNSIGNED      NOT NULL DEFAULT 0,
    `word_count`      INT UNSIGNED      NOT NULL DEFAULT 0,
    `char_count`      INT UNSIGNED      NOT NULL DEFAULT 0,
    `full_text`       LONGTEXT          DEFAULT NULL,
    `normalized_text` LONGTEXT          DEFAULT NULL COMMENT 'Arabic-normalized text for search',
    `content_hash`    VARCHAR(64)       NOT NULL COMMENT 'SHA-256 hash for dedup',
    `summary`         TEXT              DEFAULT NULL,
    `keywords`        TEXT              DEFAULT NULL COMMENT 'Comma-separated keywords',
    `is_indexed`      TINYINT           NOT NULL DEFAULT 0,
    `indexed_by`      INT UNSIGNED      DEFAULT NULL,
    `created_at`      DATETIME          DEFAULT NULL,
    `updated_at`      DATETIME          DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_documents_hash`       (`content_hash`),
    KEY `idx_documents_scope`            (`scope_id`),
    KEY `idx_documents_type`             (`document_type`),
    KEY `idx_documents_court`            (`court_level`),
    KEY `idx_documents_date`             (`document_date`),
    KEY `idx_documents_indexed`          (`is_indexed`),
    -- Optimised 3-column FULLTEXT index (migration 2026-03-04-180003)
    FULLTEXT KEY `ft_documents`          (`normalized_text`, `title`, `keywords`),
    CONSTRAINT `fk_documents_indexed_by` FOREIGN KEY (`indexed_by`) REFERENCES `users`         (`id`) ON DELETE SET NULL ON UPDATE SET NULL,
    CONSTRAINT `fk_documents_scope`      FOREIGN KEY (`scope_id`)   REFERENCES `search_scopes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- NOTE: fk_documents_scope references search_scopes which is created later.
-- MySQL defers FK validation until FOREIGN_KEY_CHECKS is re-enabled at the end.

-- ============================================================
-- 7. legal_principles
-- ============================================================
CREATE TABLE IF NOT EXISTS `legal_principles` (
    `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `document_id`      INT UNSIGNED  NOT NULL,
    `title`            VARCHAR(500)  NOT NULL,
    `description`      TEXT          DEFAULT NULL,
    `legal_basis`      VARCHAR(500)  DEFAULT NULL,
    `category`         ENUM('substantive','procedural','constitutional','commercial','criminal','civil','administrative','labor','personal_status') NOT NULL DEFAULT 'substantive',
    `source_quote`     TEXT          DEFAULT NULL,
    `page_reference`   VARCHAR(50)   DEFAULT NULL,
    `confidence`       DECIMAL(5,4)  NOT NULL DEFAULT 1.0000,
    `extraction_method` ENUM('manual','rule_based','llm') NOT NULL DEFAULT 'manual',
    `created_at`       DATETIME      DEFAULT NULL,
    `updated_at`       DATETIME      DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_principles_category` (`category`),
    FULLTEXT KEY `ft_principles` (`title`, `description`, `legal_basis`, `source_quote`),
    CONSTRAINT `fk_principles_document` FOREIGN KEY (`document_id`) REFERENCES `legal_documents` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 8. defenses
-- ============================================================
CREATE TABLE IF NOT EXISTS `defenses` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `principle_id` INT UNSIGNED  NOT NULL,
    `title`        VARCHAR(500)  NOT NULL,
    `description`  TEXT          DEFAULT NULL,
    `legal_basis`  VARCHAR(500)  DEFAULT NULL,
    `created_at`   DATETIME      DEFAULT NULL,
    `updated_at`   DATETIME      DEFAULT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_defenses_principle` FOREIGN KEY (`principle_id`) REFERENCES `legal_principles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 9. audit_logs
-- ============================================================
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED    DEFAULT NULL,
    `action`      VARCHAR(50)     NOT NULL COMMENT 'login_success, login_failed, user_created, etc.',
    `entity_type` VARCHAR(50)     DEFAULT NULL COMMENT 'user, role, document, etc.',
    `entity_id`   INT UNSIGNED    DEFAULT NULL,
    `description` TEXT            DEFAULT NULL,
    `old_values`  JSON            DEFAULT NULL COMMENT 'Previous state for changes',
    `new_values`  JSON            DEFAULT NULL COMMENT 'New state for changes',
    `ip_address`  VARCHAR(45)     DEFAULT NULL,
    `user_agent`  VARCHAR(500)    DEFAULT NULL,
    `created_at`  DATETIME        DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_audit_user`        (`user_id`),
    KEY `idx_audit_action`      (`action`),
    KEY `idx_audit_entity_type` (`entity_type`),
    KEY `idx_audit_created`     (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 10. ci_sessions  (CodeIgniter 4 database session handler)
-- ============================================================
CREATE TABLE IF NOT EXISTS `ci_sessions` (
    `id`         VARCHAR(128) NOT NULL,
    `ip_address` VARCHAR(45)  NOT NULL,
    `timestamp`  INT UNSIGNED NOT NULL DEFAULT 0,
    `data`       BLOB         NOT NULL DEFAULT '',
    PRIMARY KEY (`id`),
    KEY `idx_sessions_timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 11. search_scopes  (self-referential tree, migration 2026-03-04-180001)
--     Includes is_restricted column from migration 2026-04-23-100001
-- ============================================================
CREATE TABLE IF NOT EXISTS `search_scopes` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `parent_id`     INT UNSIGNED  DEFAULT NULL,
    `name`          VARCHAR(255)  NOT NULL,
    `description`   VARCHAR(500)  DEFAULT NULL,
    `sort_order`    INT           NOT NULL DEFAULT 0,
    `is_active`     TINYINT       NOT NULL DEFAULT 1,
    `is_restricted` TINYINT(1)    NOT NULL DEFAULT 0 COMMENT '1 = only users listed in scope_user_access may see this scope',
    `created_by`    INT UNSIGNED  DEFAULT NULL,
    `created_at`    DATETIME      DEFAULT NULL,
    `updated_at`    DATETIME      DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_scopes_parent`     (`parent_id`),
    KEY `idx_scopes_sort_order` (`sort_order`),
    CONSTRAINT `fk_scopes_parent`     FOREIGN KEY (`parent_id`)  REFERENCES `search_scopes` (`id`) ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT `fk_scopes_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`         (`id`) ON DELETE SET NULL ON UPDATE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 12. scope_user_access  (migration 2026-04-23-100001)
-- ============================================================
CREATE TABLE IF NOT EXISTS `scope_user_access` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `scope_id`   INT UNSIGNED NOT NULL,
    `user_id`    INT UNSIGNED DEFAULT NULL,
    `role_id`    INT UNSIGNED DEFAULT NULL,
    `granted_by` INT UNSIGNED DEFAULT NULL COMMENT 'Admin who granted this access',
    `created_at` DATETIME     DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_sua_scope_user` (`scope_id`, `user_id`),
    KEY `idx_sua_scope_role` (`scope_id`, `role_id`),
    CONSTRAINT `fk_sua_scope`      FOREIGN KEY (`scope_id`)   REFERENCES `search_scopes` (`id`) ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT `fk_sua_user`       FOREIGN KEY (`user_id`)    REFERENCES `users`         (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sua_role`       FOREIGN KEY (`role_id`)    REFERENCES `roles`         (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sua_granted_by` FOREIGN KEY (`granted_by`) REFERENCES `users`         (`id`) ON DELETE SET NULL ON UPDATE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 13. upload_queue  (migration 2026-04-23-200001)
-- ============================================================
CREATE TABLE IF NOT EXISTS `upload_queue` (
    `id`             BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `file_path`      VARCHAR(500)     NOT NULL,
    `original_name`  VARCHAR(500)     NOT NULL,
    `file_size`      BIGINT UNSIGNED  NOT NULL DEFAULT 0,
    `file_extension` VARCHAR(10)      NOT NULL,
    `scope_id`       INT UNSIGNED     DEFAULT NULL,
    `document_type`  VARCHAR(50)      DEFAULT NULL,
    `court_level`    VARCHAR(50)      DEFAULT NULL,
    `case_number`    VARCHAR(100)     DEFAULT NULL,
    `document_date`  DATE             DEFAULT NULL,
    `status`         ENUM('pending','processing','processed','failed','duplicate') NOT NULL DEFAULT 'pending',
    `error_message`  TEXT             DEFAULT NULL,
    `document_id`    BIGINT UNSIGNED  DEFAULT NULL,
    `uploaded_by`    INT UNSIGNED     DEFAULT NULL,
    `attempts`       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`     DATETIME         DEFAULT NULL,
    `updated_at`     DATETIME         DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_queue_status`      (`status`),
    KEY `idx_queue_created`     (`created_at`),
    KEY `idx_queue_uploaded_by` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 14. migrations  (CodeIgniter 4 internal migrations tracker)
-- ============================================================
CREATE TABLE IF NOT EXISTS `migrations` (
    `id`        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `version`   VARCHAR(255)    NOT NULL,
    `class`     VARCHAR(255)    NOT NULL,
    `group`     VARCHAR(255)    NOT NULL,
    `namespace` VARCHAR(255)    NOT NULL,
    `time`      INT             NOT NULL,
    `batch`     INT UNSIGNED    NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mark all migrations as applied so CI4 does not re-run them
INSERT IGNORE INTO `migrations` (`version`, `class`, `group`, `namespace`, `time`, `batch`) VALUES
('2026-02-28-180001', 'App\\Database\\Migrations\\CreateRolesTable',            'default', 'App', UNIX_TIMESTAMP(), 1),
('2026-02-28-180002', 'App\\Database\\Migrations\\CreatePermissionsTable',       'default', 'App', UNIX_TIMESTAMP(), 1),
('2026-02-28-180003', 'App\\Database\\Migrations\\CreateRolePermissionsTable',   'default', 'App', UNIX_TIMESTAMP(), 1),
('2026-02-28-180004', 'App\\Database\\Migrations\\CreateUsersTable',             'default', 'App', UNIX_TIMESTAMP(), 1),
('2026-02-28-180005', 'App\\Database\\Migrations\\CreateUserPermissionsTable',   'default', 'App', UNIX_TIMESTAMP(), 1),
('2026-02-28-180006', 'App\\Database\\Migrations\\CreateLegalDocumentsTable',    'default', 'App', UNIX_TIMESTAMP(), 1),
('2026-02-28-180007', 'App\\Database\\Migrations\\CreateLegalPrinciplesTable',   'default', 'App', UNIX_TIMESTAMP(), 1),
('2026-02-28-180008', 'App\\Database\\Migrations\\CreateDefensesTable',          'default', 'App', UNIX_TIMESTAMP(), 1),
('2026-02-28-180009', 'App\\Database\\Migrations\\CreateAuditLogsTable',         'default', 'App', UNIX_TIMESTAMP(), 1),
('2026-02-28-180010', 'App\\Database\\Migrations\\CreateSessionsTable',          'default', 'App', UNIX_TIMESTAMP(), 1),
('2026-03-04-180001', 'App\\Database\\Migrations\\CreateSearchScopesTable',      'default', 'App', UNIX_TIMESTAMP(), 1),
('2026-03-04-180002', 'App\\Database\\Migrations\\AddScopeAndCountsToDocuments', 'default', 'App', UNIX_TIMESTAMP(), 1),
('2026-03-04-180003', 'App\\Database\\Migrations\\OptimizeFulltextIndex',        'default', 'App', UNIX_TIMESTAMP(), 1),
('2026-04-23-100001', 'App\\Database\\Migrations\\AddScopeAccessControl',        'default', 'App', UNIX_TIMESTAMP(), 1),
('2026-04-23-100002', 'App\\Database\\Migrations\\AddScopeManagePermission',     'default', 'App', UNIX_TIMESTAMP(), 1),
('2026-04-23-200001', 'App\\Database\\Migrations\\CreateUploadQueue',            'default', 'App', UNIX_TIMESTAMP(), 1);

-- ============================================================
-- SEED DATA  (equivalent to InitialSeeder)
-- ============================================================

-- ── Roles ────────────────────────────────────────────────────
INSERT IGNORE INTO `roles` (`id`, `name`, `description`, `is_system`, `created_at`, `updated_at`) VALUES
(1, 'admin',   'Full system access',                              1, NOW(), NOW()),
(2, 'manager', 'Management access with limited admin features',   1, NOW(), NOW()),
(3, 'user',    'Standard user access',                            1, NOW(), NOW());

-- ── Permissions ──────────────────────────────────────────────
INSERT IGNORE INTO `permissions` (`name`, `group_name`, `description`) VALUES
-- Users
('users.read',          'users',      'View users list'),
('users.create',        'users',      'Create new users'),
('users.update',        'users',      'Edit users'),
('users.delete',        'users',      'Delete users'),
-- Roles
('roles.read',          'roles',      'View roles'),
('roles.create',        'roles',      'Create roles'),
('roles.update',        'roles',      'Edit roles'),
('roles.delete',        'roles',      'Delete roles'),
-- Documents
('documents.read',      'documents',  'View documents'),
('documents.create',    'documents',  'Index/upload documents'),
('documents.update',    'documents',  'Edit document metadata'),
('documents.delete',    'documents',  'Delete documents'),
('documents.export',    'documents',  'Export documents'),
-- Search
('search.use',          'search',     'Use search functionality'),
('search.advanced',     'search',     'Use advanced search filters'),
-- Principles
('principles.read',     'principles', 'View legal principles'),
('principles.create',   'principles', 'Add legal principles'),
('principles.update',   'principles', 'Edit legal principles'),
('principles.delete',   'principles', 'Delete legal principles'),
-- Audit
('audit.read',          'audit',      'View audit logs'),
-- Settings
('settings.read',       'settings',   'View settings'),
('settings.update',     'settings',   'Modify settings'),
-- Scopes (migration 2026-04-23-100002)
('scopes.manage',       'scopes',     'Manage scope visibility and access control');

-- ── Role → Permission assignments ────────────────────────────
-- Admin: ALL permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, `id` FROM `permissions`;

-- Manager: all except users.delete, roles.create/update/delete, settings.update
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, `id` FROM `permissions`
WHERE `name` NOT IN ('users.delete','roles.create','roles.update','roles.delete','settings.update');

-- User: read-only subset
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, `id` FROM `permissions`
WHERE `name` IN ('documents.read','search.use','search.advanced','principles.read','settings.read');

-- ── Default admin user ────────────────────────────────────────
-- Password: Admin@123  (bcrypt cost=12)
-- The install wizard (step 4) will update username/email/password to what
-- the installer specified.  force_password_change=1 forces a reset on first login.
INSERT IGNORE INTO `users`
    (`username`, `email`, `password_hash`, `full_name`, `role_id`, `is_active`, `force_password_change`, `created_at`, `updated_at`)
VALUES (
    'admin',
    'admin@qanony.local',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'مدير النظام',
    1,
    1,
    1,
    NOW(),
    NOW()
);
-- NOTE: The bcrypt hash above is a placeholder for 'Admin@123'.
-- The web wizard will replace it with the password you enter in step 4.
-- If installing manually (without the wizard), change this password
-- immediately after first login via Profile → Change Password.

COMMIT;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- POST-INSTALL CHECKLIST
-- ============================================================
-- 1. Configure .env  (app.baseURL, database.*, encryption.key)
-- 2. Run web wizard at /install  OR  mark installed manually:
--      INSERT INTO writable/install.lock via wizard finalize step
-- 3. Make writable/ directories readable+writable by web server
-- 4. Set CI_ENVIRONMENT = production in .env
-- 5. Log in at /auth/login with admin / Admin@123
-- 6. Change the admin password on first login
-- ============================================================
