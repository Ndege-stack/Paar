<?php
/**
 * =====================================================================
 * PAAR — admin/dashboard.php
 * ---------------------------------------------------------------------
 * Administrator landing page with KPI tiles, a 7-day adherence trend
 * chart, recent adherence activity feed, and quick-action buttons.
 * =====================================================================
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_role('admin');

$pdo = db();

/* ---- KPIs --------------------------------------------------------- */
$totalPatients = (int) $pdo->query(
    "SELECT COUNT(*) FROM users WHERE role='patient' AND status='active'"
)->fetchColumn();

$activeMeds = (int) $pdo->query(
    "SELECT COUNT(*) FROM medications WHERE end_date >= CURDATE() AND start_date <= CURDATE()"
)->fetchColumn();

$apptsToday = (int) $pdo->query(
    "SELECT COUNT(*) FROM appointments WHERE DATE(appointment_date) = CURDATE()"
)->fetchColumn();

// Overall adherence rate = taken / (taken + missed) over the last 30 days.
$row = $pdo->query("
    SELECT
        SUM(status='taken')  AS taken,
        SUM(status='missed') AS missed
      FROM adherence
     WHERE confirmation_time >= (NOW() - INTERVAL 30 DAY)
")->fetch();
$taken  = (int) ($row['taken']  ?? 0);
$missed = (int) ($row['missed'] ?? 0);
$adherenceRate = ($taken + $missed) > 0
    ? round(($taken / ($taken + $missed)) * 100, 1)
    : 0.0;

/* ---- Recent activity (last 5 adherence rows) ---------------------- */
$recent = $pdo->query("
    SELECT a.status, a.confirmation_time, m.medication_name, u.name AS patient_name
      FROM adherence a
      JOIN medications m ON m.medication_id = a.medication_id
      JOIN patients   p ON p.patient_id    = a.patient_id
      JOIN users      u ON u.user_id       = p.user_id
     WHERE a.status IN ('taken','missed')
     ORDER BY a.confirmation_time DESC
     LIMIT 5
")->fetchAll();

/* ---- 7-day adherence trend (for Chart.js) ------------------------- */
$trend = $pdo->query("
    SELECT DATE(confirmation_time) AS d,
           SUM(status='taken')     AS taken,
           SUM(status='missed')    AS missed
      FROM adherence
     WHERE confirmation_time >= (CURDATE() - INTERVAL 6 DAY)
       AND status IN ('taken','missed')
     GROUP BY DATE(confirmation_time)
     ORDER BY d ASC
")->fetchAll();

// Build a continuous 7-day series so days without data show as 0.
$labels = [];
$takenSeries = [];
$missedSeries = [];
$byDay = [];
foreach ($trend as $r) { $byDay[$r['d']] = $r; }
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $labels[]       = date('D', strtotime($d));
    $takenSeries[]  = (int) ($byDay[$d]['taken']  ?? 0);
    $missedSeries[] = (int) ($byDay[$d]['missed'] ?? 0);
}

$page_title   = 'Dashboard';
$page_section = 'dashboard';
$use_chartjs  = true;
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="page-head">
    <div>
        <h1>Welcome back, <?= e(current_user_name()) ?>.</h1>
        <div class="subtitle">Here is what is happening across your facility today.</div>
    </div>
    <div class="btn-row">
        <a class="btn" href="<?= e(base_url('admin/add_patient.php')) ?>">+ Add Patient</a>
        <a class="btn btn--accent" href="<?= e(base_url('admin/appointments.php')) ?>">Schedule Appointment</a>
        <a class="btn btn--outline" href="<?= e(base_url('admin/medications.php')) ?>">Assign Medication</a>
    </div>
</div>

<!-- KPI tiles -->
<div class="stats">
    <div class="stat">
        <div class="stat__label">Total active patients</div>
        <div class="stat__value"><?= number_format($totalPatients) ?></div>
        <div class="stat__sub">Approved and currently active</div>
    </div>
    <div class="stat stat--warning">
        <div class="stat__label">Active medications</div>
        <div class="stat__value"><?= number_format($activeMeds) ?></div>
        <div class="stat__sub">In effect today</div>
    </div>
    <div class="stat">
        <div class="stat__label">Appointments today</div>
        <div class="stat__value"><?= number_format($apptsToday) ?></div>
        <div class="stat__sub"><?= e(date('l, j M Y')) ?></div>
    </div>
    <div class="stat <?= $adherenceRate >= 80 ? 'stat--success' : ($adherenceRate >= 50 ? 'stat--warning' : 'stat--danger') ?>">
        <div class="stat__label">Overall adherence (30d)</div>
        <div class="stat__value"><?= e($adherenceRate) ?>%</div>
        <div class="stat__sub">Taken vs. missed across all patients</div>
    </div>
</div>

<div class="grid-2">
    <!-- Trend chart -->
    <div class="card">
        <div class="card__header">
            <h3 class="card__title">Adherence trend — last 7 days</h3>
        </div>
        <canvas id="adherenceTrend" height="120"
                data-labels="<?= e(json_encode($labels)) ?>"
                data-taken="<?= e(json_encode($takenSeries)) ?>"
                data-missed="<?= e(json_encode($missedSeries)) ?>"></canvas>
    </div>

    <!-- Recent activity -->
    <div class="card">
        <div class="card__header">
            <h3 class="card__title">Recent adherence activity</h3>
            <a class="btn btn--ghost btn--sm" href="<?= e(base_url('admin/adherence.php')) ?>">View all →</a>
        </div>
        <?php if (!$recent): ?>
            <div class="text-muted">No confirmations recorded yet.</div>
        <?php else: ?>
            <ul class="activity">
                <?php foreach ($recent as $r): ?>
                    <li>
                        <span class="badge <?= $r['status']==='taken' ? 'badge--success' : 'badge--danger' ?>">
                            <?= e(ucfirst($r['status'])) ?>
                        </span>
                        <div class="flex-1">
                            <div><b><?= e($r['patient_name']) ?></b> — <?= e($r['medication_name']) ?></div>
                            <div class="when"><?= e(date('M j, Y · H:i', strtotime($r['confirmation_time']))) ?></div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;
    const el = document.getElementById('adherenceTrend');
    if (!el) return;
    new Chart(el, {
        type: 'bar',
        data: {
            labels:   JSON.parse(el.dataset.labels),
            datasets: [
                { label: 'Taken',  data: JSON.parse(el.dataset.taken),  backgroundColor: '#00c896' },
                { label: 'Missed', data: JSON.parse(el.dataset.missed), backgroundColor: '#e63946' }
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } },
            scales: {
                x: { stacked: true, grid: { display: false } },
                y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } }
            }
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
