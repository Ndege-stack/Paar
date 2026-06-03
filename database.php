<?php
/**
 * =====================================================================
 * PAAR System — Database Connection (PDO)
 * ---------------------------------------------------------------------
 * Returns a singleton PDO instance configured for safety:
 *   - Real prepared statements (emulation OFF)
 *   - Exceptions on error
 *   - Associative-array fetch by default
 *   - utf8mb4 charset
 *
 * Usage:
 *   require_once __DIR__ . '/database.php';
 *   $pdo  = db();
 *   $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
 *   $stmt->execute([$email]);
 * =====================================================================
 */

// Mark bootstrap so config.php knows it is being loaded legitimately.
define('PAAR_BOOTSTRAP_OK', true);

require_once __DIR__ . '/config.php';

/**
 * Return a shared PDO connection. The same handle is reused for the
 * lifetime of the request to avoid the overhead of repeated TCP handshakes.
 *
 * @return PDO
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,           // throw on error
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,                 // assoc fetch
        PDO::ATTR_EMULATE_PREPARES   => false,                            // real prepares
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

        /* ----------------------------------------------------------- *
         * Align MySQL session time zone with PHP's default zone.      *
         * Without this, NOW() / CURDATE() / TIMESTAMP defaults run on *
         * the MySQL server clock (often UTC on MAMP), which causes    *
         * "confirmed at 12:40" while the wall clock says 22:30. We    *
         * use the numeric offset (e.g. +03:00) so it works even when  *
         * the MySQL named-zone tables are not loaded.                 *
         * ----------------------------------------------------------- */
        $tzOffset = (new DateTime('now', new DateTimeZone(date_default_timezone_get())))->format('P');
        $pdo->exec("SET time_zone = '{$tzOffset}'");
    } catch (PDOException $e) {
        // In DEBUG, surface the message; in production show a generic error.
        if (DEBUG) {
            http_response_code(500);
            die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
        }
        http_response_code(500);
        die('A database error occurred. Please try again later.');
    }

    return $pdo;
}
