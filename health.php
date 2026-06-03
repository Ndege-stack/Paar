<?php
/**
 * =====================================================================
 * PAAR — health.php
 * ---------------------------------------------------------------------
 * Lightweight health check endpoint for uptime monitors (UptimeRobot,
 * cron-job.org, etc.). Returns JSON with:
 *   status  – "ok" or "error"
 *   db      – "ok" or "error: <message>"
 *   version – application identifier
 *   ts      – current server timestamp
 *
 * Returns HTTP 200 when everything is healthy, 503 otherwise.
 * This endpoint is intentionally kept public so monitors don't need
 * credentials, but reveals nothing sensitive about the system.
 * =====================================================================
 */

// Minimal bootstrap — just config + DB, no session/auth overhead.
define('PAAR_BOOTSTRAP_OK', true);
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$result = [
    'status'  => 'ok',
    'db'      => 'ok',
    'version' => SITE_NAME . '/1.0',
    'ts'      => date('c'),
];

// Minimal DB check — one ping query, no data returned.
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME),
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]
    );
    $pdo->query('SELECT 1');
} catch (Throwable $e) {
    $result['status'] = 'error';
    $result['db']     = 'error';   // never expose $e->getMessage() publicly
    http_response_code(503);
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
