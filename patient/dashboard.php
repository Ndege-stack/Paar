<?php
/**
 * =====================================================================
 * PAAR — patient/dashboard.php
 * ---------------------------------------------------------------------
 * Patient home page. Shows:
 *   - Today's medication schedule with a Confirm button per dose
 *   - Adherence streak (consecutive days with at least one taken and
 *     zero missed)
 *   - Next upcoming appointment
 *   - Unread notification badge (in the topbar)
 * =====================================================================
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_role('patient');

$pdo       = db();
$patientId = current_patient_id();
if (!$patientId) {
    flash('danger', 'Patient profile missing. Please contact your clinic.');
    redirect(base_url('logout.php'));
}

$today = date('Y-m-d');

/* ---- Active medications for today --------------------------------- */
$stmt = $pdo->prepare("
    SELECT * FROM medications
     WHERE patient_id = ? AND start_date <= ? AND end_date >= ?
     ORDER BY medication_name
");
$stmt->execute([$patientId, $today, $today]);
$activeMeds = $stmt->fetchAll();

/* ---- Build today's schedule — bulk adherence lookup --------------- */
// Fetch all of today's adherence rows for this patient in one query.
$adStmt = $pdo->prepare("
    SELECT medication_id, slot_idx, status, confirmation_time
      FROM adherence
     WHERE patient_id = ?
       AND DATE(confirmation_time) = ?
     ORDER BY confirmation_time DESC
");
$adStmt->execute([$patientId, $today]);
$adBySlot = [];   // keyed as "medId:slotIdx" -> row
foreach ($adStmt->fetchAll() as $adRow) {
    $key = $adRow['medication_id'] . ':' . $adRow['slot_idx'];
    if (!isset($adBySlot[$key])) {   // keep the most-recent row per slot
        $adBySlot[$key] = $adRow;
    }
}

$schedule = [];
foreach ($activeMeds as $m) {
    foreach (slots_for_medication($m['frequency'], $m['start_date'], $m['end_date'], $today) as $slot) {
        $key = $m['medication_id'] . ':' . $slot['idx'];
        $row = $adBySlot[$key] ?? null;
        $schedule[] = [
            'medication_id' => (int) $m['medication_id'],
            'name'          => $m['medication_name'],
            'dosage'        => $m['dosage'],
            'slot_idx'      => $slot['idx'],
            'slot_label'    => $slot['label'],
            'slot_time'     => $slot['time'],
            'status'        => $row ? $row['status'] : 'pending',
            'confirmed_at'  => $row['confirmation_time'] ?? null,
        ];
    }
}
usort($schedule, fn($a, $b) => strcmp($a['slot_time'], $b['slot_time']));

/* ---- Next upcoming appointment ----------------------------------- */
$stmt = $pdo->prepare("
    SELECT * FROM appointments
     WHERE patient_id = ? AND appointment_date >= NOW() AND status = 'scheduled'
     ORDER BY appointment_date ASC LIMIT 1
");
$stmt->execute([$patientId]);
$nextAppt = $stmt->fetch();

/* ---- Adherence streak — single SQL query -------------------------- */
// Fetch the last 60 days of per-day taken/missed counts in one query,
// then walk the result in PHP to count consecutive clean days.
$streakStmt = $pdo->prepare("
    SELECT DATE(confirmation_time)   AS d,
           SUM(status='taken')       AS taken,
           SUM(status='missed')      AS missed
      FROM adherence
     WHERE patient_id = ?
       AND confirmation_time >= (CURDATE() - INTERVAL 59 DAY)
     GROUP BY DATE(confirmation_time)
");
$streakStmt->execute([$patientId]);
$streakByDay = [];
foreach ($streakStmt->fetchAll() as $sr) {
    $streakByDay[$sr['d']] = $sr;
}
$streak = 0;
for ($i = 0; $i < 60; $i++) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $t = (int) ($streakByDay[$d]['taken']  ?? 0);
    $m = (int) ($streakByDay[$d]['missed'] ?? 0);
    if ($i === 0) {
        if ($m > 0) break;
        if ($t > 0) $streak++;
    } else {
        if ($t > 0 && $m === 0) $streak++;
        else break;
    }
}

/* ---- 30-day adherence rate ---------------------------------------- */
$stmt = $pdo->prepare("
    SELECT SUM(status='taken') AS t, SUM(status='missed') AS m
      FROM adherence
     WHERE patient_id = ? AND confirmation_time >= (NOW() - INTERVAL 30 DAY)
");
$stmt->execute([$patientId]);
$rate = $stmt->fetch() ?: ['t'=>0,'m'=>0];
$total = (int)$rate['t'] + (int)$rate['m'];
$adhPct = $total > 0 ? round(((int)$rate['t'] / $total) * 100, 1) : 0;

$page_title   = 'Dashboard';
$page_section = 'dashboard';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_patient.php';
?>

<div class="page-head">
    <div>
        <h1>Hello, <?= e(current_user_name()) ?> 👋</h1>
        <div class="subtitle">Here's your plan for <?= e(date('l, j M Y')) ?>.</div>
    </div>
</div>

<div class="stats">
    <div class="stat stat--success">
        <div class="stat__label">Adherence streak</div>
        <div class="stat__value"><?= (int) $streak ?></div>
        <div class="stat__sub">consecutive day<?= $streak===1?'':'s' ?> on track</div>
    </div>
    <div class="stat">
        <div class="stat__label">30-day adherence</div>
        <div class="stat__value"><?= e($adhPct) ?>%</div>
        <div class="stat__sub"><?= (int)$rate['t'] ?> taken · <?= (int)$rate['m'] ?> missed</div>
    </div>
    <div class="stat stat--warning">
        <div class="stat__label">Today's doses</div>
        <div class="stat__value"><?= count($schedule) ?></div>
        <div class="stat__sub">scheduled for today</div>
    </div>
</div>

<div class="grid-2">
    <!-- Today's schedule -->
    <div class="card">
        <div class="card__header">
            <h3 class="card__title">Today's medication schedule</h3>
            <a class="btn btn--ghost btn--sm" href="<?= e(base_url('patient/medications.php')) ?>">All medications →</a>
        </div>

        <?php if (!$schedule): ?>
            <div class="empty-state empty-state--inline">
                <?= icon('pill', 24) ?>
                <div>
                    <div class="empty-state__title">No medications today</div>
                    <div class="empty-state__desc">Your clinic hasn't assigned any active medications yet.</div>
                </div>
            </div>
        <?php else: ?>
            <ul class="dose-list">
                <?php foreach ($schedule as $s):
                    $cls = $s['status']==='taken'  ? 'dose--taken'
                         : ($s['status']==='missed' ? 'dose--missed' : 'dose--pending');
                ?>
                    <li class="dose <?= $cls ?>">
                        <div>
                            <div class="dose__name"><?= e($s['name']) ?> — <?= e($s['dosage']) ?></div>
                            <div class="dose__meta">
                                <?= e($s['slot_label']) ?> · scheduled <?= e($s['slot_time']) ?>
                                <?php if ($s['confirmed_at']): ?>
                                    · confirmed <?= e(date('H:i', strtotime($s['confirmed_at']))) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <?php if ($s['status'] === 'taken'): ?>
                                <span class="badge badge--success">Taken</span>
                            <?php elseif ($s['status'] === 'missed'): ?>
                                <span class="badge badge--danger badge--missed-strong">⚠ Missed</span>
                            <?php else: ?>
                                <form method="post" action="<?= e(base_url('patient/confirm_medication.php')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="medication_id" value="<?= (int) $s['medication_id'] ?>">
                                    <input type="hidden" name="slot_idx"      value="<?= (int) $s['slot_idx'] ?>">
                                    <button class="btn btn--success btn--sm" type="submit">Confirm taken</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <!-- Next appointment -->
    <div class="card">
        <div class="card__header"><h3 class="card__title">Next appointment</h3></div>
        <?php if (!$nextAppt): ?>
            <div class="text-muted">No upcoming appointments.</div>
        <?php else: ?>
            <div class="mt-2"><b><?= e(date('l, j M Y · H:i', strtotime($nextAppt['appointment_date']))) ?></b></div>
            <div class="mt-2">With <b><?= e($nextAppt['doctor_name']) ?></b> · <?= e($nextAppt['department']) ?></div>
            <?php if (!empty($nextAppt['reason'])): ?>
                <div class="text-muted mt-2"><?= e($nextAppt['reason']) ?></div>
            <?php endif; ?>
            <div class="mt-4">
                <a class="btn btn--outline btn--sm" href="<?= e(base_url('patient/appointments.php')) ?>">All appointments →</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
