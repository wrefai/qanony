<?php

use CodeIgniter\Router\RouteCollection;

/*
 * --------------------------------------------------------------------
 * Router Setup
 * --------------------------------------------------------------------
 * NOTE: $routes is injected by RouteCollection::loadRoutes() — do NOT
 * call Services::routes() here.
 * --------------------------------------------------------------------
 */

/** @var RouteCollection $routes */
$routes->setDefaultNamespace('App\Controllers');
$routes->setDefaultController('DashboardController');
$routes->setDefaultMethod('index');
$routes->setTranslateURIDashes(false);
$routes->set404Override();
$routes->setAutoRoute(false);

// ── Public routes ──────────────────────────────────────────────
$routes->get('/', 'DashboardController::index', ['filter' => 'auth']);

// Setup / upgrade wizard (public — no auth required)
$routes->get('install',                        'InstallController::index');
$routes->post('install/step/(:segment)',       'InstallController::step/$1');

// Update wizard (public — no auth required)
$routes->get('update',      'UpdateController::index');
$routes->post('update/run', 'UpdateController::run');

// Language switcher
$routes->get('lang/(:segment)', 'LanguageController::switch/$1');

// Theme switcher (AJAX fire-and-forget from app.js)
$routes->get('theme/set', 'LanguageController::setTheme');

// ── Auth routes ────────────────────────────────────────────────
$routes->group('auth', static function ($routes) {
    $routes->get('login', 'AuthController::login');
    $routes->post('login', 'AuthController::attemptLogin');
    $routes->get('logout', 'AuthController::logout');
    $routes->get('change-password', 'AuthController::changePassword', ['filter' => 'auth']);
    $routes->post('change-password', 'AuthController::attemptChangePassword', ['filter' => 'auth']);
});

// ── Protected routes ───────────────────────────────────────────
$routes->group('', ['filter' => 'auth'], static function ($routes) {

    // Dashboard
    $routes->get('dashboard', 'DashboardController::index');

    // Users
    $routes->group('users', ['filter' => 'permission:users.read'], static function ($routes) {
        $routes->get('/', 'UserController::index');
        $routes->get('data', 'UserController::data');
        $routes->get('create', 'UserController::create', ['filter' => 'permission:users.create']);
        $routes->post('create', 'UserController::store', ['filter' => 'permission:users.create']);
        $routes->get('(:num)/edit', 'UserController::edit/$1', ['filter' => 'permission:users.update']);
        $routes->post('(:num)/update', 'UserController::update/$1', ['filter' => 'permission:users.update']);
        $routes->post('(:num)/delete', 'UserController::delete/$1', ['filter' => 'permission:users.delete']);
        $routes->post('(:num)/toggle-status', 'UserController::toggleStatus/$1', ['filter' => 'permission:users.update']);
        $routes->post('(:num)/reset-password', 'UserController::resetPassword/$1', ['filter' => 'permission:users.update']);
    });

    // Roles
    $routes->group('roles', ['filter' => 'permission:roles.read'], static function ($routes) {
        $routes->get('/', 'RoleController::index');
        $routes->get('create', 'RoleController::create', ['filter' => 'permission:roles.create']);
        $routes->post('create', 'RoleController::store', ['filter' => 'permission:roles.create']);
        $routes->get('(:num)/edit', 'RoleController::edit/$1', ['filter' => 'permission:roles.update']);
        $routes->post('(:num)/update', 'RoleController::update/$1', ['filter' => 'permission:roles.update']);
        $routes->post('(:num)/delete', 'RoleController::delete/$1', ['filter' => 'permission:roles.delete']);
    });

    // Documents
    $routes->group('documents', ['filter' => 'permission:documents.read'], static function ($routes) {
        $routes->get('/', 'DocumentController::index');
        $routes->get('data', 'DocumentController::data');
        $routes->get('upload', 'DocumentController::upload', ['filter' => 'permission:documents.create']);
        $routes->post('upload', 'DocumentController::doUpload', ['filter' => 'permission:documents.create']);
        $routes->post('upload-single', 'DocumentController::doUploadSingle', ['filter' => 'permission:documents.create']);
        $routes->get('(:num)', 'DocumentController::show/$1');
        $routes->get('(:num)/preview', 'DocumentController::preview/$1');
        $routes->get('(:num)/render', 'DocumentController::render/$1');
        $routes->get('(:num)/pdf', 'DocumentController::servePdf/$1');
        $routes->get('(:num)/download', 'DocumentController::download/$1');
        $routes->post('(:num)/delete', 'DocumentController::delete/$1', ['filter' => 'permission:documents.delete']);
        $routes->post('bulk-delete', 'DocumentController::bulkDelete', ['filter' => 'permission:documents.delete']);
        $routes->post('bulk-move-scope', 'DocumentController::bulkMoveScope', ['filter' => 'permission:documents.update']);
        $routes->post('delete-by-scope', 'DocumentController::deleteByScope', ['filter' => 'permission:documents.delete']);
        $routes->get('(:num)/edit', 'DocumentController::edit/$1', ['filter' => 'permission:documents.update']);
        $routes->post('(:num)/update', 'DocumentController::update/$1', ['filter' => 'permission:documents.update']);
        $routes->post('(:num)/move-scope', 'DocumentController::moveScope/$1', ['filter' => 'permission:documents.update']);
    });

    // Search Scopes (virtual folder groups)
    $routes->group('scopes', ['filter' => 'permission:search.use'], static function ($routes) {
        $routes->get('tree', 'SearchScopeController::tree');
        $routes->get('dropdown', 'SearchScopeController::dropdown');
        $routes->post('create', 'SearchScopeController::create', ['filter' => 'permission:documents.create']);
        $routes->post('(:num)/update', 'SearchScopeController::update/$1', ['filter' => 'permission:documents.update']);
        $routes->post('(:num)/delete', 'SearchScopeController::delete/$1', ['filter' => 'permission:documents.delete']);
        $routes->post('(:num)/delete-with-docs', 'SearchScopeController::deleteWithDocuments/$1', ['filter' => 'permission:documents.delete']);
        $routes->post('(:num)/move', 'SearchScopeController::move/$1', ['filter' => 'permission:documents.update']);
        // Access control (requires scopes.manage)
        $routes->get('(:num)/access',        'SearchScopeController::accessList/$1',    ['filter' => 'permission:scopes.manage']);
        $routes->post('(:num)/set-restricted','SearchScopeController::setRestricted/$1', ['filter' => 'permission:scopes.manage']);
        $routes->post('(:num)/grant-access',  'SearchScopeController::grantAccess/$1',   ['filter' => 'permission:scopes.manage']);
        $routes->post('(:num)/revoke-access/(:num)', 'SearchScopeController::revokeAccess/$1/$2', ['filter' => 'permission:scopes.manage']);
    });

    // Search
    $routes->group('search', ['filter' => 'permission:search.use'], static function ($routes) {
        $routes->get('/', 'SearchController::index');
        $routes->get('results', 'SearchController::results');
        $routes->get('suggest', 'SearchController::suggest');
        $routes->get('drive-results', 'SearchController::driveResults');
    });

    // Audit Logs
    $routes->group('audit', ['filter' => 'permission:audit.read'], static function ($routes) {
        $routes->get('/', 'AuditController::index');
        $routes->get('data', 'AuditController::data');
    });

    // Settings
    $routes->group('settings', ['filter' => 'permission:settings.read'], static function ($routes) {
        $routes->get('/', 'SettingsController::index');
        $routes->post('update', 'SettingsController::update', ['filter' => 'permission:settings.update']);
    });

    // Upload Queue Monitor
    $routes->group('queue', ['filter' => 'permission:documents.create'], static function ($routes) {
        $routes->get('/', 'QueueController::index');
        $routes->get('stats', 'QueueController::stats');
        $routes->post('clear', 'QueueController::clear', ['filter' => 'permission:documents.delete']);
    });
});
