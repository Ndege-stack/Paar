<?php
/**
 * =====================================================================
 * PAAR — includes/security.php
 * ---------------------------------------------------------------------
 * Cross-cutting security helpers used by login, register, logout,
 * forgot/reset-password, and admin mutation pages:
 *
 *   client_ip()                       Best-effort visitor IP (proxy-aware)
 *   audit_log(action, entity, ...)    Append a row to audit_log
 *
 *   record_login_attempt(email, ok)   Track auth attempts
 *   login_lockout_seconds(email)      Returns 0 or seconds remaining
 *
 *   create_password_reset(userId)     -> plain token (deliver via email)
 *   consume_password_reset(token, pw) -> bool
 *
 * All functions are no-throw, and degrade gracefully if the new tables
 * have not been migrated yet (so an un-migrated install can still log
 * in — just without the extra hardening).
 * =====================================================================
 */

require_once __DIR__ . '/auth_check.php';

/* -------------------------------------------------------------------- */
/* CONSTANTS                                                            */
/* -------------------------------------------------------------------- */
if (!defined('LOGIN_LOCK_WINDOW_SECONDS')) define('LOGIN_LOCK_WINDOW_SECONDS', 15 * 60);
if (!defined('LOGIN_LOCK_THRESHOLD'))      define('LOGIN_LOCK_THRESHOLD',      5);
if (!defined('PASSWORD_RESET_TTL_MINUTES')) define('PASSWORD_RESET_TTL_MINUTES', 60);

/* -------------------------------------------------------------------- */
/* Password complexity validator                                        */
/* -------------------------------------------------------------------- */

if (!function_exists('validate_password')) {
    /**
     * Validate a plain-text password against the application complexity rules.
     * Returns null on success, or an error string describing the failure.
     *
     * Rules (all must pass):
     *   - At least MIN_PASSWORD_LENGTH characters
     *   - At least one uppercase letter
     *   - At least one lowercase letter
     *   - At least one digit
     */
    function validate_password(string $password): ?string {
        $min = MIN_PASSWORD_LENGTH;
        if (strlen($password) < $min) {
            return "Password must be at least {$min} characters.";
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return 'Password must include at least one uppercase letter.';
        }
        if (!preg_match('/[a-z]/', $password)) {
            return 'Password must include at least one lowercase letter.';
        }
        if (!preg_match('/[0-9]/', $password)) {
            return 'Password must include at least one number.';
        }
        return null;
    }
}

/* -------------------------------------------------------------------- */
/* IP & user-agent                                                      */
/* -------------------------------------------------------------------- */

if (!function_exists('client_ip')) {
    /**
     * Resolve a best-effort client IP. Honours common proxy headers but
     * always falls back to REMOTE_ADDR. Never returns more than 45 chars.
     */
    function client_ip(): string {
        $candidates = [
            $_SERVER['HTTP_CF_CONNECTING_IP']  ?? null,   // Cloudflare
            $_SERVER['HTTP_X_FORWARDED_FOR']   ?? null,   // generic proxy (may be CSV)
            $_SERVER['HTTP_X_REAL_IP']         ?? null,
            $_SERVER['REMOTE_ADDR']            ?? null,
        ];
        foreach ($candidates as $val) {
            if (!$val) continue;
            $first = trim(explode(',', $val)[0]);
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                return substr($first, 0, 45);
            }
        }
        return '';
    }
}

if (!function_exists('client_user_agent')) {
    function client_user_agent(): string {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }
}

/* -------------------------------------------------------------------- */
/* Audit log                                                            */
/* -------------------------------------------------------------------- */

if (!function_exists('audit_log')) {
    /**
     * Append an entry to the audit_log table. Silently swallows DB errors
     * so a broken audit table can never break the user-facing flow.
     *
     * @param string      $action    e.g. 'login_success', 'patient_create'
     * @param string|null $entity    e.g. 'user', 'medication'
     * @param int|null    $entityId  Primary key of the affected row
     * @param array       $meta      Arbitrary structured context (JSON-encoded)
     * @param int|null    $actorId   Override the inferred actor (for failed logins)
     * @param string|null $actorRole Override the inferred role
     */
    function audit_log(
        string $action,
        ?string $entity = null,
        ?int $entityId = null,
        array $meta = [],
        ?int $actorId = null,
        ?string $actorRole = null
    ): void {
        try {
            $stmt = db()->prepare(
                'INSERT INTO audit_log
                    (actor_user_id, actor_role, action, entity, entity_id,
                     meta_json, ip, user_agent)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $actorId   ?? current_user_id(),
                $actorRole ?? current_role(),
                $action,
                $entity,
                $entityId,
                $meta ? json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
                client_ip() ?: null,
                client_user_agent() ?: null,
            ]);
        } catch (Throwable $e) {
            // Never let audit failures break the app. Log to file as a fallback.
            @file_put_contents(
                LOG_PATH . '/audit_fallback.log',
                '[' . date('Y-m-d H:i:s') . "] {$action} :: {$e->getMessage()}\n",
                FILE_APPEND
            );
        }
    }
}

/* -------------------------------------------------------------------- */
/* Login rate limiting                                                  */
/* -------------------------------------------------------------------- */

if (!function_exists('record_login_attempt')) {
    /** Record the outcome of an authentication attempt. */
    function record_login_attempt(string $email, bool $success): void {
        try {
            db()->prepare(
                'INSERT INTO login_attempts (ip, email, success) VALUES (?, ?, ?)'
            )->execute([client_ip(), substr($email, 0, 190), $success ? 1 : 0]);
        } catch (Throwable $e) {
            // Table missing or DB unhealthy — fail open, but log.
            @file_put_contents(
                LOG_PATH . '/audit_fallback.log',
                '[' . date('Y-m-d H:i:s') . "] login_attempt_record :: {$e->getMessage()}\n",
                FILE_APPEND
            );
        }
    }
}

if (!function_exists('login_lockout_seconds')) {
    /**
     * Return the number of seconds the caller must wait before retrying.
     * 0 means no lockout.
     *
     * Logic: count failed attempts in the last LOGIN_LOCK_WINDOW_SECONDS
     * for (this IP) OR (this email). If >= LOGIN_LOCK_THRESHOLD, return
     * the seconds remaining until the oldest counted attempt rolls out
     * of the window.
     */
    function login_lockout_seconds(string $email): int {
        try {
            $window = LOGIN_LOCK_WINDOW_SECONDS;
            $stmt = db()->prepare(
                'SELECT MIN(attempted_at) AS oldest, COUNT(*) AS n
                   FROM login_attempts
                  WHERE success = 0
                    AND attempted_at >= (NOW() - INTERVAL ? SECOND)
                    AND (ip = ? OR email = ?)'
            );
            $stmt->execute([$window, client_ip(), substr($email, 0, 190)]);
            $row = $stmt->fetch();
            if (!$row || (int) $row['n'] < LOGIN_LOCK_THRESHOLD) return 0;

            $oldestTs = strtotime($row['oldest'] ?? 'now');
            $unlockTs = $oldestTs + $window;
            $remaining = $unlockTs - time();
            return max(1, $remaining);
        } catch (Throwable $e) {
            return 0;   // fail open
        }
    }
}

/* -------------------------------------------------------------------- */
/* Password reset                                                       */
/* -------------------------------------------------------------------- */

if (!function_exists('create_password_reset')) {
    /**
     * Issue a fresh single-use reset token for a user. Returns the PLAIN
     * token (deliver to the user via email). Only the SHA-256 hash is
     * stored. Returns null on DB failure.
     */
    function create_password_reset(int $userId): ?string {
        try {
            $token  = bin2hex(random_bytes(32));   // 64 hex chars
            $hash   = hash('sha256', $token);
            $expiry = (new DateTime('+' . PASSWORD_RESET_TTL_MINUTES . ' minutes'))
                        ->format('Y-m-d H:i:s');

            db()->prepare(
                'INSERT INTO password_resets (user_id, token_hash, expires_at, ip)
                 VALUES (?, ?, ?, ?)'
            )->execute([$userId, $hash, $expiry, client_ip() ?: null]);
            return $token;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('find_valid_password_reset')) {
    /**
     * Look up an unused, non-expired reset token. Returns the row
     * (id, user_id, ...) or null.
     */
    function find_valid_password_reset(string $token): ?array {
        try {
            $hash = hash('sha256', $token);
            $stmt = db()->prepare(
                'SELECT id, user_id, expires_at
                   FROM password_resets
                  WHERE token_hash = ?
                    AND used_at IS NULL
                    AND expires_at > NOW()
                  LIMIT 1'
            );
            $stmt->execute([$hash]);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('consume_password_reset')) {
    /**
     * Apply a new password using a valid reset token. Marks the token
     * used, invalidates all OTHER unused tokens for that user, and
     * updates the password hash. Returns true on success.
     */
    function consume_password_reset(string $token, string $newPasswordPlain): bool {
        $row = find_valid_password_reset($token);
        if (!$row) return false;

        $pdo = db();
        try {
            $pdo->beginTransaction();

            $hash = password_hash($newPasswordPlain, PASSWORD_BCRYPT);
            $pdo->prepare('UPDATE users SET password = ? WHERE user_id = ?')
                ->execute([$hash, (int) $row['user_id']]);

            $pdo->prepare(
                'UPDATE password_resets SET used_at = NOW() WHERE id = ?'
            )->execute([(int) $row['id']]);

            // Burn any other still-valid tokens for this user.
            $pdo->prepare(
                'UPDATE password_resets
                    SET used_at = NOW()
                  WHERE user_id = ?
                    AND id <> ?
                    AND used_at IS NULL'
            )->execute([(int) $row['user_id'], (int) $row['id']]);

            $pdo->commit();

            audit_log(
                'password_reset_completed',
                'user',
                (int) $row['user_id'],
                [],
                (int) $row['user_id'],
                'patient'   // best effort; could be admin too — actor confirmed via user_id
            );
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return false;
        }
    }
}
