<?php
/**
 * =====================================================================
 * PAAR — cron.php  (Reminder Engine)
 * ---------------------------------------------------------------------
 * Runs every hour (configure as a cron job). Performs four jobs:
 *
 *   1. Queue missing reminders for today and tomorrow for every active
 *      medication, so newly-prescribed meds and meds that span multiple
 *      days both get covered.
 *
 *   2. Send pending medication reminders whose reminder_time falls
 *      within the next MEDICATION_WINDOW_MINUTES.
 *
 *   3. Send pending appointment reminders whose reminder_time falls
 *      within the next APPOINTMENT_WINDOW_HOURS.
 *
 *   4. Missed-dose detection: for each active medication, mark a 'missed'
 *      adherence row + send the patient a notification when more than 4
 *      hours have passed since a scheduled dose time and the patient has
 *      no 'taken' record for that medication today. Dedup is per
 *      medication + patient + day, so at most one missed row per med/day.
 *
 * Each "send" performs two actions:
 *   (a) email via PHPMailer (if vendor/phpmailer is installed and SMTP
 *       creds in config.php are valid), and
 *   (b) creates an in-app row in `notifications` so the patient sees it
 *       in their inbox even if email fails.
 *
 * Activity is logged to logs/reminders.log.
 *
 * HOW TO RUN
 *   CLI:  php /path/to/paar/cron.php
 *   Cron: 0 * * * * /usr/bin/php /path/to/paar/cron.php >/dev/null 2>&1
 *   Web:  GET /paar/cron.php  (consider protecting with a token in
 *         production — see SECURITY note at bottom)
 * =====================================================================
 */

declare(strict_types=1);
require_once __DIR__ . '/database.php';              // db(), config constants
require_once __DIR__ . '/includes/helpers.php';      // slots_for_medication()
require_once __DIR__ . '/includes/mailer.php';       // paar_send_mail() / paar_mail_available()

/* ------------------------------------------------------------------ */
/* Access guard                                                       */
/* ---------------------------------------------------------------- - */
/* Allow CLI runs unconditionally. Web requests must include          */
/* ?token=CRON_TOKEN that matches the secret in config.php.           */
/* ------------------------------------------------------------------ */
$token = $_GET['token'] ?? '';
if (PHP_SAPI !== 'cli' && !hash_equals(CRON_TOKEN, (string) $token)) {
    http_response_code(403);
    exit('Forbidden');
}

$pdo = db();

/* ------------------------------------------------------------------ */
/* PHPMailer is loaded lazily by includes/mailer.php. If missing,     */
/* paar_send_mail() simply logs and skips — we still queue in-app     */
/* notifications.                                                     */
/* ------------------------------------------------------------------ */
$mailerAvailable = paar_mail_available();

/* ------------------------------------------------------------------ */
/* Logging                                                            */
/* ------------------------------------------------------------------ */
function cron_log(string $msg): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    @file_put_contents(LOG_PATH . '/reminders.log', $line, FILE_APPEND);
    if (PHP_SAPI === 'cli') echo $line;
}

/* ------------------------------------------------------------------ */
/* Email helper — delegates to includes/mailer.php so the same        */
/* SMTP plumbing is shared with the password-reset flow.              */
/* ------------------------------------------------------------------ */
function send_mail(string $to, string $toName, string $subject, string $bodyHtml): bool
{
    if (!paar_mail_available()) {
        cron_log("Mail SKIPPED (PHPMailer not installed) to {$to} — {$subject}");
        return false;
    }
    $ok = paar_send_mail($to, $toName, $subject, $bodyHtml);
    if (!$ok) cron_log('Mail ERROR to ' . $to . ' — see logs/mailer.log');
    return $ok;
}

/* ------------------------------------------------------------------ */
/* Insert an in-app notification (idempotent against accidental dup)  */
/* ------------------------------------------------------------------ */
function add_notification(PDO $pdo, int $userId, string $msg): void
{
    $pdo->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)')
        ->execute([$userId, $msg]);
}

cron_log('===== cron run started =====');
cron_log('PHPMailer available: ' . ($mailerAvailable ? 'yes' : 'no'));

/* ================================================================== */
/* JOB 1 — Queue missing reminders for today & tomorrow              */
/* ================================================================== */

$activeMeds = $pdo->query("
    SELECT medication_id, patient_id, medication_name, frequency, start_date, end_date
      FROM medications
     WHERE end_date >= CURDATE()
")->fetchAll();

$queueCheck = $pdo->prepare("
    SELECT 1 FROM reminders
     WHERE reference_id = ? AND reminder_type = 'medication' AND reminder_time = ?
     LIMIT 1
");
$queueInsert = $pdo->prepare("
    INSERT INTO reminders (patient_id, reference_id, reminder_type, reminder_time, sent_status)
    VALUES (?, ?, 'medication', ?, 'pending')
");

$queued = 0;
foreach ($activeMeds as $m) {
    foreach ([date('Y-m-d'), date('Y-m-d', strtotime('+1 day'))] as $d) {
        foreach (slots_for_medication($m['frequency'], $m['start_date'], $m['end_date'], $d) as $slot) {
            $slotTs = $d . ' ' . $slot['time'] . ':00';
            $queueCheck->execute([$m['medication_id'], $slotTs]);
            if ($queueCheck->fetchColumn()) continue;
            $queueInsert->execute([$m['patient_id'], $m['medication_id'], $slotTs]);
            $queued++;
        }
    }
}
cron_log("Job 1: queued $queued new medication reminder slots.");

/* ================================================================== */
/* JOB 2 — Send due medication reminders                              */
/* ================================================================== */

$dueMeds = $pdo->prepare("
    SELECT r.reminder_id, r.reminder_time, r.patient_id, r.reference_id AS medication_id,
           m.medication_name, m.dosage, m.frequency,
           u.user_id, u.email, u.name AS patient_name
      FROM reminders r
      JOIN medications m ON m.medication_id = r.reference_id
      JOIN patients   p ON p.patient_id    = r.patient_id
      JOIN users      u ON u.user_id       = p.user_id
     WHERE r.reminder_type = 'medication'
       AND r.sent_status   = 'pending'
       AND r.reminder_time <= NOW() + INTERVAL ? MINUTE
       AND r.reminder_time >= NOW() - INTERVAL 24 HOUR
       AND u.status = 'active'
     ORDER BY r.reminder_time ASC
");
$dueMeds->execute([MEDICATION_WINDOW_MINUTES]);

$medCount = 0;
foreach ($dueMeds as $r) {
    $when     = date('l, j M Y · H:i', strtotime($r['reminder_time']));
    $subject  = 'PAAR Reminder: time for ' . $r['medication_name'];
    $bodyHtml = '
        <div style="font-family:Arial,sans-serif;color:#0d1f17">
          <h2 style="color:#0b3d2e">Hello ' . htmlspecialchars($r['patient_name']) . ',</h2>
          <p>It is almost time to take your medication:</p>
          <p style="font-size:18px;background:#f4f6f3;padding:12px;border-radius:8px;border-left:4px solid #00c896">
             <b>' . htmlspecialchars($r['medication_name']) . '</b><br>
             Dosage: ' . htmlspecialchars($r['dosage']) . '<br>
             Scheduled: ' . htmlspecialchars($when) . '
          </p>
          <p>After taking it, please open PAAR and tap <b>Confirm taken</b> on your dashboard.</p>
          <p><a href="' . SITE_URL . '/patient/dashboard.php"
                style="background:#0b3d2e;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none;display:inline-block;font-weight:600">
                Open PAAR dashboard</a></p>
          <p style="color:#5a7066;font-size:12px;margin-top:24px">
             This is an automated reminder from ' . htmlspecialchars(SITE_NAME) . '.
          </p>
        </div>';
    $ok = send_mail($r['email'], $r['patient_name'], $subject, $bodyHtml);

    // Always create an in-app notification
    add_notification(
        $pdo,
        (int) $r['user_id'],
        'Time to take ' . $r['medication_name'] . ' (' . $r['dosage'] . ') — ' .
        date('H:i', strtotime($r['reminder_time']))
    );

    $pdo->prepare('UPDATE reminders SET sent_status = ? WHERE reminder_id = ?')
        ->execute([$ok ? 'sent' : 'failed', $r['reminder_id']]);

    cron_log(sprintf(
        'Med reminder #%d to %s (med #%d): email=%s, in-app=ok',
        $r['reminder_id'], $r['email'], $r['medication_id'], $ok ? 'sent' : 'failed'
    ));
    $medCount++;
}
cron_log("Job 2: processed $medCount medication reminders.");

/* ================================================================== */
/* JOB 3 — Send due appointment reminders                             */
/* ================================================================== */

$dueAppts = $pdo->prepare("
    SELECT r.reminder_id, r.reminder_time,
           a.appointment_id, a.appointment_date, a.doctor_name, a.department, a.reason,
           u.user_id, u.email, u.name AS patient_name
      FROM reminders r
      JOIN appointments a ON a.appointment_id = r.reference_id
      JOIN patients     p ON p.patient_id    = r.patient_id
      JOIN users        u ON u.user_id       = p.user_id
     WHERE r.reminder_type = 'appointment'
       AND r.sent_status   = 'pending'
       AND r.reminder_time <= NOW() + INTERVAL ? HOUR
       AND r.reminder_time >= NOW() - INTERVAL 48 HOUR
       AND a.status = 'scheduled'
       AND u.status = 'active'
     ORDER BY r.reminder_time ASC
");
$dueAppts->execute([APPOINTMENT_WINDOW_HOURS]);

$apptCount = 0;
foreach ($dueAppts as $r) {
    $when     = date('l, j M Y · H:i', strtotime($r['appointment_date']));
    $subject  = 'PAAR Reminder: appointment with Dr. ' . $r['doctor_name'];
    $bodyHtml = '
        <div style="font-family:Arial,sans-serif;color:#0d1f17">
          <h2 style="color:#0b3d2e">Hello ' . htmlspecialchars($r['patient_name']) . ',</h2>
          <p>This is a reminder that you have an upcoming appointment:</p>
          <p style="font-size:18px;background:#f4f6f3;padding:12px;border-radius:8px;border-left:4px solid #f0a500">
             <b>' . htmlspecialchars($when) . '</b><br>
             With: Dr. ' . htmlspecialchars($r['doctor_name']) . '<br>
             Department: ' . htmlspecialchars($r['department']) .
             ($r['reason'] ? '<br>Reason: ' . htmlspecialchars($r['reason']) : '') . '
          </p>
          <p><a href="' . SITE_URL . '/patient/appointments.php"
                style="background:#0b3d2e;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none;display:inline-block;font-weight:600">
                View your appointments</a></p>
          <p style="color:#5a7066;font-size:12px;margin-top:24px">
             This is an automated reminder from ' . htmlspecialchars(SITE_NAME) . '.
          </p>
        </div>';
    $ok = send_mail($r['email'], $r['patient_name'], $subject, $bodyHtml);

    add_notification(
        $pdo,
        (int) $r['user_id'],
        'Reminder: appointment with Dr. ' . $r['doctor_name'] .
        ' on ' . date('M j · H:i', strtotime($r['appointment_date']))
    );

    $pdo->prepare('UPDATE reminders SET sent_status = ? WHERE reminder_id = ?')
        ->execute([$ok ? 'sent' : 'failed', $r['reminder_id']]);

    cron_log(sprintf(
        'Appt reminder #%d to %s (appt #%d): email=%s, in-app=ok',
        $r['reminder_id'], $r['email'], $r['appointment_id'], $ok ? 'sent' : 'failed'
    ));
    $apptCount++;
}
cron_log("Job 3: processed $apptCount appointment reminders.");

/* ================================================================== */
/* === MISSED DOSE DETECTION ===                                      */
/* ------------------------------------------------------------------ */
/* For every currently-active medication, walk today's scheduled dose */
/* times. Mark a dose as MISSED (and notify the patient) when:        */
/*                                                                    */
/*   * the medication is active today (start_date <= today <= end)    */
/*   * the scheduled dose time is more than 4 hours in the past       */
/*   * the patient has NO 'taken' adherence row for this medication   */
/*     today                                                          */
/*   * no 'missed' adherence row already exists for this medication + */
/*     patient + today (idempotent — at most one per med/day)         */
/* ================================================================== */

$today           = date('Y-m-d');
$nowTs           = time();
$missedGraceSecs = 4 * 3600;     // 4-hour grace per spec

$takenTodayStmt = $pdo->prepare("
    SELECT 1 FROM adherence
     WHERE medication_id = ? AND patient_id = ?
       AND DATE(confirmation_time) = ?
       AND status = 'taken'
     LIMIT 1
");
$missedTodayStmt = $pdo->prepare("
    SELECT 1 FROM adherence
     WHERE medication_id = ? AND patient_id = ?
       AND DATE(confirmation_time) = ?
       AND status = 'missed'
     LIMIT 1
");
$insertMissed = $pdo->prepare("
    INSERT INTO adherence (medication_id, patient_id, slot_idx, status, confirmation_time, notes)
    VALUES (?, ?, ?, 'missed', NOW(), 'Auto-marked missed after 4-hour grace period')
");

// Pull active meds with patient user_id and medication name in one query.
$missedSrc = $pdo->query("
    SELECT m.medication_id, m.patient_id, m.medication_name, m.frequency,
           m.start_date, m.end_date, p.user_id
      FROM medications m
      JOIN patients p ON p.patient_id = m.patient_id
      JOIN users    u ON u.user_id    = p.user_id
     WHERE m.start_date <= CURDATE()
       AND m.end_date   >= CURDATE()
       AND u.status      = 'active'
")->fetchAll();

$missedCount = 0;
foreach ($missedSrc as $m) {
    $slots = slots_for_medication(
        $m['frequency'], $m['start_date'], $m['end_date'], $today
    );
    foreach ($slots as $slot) {
        $scheduledTs = strtotime($today . ' ' . $slot['time'] . ':00');
        if (($nowTs - $scheduledTs) <= $missedGraceSecs) continue;  // not overdue yet

        // Skip if patient already took ANY dose of this med today.
        $takenTodayStmt->execute([
            $m['medication_id'], $m['patient_id'], $today,
        ]);
        if ($takenTodayStmt->fetchColumn()) break;   // taken → no misses for the rest of today

        // Skip if a missed row already exists for this med+patient today.
        $missedTodayStmt->execute([
            $m['medication_id'], $m['patient_id'], $today,
        ]);
        if ($missedTodayStmt->fetchColumn()) break;  // already flagged for today

        $insertMissed->execute([$m['medication_id'], $m['patient_id'], $slot['idx']]);

        add_notification(
            $pdo,
            (int) $m['user_id'],
            'You missed your dose of ' . $m['medication_name'] .
            '. Please contact your healthcare provider.'
        );

        cron_log(sprintf(
            'MISSED: Patient #%d missed %s (scheduled %s, marked after 4hr grace)',
            (int) $m['patient_id'], $m['medication_name'], $slot['time']
        ));
        $missedCount++;
        break;   // one missed row per medication per day
    }
}
cron_log("Missed-dose detection: marked $missedCount dose(s) as missed.");

/* ================================================================== */
/* JOB 5 — Log retention                                             */
/* ================================================================== */

// 5a. Purge audit_log rows older than 365 days.
try {
    $deleted = $pdo->exec(
        "DELETE FROM audit_log WHERE created_at < (NOW() - INTERVAL 365 DAY)"
    );
    if ($deleted > 0) cron_log("Log retention: purged $deleted audit_log row(s) older than 1 year.");
} catch (Throwable $e) {
    cron_log('Log retention: audit_log purge failed — ' . $e->getMessage());
}

// 5b. Rotate reminders.log if it exceeds 5 MB.
$logFile = LOG_PATH . '/reminders.log';
if (is_file($logFile) && filesize($logFile) > 5 * 1024 * 1024) {
    $archive = LOG_PATH . '/reminders.' . date('Y-m-d') . '.log';
    @rename($logFile, $archive);
    cron_log("Log retention: rotated reminders.log → " . basename($archive));
}

cron_log('===== cron run finished =====');

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "cron run finished. Queued: $queued · Medication reminders: $medCount · " .
         "Appointment reminders: $apptCount · Missed marked: $missedCount\n";
}

/* =====================================================================
 * SECURITY NOTE
 * If this script is reachable from the public web (e.g., served by
 * Apache at /paar/cron.php), consider one of the following:
 *
 *   (a) Restrict execution to CLI by uncommenting:
 *       if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
 *
 *   (b) Require a shared-secret token in the query string:
 *       define('CRON_TOKEN', 'change-me');                 // in config.php
 *       if (($_GET['token'] ?? '') !== CRON_TOKEN) { http_response_code(403); exit; }
 *
 *   (c) Block via .htaccess / nginx location rules.
 * =====================================================================
 */
