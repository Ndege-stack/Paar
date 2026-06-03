<?php
/**
 * =====================================================================
 * PAAR — auth_check.php
 * ---------------------------------------------------------------------
 * Bootstraps the session, exposes shared security helpers, and enforces
 * role-based access control for any page that includes it.
 *
 * USAGE
 *   require_once __DIR__ . '/../includes/auth_check.php';
 *   require_role('admin');     // admin-only page
 *   require_role('patient');   // patient-only page
 *
 * SECURITY
 *   - Hardened session cookie (HttpOnly, SameSite=Lax, secure when HTTPS)
 *   - Custom session name (PAARSESSID)
 *   - CSRF token generated per session, validated by verify_csrf()
 *   - require_role() redirects unauthorised users to /index.php
 * =====================================================================
 */

require_once __DIR__ . '/../database.php';

/* ------------------------------------------------------------------ */
/* Session bootstrap                                                  */
/* ------------------------------------------------------------------ */
define('SESSION_IDLE_SECONDS', 30 * 60);   // 30-minute idle timeout

if (session_status() === PHP_SESSION_NONE) {
    // Hardened cookie params (must be set BEFORE session_start()).
    $secure = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (($_SERVER['SERVER_PORT'] ?? null) == 443)
    );
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,      // only sent over HTTPS in production
        'httponly' => true,         // not accessible to JavaScript
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Idle timeout: if the user has been inactive for SESSION_IDLE_SECONDS,
// destroy the session and redirect to login.
if (!empty($_SESSION['user_id'])) {
    $lastActive = $_SESSION['last_active'] ?? time();
    if ((time() - $lastActive) > SESSION_IDLE_SECONDS) {
        session_unset();
        session_destroy();
        // Restart a clean session to carry the flash message.
        session_start();
        flash('info', 'Your session expired due to inactivity. Please sign in again.');
        header('Location: ' . (function() {
            $script = $_SERVER['SCRIPT_NAME'] ?? '/';
            $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
            $dir = preg_replace('#/(admin|patient|includes)$#', '', $dir);
            return $dir . '/login.php';
        })());
        exit;
    }
    $_SESSION['last_active'] = time();
}

/* ------------------------------------------------------------------ */
/* CSRF helpers                                                       */
/* ------------------------------------------------------------------ */

/**
 * Return the current CSRF token, generating one if missing.
 */
function csrf_token(): string
{
    if (empty($_SESSION[CSRF_TOKEN_KEY])) {
        $_SESSION[CSRF_TOKEN_KEY] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_KEY];
}

/**
 * Render a hidden CSRF input for forms.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' .
           htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Validate a submitted CSRF token. On failure, halts the request.
 */
function verify_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $submitted = $_POST['csrf_token'] ?? '';
    $expected  = $_SESSION[CSRF_TOKEN_KEY] ?? '';
    if (!is_string($submitted) || !is_string($expected) || $expected === '' ||
        !hash_equals($expected, $submitted)) {
        http_response_code(419);
        die('CSRF validation failed. Please reload the page and try again.');
    }
}

/* ------------------------------------------------------------------ */
/* Auth state helpers                                                 */
/* ------------------------------------------------------------------ */

/** True when a user is logged in. */
function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']) && !empty($_SESSION['role']);
}

/** Returns the logged-in user's role (admin|patient) or null. */
function current_role(): ?string
{
    return $_SESSION['role'] ?? null;
}

/** Returns the logged-in user's id or null. */
function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

/** Returns the logged-in user's display name or empty string. */
function current_user_name(): string
{
    return $_SESSION['name'] ?? '';
}

/**
 * Redirect helper. Always exits.
 */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * Resolve the URL to the project root from any depth.
 * Pages in /admin or /patient must reach back to /index.php correctly.
 */
function base_url(string $path = ''): string
{
    // Determine prefix relative to this script's location.
    // We compute it from REQUEST_URI: e.g. /paar/admin/dashboard.php -> /paar/
    $script = $_SERVER['SCRIPT_NAME'] ?? '/';
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    // Strip the /admin or /patient suffix if present.
    $dir = preg_replace('#/(admin|patient|includes)$#', '', $dir);
    return $dir . '/' . ltrim($path, '/');
}

/**
 * Enforce that the current request is made by a user with the given role.
 *
 * @param 'admin'|'patient' $role
 */
function require_role(string $role): void
{
    if (!is_logged_in()) {
        redirect(base_url('login.php'));
    }
    if (current_role() !== $role) {
        // Wrong role for this page — send them to their own dashboard or login.
        if (current_role() === 'admin') {
            redirect(base_url('admin/dashboard.php'));
        }
        if (current_role() === 'patient') {
            redirect(base_url('patient/dashboard.php'));
        }
        redirect(base_url('login.php'));
    }
}

/* ------------------------------------------------------------------ */
/* Sanitisation helper                                                */
/* ------------------------------------------------------------------ */

/**
 * Convenience escape for echoing user data into HTML.
 */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/* ------------------------------------------------------------------ */
/* Flash messages (one-shot, stored in session)                       */
/* ------------------------------------------------------------------ */

/**
 * Push a flash message that will be shown on the next page load.
 *
 * @param string $type  one of: success, warning, danger, info
 * @param string $msg
 */
function flash(string $type, string $msg): void
{
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

/**
 * Render and clear all queued flash messages.
 */
function render_flashes(): string
{
    if (empty($_SESSION['flash'])) return '';
    $out = '';
    foreach ($_SESSION['flash'] as $f) {
        $type = in_array($f['type'], ['success','warning','danger','info'], true)
            ? $f['type'] : 'info';
        $out .= '<div class="alert alert--' . $type . '" data-flash="1" role="status">'
              . e($f['msg']) . '</div>';
    }
    $_SESSION['flash'] = [];
    return $out;
}

/* ------------------------------------------------------------------ */
/* Inline SVG icon library (Lucide-flavoured, currentColor strokes)   */
/* ------------------------------------------------------------------ */

/**
 * Render a small inline SVG icon by name.
 * All icons are 24x24 viewBox, stroke=currentColor — colour them via CSS.
 *
 * @param string $name   Icon name (see $paths below)
 * @param int    $size   Pixel size (square)
 * @param string $extra  Extra attributes appended to <svg> (already escaped)
 */
function icon(string $name, int $size = 18, string $extra = ''): string
{
    static $paths = [
        'home'      => '<path d="M3 12l9-9 9 9"/><path d="M5 10v10a1 1 0 001 1h4v-7h4v7h4a1 1 0 001-1V10"/>',
        'chart'     => '<path d="M3 3v18h18"/><path d="M7 14l3-3 3 3 5-5"/>',
        'users'     => '<path d="M17 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9.5" cy="7" r="4"/><path d="M22 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>',
        'user'      => '<path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'plus'      => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'check'     => '<path d="M20 6L9 17l-5-5"/>',
        'check-circle' => '<circle cx="12" cy="12" r="10"/><path d="M9 12l2 2 4-4"/>',
        'pill'      => '<path d="M10.5 20.5a4.95 4.95 0 010-7l7-7a4.95 4.95 0 117 7l-7 7a4.95 4.95 0 01-7 0z"/><line x1="8.5" y1="8.5" x2="15.5" y2="15.5"/>',
        'calendar'  => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'heart'     => '<path d="M20.84 4.6a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.07a5.5 5.5 0 00-7.78 7.78l1.06 1.07L12 21.23l7.78-7.78 1.06-1.07a5.5 5.5 0 000-7.78z"/>',
        'mail'      => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 7l-10 7L2 7"/>',
        'bell'      => '<path d="M6 8a6 6 0 0112 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 003.4 0"/>',
        'megaphone' => '<path d="M3 11v2a4 4 0 004 4h1l5 4V5L8 9H7a4 4 0 00-4 4z"/><path d="M19 8a5 5 0 010 8"/>',
        'logout'    => '<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'menu'      => '<line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/>',
        'x'         => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        'arrow-right' => '<path d="M5 12h14"/><path d="M12 5l7 7-7 7"/>',
        'shield'    => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'clock'     => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'activity'  => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
        'sparkle'   => '<path d="M12 3l1.9 5.4L19 10l-5.1 1.6L12 17l-1.9-5.4L5 10l5.1-1.6L12 3z"/>',
    ];
    $body = $paths[$name] ?? '';
    return '<svg viewBox="0 0 24 24" width="' . (int) $size . '" height="' . (int) $size
        . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"'
        . ($extra ? ' ' . $extra : '') . '>' . $body . '</svg>';
}

/**
 * Build up to two-letter initials from a person's name.
 * "Aisha Karanja" -> "AK"  ·  "John" -> "J"  ·  "" -> "?"
 */
function initials(string $name): string
{
    $name = trim($name);
    if ($name === '') return '?';
    $parts = preg_split('/\s+/u', $name);
    $first = mb_substr($parts[0], 0, 1, 'UTF-8');
    $last  = count($parts) > 1 ? mb_substr(end($parts), 0, 1, 'UTF-8') : '';
    return mb_strtoupper($first . $last, 'UTF-8');
}

/* ------------------------------------------------------------------ */
/* Notification count (used by topbar bell)                           */
/* ------------------------------------------------------------------ */

/**
 * Count unread in-app notifications for the current user.
 */
function unread_notifications_count(): int
{
    if (!is_logged_in()) return 0;
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0'
    );
    $stmt->execute([current_user_id()]);
    return (int) $stmt->fetchColumn();
}

/* ------------------------------------------------------------------ */
/* Patient helpers — shared between patient pages and cron.php        */
/* ------------------------------------------------------------------ */

/**
 * Resolve the patients.patient_id for the currently logged-in user.
 * Returns null when the session is not a patient.
 */
function current_patient_id(): ?int
{
    static $pid = null;
    if ($pid !== null) return $pid ?: null;
    if (current_role() !== 'patient') { $pid = 0; return null; }
    $stmt = db()->prepare('SELECT patient_id FROM patients WHERE user_id = ? LIMIT 1');
    $stmt->execute([current_user_id()]);
    $pid = (int) $stmt->fetchColumn();
    return $pid ?: null;
}

require_once __DIR__ . '/helpers.php';   // slots_for_medication() and other pure utils

/* ------------------------------------------------------------------ */
/* Security helpers (audit log, login lockout, password reset tokens) */
/* ------------------------------------------------------------------ */
/* Loaded last because security.php's helpers depend on db(),         */
/* current_user_id(), current_role(), client_ip(), etc. defined above.*/
require_once __DIR__ . '/security.php';
