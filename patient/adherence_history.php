<?php
/**
 * =====================================================================
 * PAAR — patient/adherence_history.php
 * ---------------------------------------------------------------------
 * Patient's personal adherence log with a 14-day chart and a paginated
 * table of recent confirmations.
 * =====================================================================
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_role('patient');

$pdo       = db();
$patientId = current_patient_id();
if (!$patientId) {
    flash('danger', 'Patient profile missing.');
    redirect(base_url('logout.php'));
}

/* ---- 14-day trend ------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT DATE(confirmation_time) AS d,
           SUM(status='taken')  AS taken,
           SUM(status='missed') AS missed
      FROM adherence
     WHERE patient_id = ? AND confirmation_time >= (CURDATE() - INTERVAL 13 DAY)
       AND status IN ('taken','missed')
     GROUP BY DATE(confirmation_time)
     ORDER BY d ASC
");
$stmt->execute([$patientId]);
$byDay = [];
foreach ($stmt->fetchAll() as $r) { $byDay[$r['d']] = $r; }

$labels = $taken = $missed = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $labels[] = date('M j', strtotime($d));
    $taken[]  = (int) ($byDay[$d]['taken']  ?? 0);
    $missed[] = (int) ($byDay[$d]['missed'] ?? 0);
}

/* ---- KPIs --------------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT SUM(status='taken')   AS taken,
           SUM(status='missed')  AS missed,
           SUM(status='pending') AS pending
      FROM adherence
     WHERE patient_id = ? AND confirmation_time >= (NOW() - INTERVAL 30 DAY)
");
$stmt->execute([$patientId]);
$k = $stmt->fetch() ?: ['taken'=>0,'missed'=>0,'pending'=>0];
$totalCounted = (int)$k['taken'] + (int)$k['missed'];
$rate = $totalCounted > 0 ? round(((int)$k['taken'] / $totalCounted) * 100, 1) : 0;

/* ---- Recent log --------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT a.*, m.medication_name, m.dosage
      FROM adherence a
      JOIN medications m ON m.medication_id = a.medication_id
     WHERE a.patient_id = ?
     ORDER BY a.confirmation_time DESC
     LIMIT 100
");
$stmt->execute([$patientId]);
$rows = $stmt->fetchAll();

$page_title   = 'Adherence History';
$page_section = 'history';
$use_chartjs  = true;
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_patient.php';
?>

<div class="page-head">
    <div>
        <h1>Your adherence history</h1>
        <div class="subtitle">Track how you've been doing over time.</div>
    </div>
</div>

<div class="stats">
    <div class="stat stat--success">
        <div class="stat__label">Doses taken (30d)</div>
        <div class="stat__value"><?= (int)$k['taken'] ?></div>
    </div>
    <div class="stat stat--danger">
        <div class="stat__label">Doses missed (30d)</div>
        <div class="stat__value"><?= (int)$k['missed'] ?></div>
    </div>
    <div class="stat <?= $rate>=80?'stat--success':($rate>=50?'stat--warning':'stat--danger') ?>">
        <div class="stat__label">Adherence rate</div>
        <div class="stat__value"><?= e($rate) ?>%</div>
    </div>
</div>

<div class="card">
    <div class="card__header"><h3 class="card__title">Last 14 days</h3></div>
    <canvas id="historyChart" height="100"></canvas>
</div>

<div class="card">
    <div class="card__header">
        <h3 class="card__title">Recent confirmations</h3>
        <span class="text-muted"><?= count($rows) ?> records</span>
    </div>
    <?php if (!$rows): ?>
        <div class="text-muted">No confirmations yet. Visit your dashboard to confirm today's doses.</div>
    <?php else: ?>
        <table class="table">
            <thead><tr>
                <th data-sort="date">When</th>
                <th data-sort="text">Medication</th>
                <th data-sort="text">Dosage</th>
                <th data-sort="text">Status</th>
                <th>Notes</th>
            </tr></thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= e(date('M j, Y · H:i', strtotime($r['confirmation_time']))) ?></td>
                        <td><b><?= e($r['medication_name']) ?></b></td>
                        <td><?= e($r['dosage']) ?></td>
                        <td><span class="badge <?=
                            $r['status']==='taken'  ? 'badge--success' :
                           ($r['status']==='missed' ? 'badge--danger'  : 'badge--warning')
                        ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                        <td><?= e($r['notes'] ?: '—') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;
    new Chart(document.getElementById('historyChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [
                { label: 'Taken',  data: <?= json_encode($taken)  ?>, backgroundColor: '#00c896' },
                { label: 'Missed', data: <?= json_encode($missed) ?>, backgroundColor: '#e63946' }
            ]
        },
        options: {
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
