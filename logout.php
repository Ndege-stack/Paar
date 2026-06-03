<?php
/**
 * =====================================================================
 * PAAR — logout.php
 * ---------------------------------------------------------------------
 * Destroys the session completely and redirects to the login page.
 * =====================================================================
 */

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/security.php';

// 0. Audit before wiping the session so we still know who is logging out.
if (is_logged_in()) {
    audit_log('logout', 'user', current_user_id());
}

// 1. Wipe all session data.
$_SESSION = [];

// 2. Expire the session cookie on the browser.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path']     ?? '/',
        $params['domain']   ?? '',
        $params['secure']   ?? false,
        $params['httponly'] ?? true
    );
}

// 3. Destroy the server-side session record.
session_destroy();

redirect('index.php');
