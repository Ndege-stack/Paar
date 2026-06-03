<?php
/**
 * =====================================================================
 * PAAR — admin/analytics.php
 * ---------------------------------------------------------------------
 * Three Chart.js visualisations:
 *   1. Adherence rate over time          (line, last 14 days)
 *   2. Appointment attendance breakdown  (doughnut)
 *   3. Top medications by non-adherence  (horizontal bar)
 * =====================================================================
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_role('admin');

$pdo = db();

/* ---- 1. Adherence rate over the last 14 days ---------------------- */
$rateRows = $pdo->query("
    SELECT DATE(confirmation_time) AS d,
           SUM(status='taken')  AS taken,
           SUM(status='missed') AS missed
      FROM adherence
     WHERE confirmation_time >= (CURDATE() - INTERVAL 13 DAY)
       AND status IN ('taken','missed')
     GROUP BY DATE(confirmation_time)
     ORDER BY d ASC
")->fetchAll();

$byDay = [];
foreach ($rateRows as $r) { $byDay[$r['d']] = $r; }

$rateLabels = [];
$rateSeries = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $rateLabels[] = date('M j', strtotime($d));
    $t = (int) ($byDay[$d]['taken']  ?? 0);
    $m = (int) ($byDay[$d]['missed'] ?? 0);
    $rateSeries[] = ($t + $m) > 0 ? round($t / ($t + $m) * 100, 1) : null;
}

/* ---- 2. Appointment attendance ----------------------------------- */
$attRows = $pdo->query("
    SELECT status, COUNT(*) AS c FROM appointments GROUP BY status
")->fetchAll();
$attMap = ['scheduled'=>0,'completed'=>0,'missed'=>0,'cancelled'=>0];
foreach ($attRows as $r) { $attMap[$r['status']] = (int) $r['c']; }

/* ---- 3. Top medications by missed doses (last 60 days) ----------- */
$topMissed = $pdo->query("
    SELECT m.medication_name, COUNT(*) AS missed
      FROM adherence a
      JOIN medications m ON m.medication_id = a.medication_id
     WHERE a.status='missed' AND a.confirmation_time >= (NOW() - INTERVAL 60 DAY)
     GROUP BY m.medication_name
     ORDER BY missed DESC
     LIMIT 8
")->fetchAll();
$topLabels = array_map(fn($r) => $r['medication_name'], $topMissed);
$topData   = array_map(fn($r) => (int) $r['missed'], $topMissed);

$page_title   = 'Analytics';
$page_section = 'analytics';
$use_chartjs  = true;
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="page-head">
    <div>
        <h1>Analytics</h1>
        <div class="subtitle">Insights into adherence and appointment outcomes.</div>
    </div>
</div>

<div class="card">
    <div class="card__header"><h3 class="card__title">Adherence rate — last 14 days</h3></div>
    <canvas id="rateChart" height="100"
            data-labels="<?= e(json_encode($rateLabels)) ?>"
            data-series="<?= e(json_encode($rateSeries)) ?>"></canvas>
</div>

<div class="grid-2 mt-5">
    <div class="card">
        <div class="card__header"><h3 class="card__title">Appointment attendance</h3></div>
        <canvas id="attChart" height="200"
                data-scheduled="<?= (int)$attMap['scheduled'] ?>"
                data-completed="<?= (int)$attMap['completed'] ?>"
                data-missed="<?= (int)$attMap['missed'] ?>"
                data-cancelled="<?= (int)$attMap['cancelled'] ?>"></canvas>
    </div>
    <div class="card">
        <div class="card__header"><h3 class="card__title">Top medications by missed doses (60d)</h3></div>
        <?php if (!$topMissed): ?>
            <div class="text-muted">No missed doses recorded.</div>
        <?php else: ?>
            <canvas id="topMissedChart" height="200"
                    data-labels="<?= e(json_encode($topLabels)) ?>"
                    data-series="<?= e(json_encode($topData)) ?>"></canvas>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;

    // 1. Adherence rate (line)
    var rateEl = document.getElementById('rateChart');
    if (rateEl) new Chart(rateEl, {
        type: 'line',
        data: {
            labels: JSON.parse(rateEl.dataset.labels),
            datasets: [{
                label: 'Adherence %',
                data: JSON.parse(rateEl.dataset.series),
                borderColor: '#00c896',
                backgroundColor: 'rgba(0,200,150,0.12)',
                tension: 0.35, fill: true, spanGaps: true,
                pointBackgroundColor: '#00c896', pointRadius: 3
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } } }
        }
    });

    // 2. Appointment attendance (doughnut)
    var attEl = document.getElementById('attChart');
    if (attEl) new Chart(attEl, {
        type: 'doughnut',
        data: {
            labels: ['Scheduled','Completed','Missed','Cancelled'],
            datasets: [{
                data: [attEl.dataset.scheduled, attEl.dataset.completed,
                       attEl.dataset.missed, attEl.dataset.cancelled].map(Number),
                backgroundColor: ['#00c896','#1b9070','#e63946','#a7bfb3']
            }]
        },
        options: { plugins: { legend: { position: 'bottom' } } }
    });

    // 3. Top missed (horizontal bar)
    var topEl = document.getElementById('topMissedChart');
    if (topEl) new Chart(topEl, {
        type: 'bar',
        data: {
            labels: JSON.parse(topEl.dataset.labels),
            datasets: [{
                label: 'Missed doses',
                data: JSON.parse(topEl.dataset.series),
                backgroundColor: '#e63946'
            }]
        },
        options: {
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
