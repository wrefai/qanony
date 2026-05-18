<?php
/**
 * Qanony — Bootstrap index.php (pre-install only)
 * ─────────────────────────────────────────────────────────────────────
 * This file ships INSIDE the qanony_install.zip alongside installer.php
 * and qanony.zip. Visiting the install directory before the application
 * has been extracted lands on this stub, which forwards the browser to
 * installer.php.
 *
 * Once installer.php extracts qanony.zip, the REAL CodeIgniter4 entry
 * point (also named index.php) overwrites this file, so this script
 * is only ever served for the brief window between extraction of the
 * outer archive and the user clicking "Install Qanony".
 * ─────────────────────────────────────────────────────────────────────
 */

declare(strict_types=1);

// If the application is already installed, the real index.php should
// have replaced this one. Reaching this code with install.lock present
// means an inconsistent state — push the user to installer.php which
// will show an "already installed" guard.
if (is_file(__DIR__ . '/installer.php')) {
    header('Location: installer.php');
    exit;
}

// Edge case: installer.php was deleted but the real app never extracted.
http_response_code(500);
header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
<meta charset="utf-8">
<title>Qanony — Setup Required</title>
<style>
body{font-family:-apple-system,Segoe UI,Roboto,sans-serif;max-width:640px;margin:4rem auto;padding:0 1.5rem;color:#222;line-height:1.55}
h1{font-weight:600}code{background:#f4f4f4;padding:.15rem .4rem;border-radius:3px}
</style>
</head>
<body>
<h1>Qanony is not installed</h1>
<p>The installer (<code>installer.php</code>) is missing from this directory and the application has not been extracted.</p>
<p>Re-upload <code>qanony_install.zip</code> and extract it here, then refresh this page.</p>
</body>
</html>
