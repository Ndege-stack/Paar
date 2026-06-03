<?php
/**
 * =====================================================================
 * PAAR — admin/adherence.php
 * ---------------------------------------------------------------------
 * Adherence monitoring dashboard. Shows recent adherence events with
 * filtering by patient and status, plus quick KPIs.
 * =====================================================================
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_role('admin');

$pdo = db();

/* ---- Filters ------------------------------------------------------ */
$patientFilter = (int) ($_GET['patient_id'] ?? 0);
$statusFilter  = $_GET['status'] ?? '';
$validStatuses = ['taken','missed','pending'];
if ($statusFilter !== '' && !in_array($statusFilter, $validStatuses, true)) $statusFilter = '';

$where = [];
$params = [];
if ($patientFilter > 0) { $where[] = 'a.patient_id = ?'; $params[] = $patientFilter; }
if ($statusFilter !== '') { $where[] = 'a.status = ?'; $params[] = $statusFilter; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* ---- Pagination --------------------------------------------------- */
$perPage     = 50;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$offset      = ($currentPage - 1) * $perPage;

$countStmt = $pdo->prepare("
    SELECT COUNT(*)
      FROM adherence a
      JOIN medications m ON m.medication_id = a.medication_id
      JOIN patients   p ON p.patient_id    = a.patient_id
      JOIN users      u ON u.user_id       = p.user_id
      $whereSql
");
$countStmt->execute($params);
$totalRows  = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));
if ($currentPage > $totalPages) { $currentPage = $totalPages; $offset = ($currentPage - 1) * $perPage; }

$sql = "
    SELECT a.*, m.medication_name, m.dosage, u.name AS patient_name, p.patient_id
      FROM adherence a
      JOIN medications m ON m.medication_id = a.medication_id
      JOIN patients   p ON p.patient_id    = a.patient_id
      JOIN users      u ON u.user_id       = p.user_id
      $whereSql
     ORDER BY a.confirmation_time DESC
     LIMIT $perPage OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

/* ---- KPIs over last 30 days --------------------------------------- */
$kpiParams = [];
$kpiWhere  = "confirmation_time >= (NOW() - INTERVAL 30 DAY)";
if ($patientFilter > 0) { $kpiWhere .= " AND patient_id = ?"; $kpiParams[] = $patientFilter; }
$kpiStmt = $pdo->prepare("
    SELECT
        SUM(status='taken')   AS taken,
        SUM(status='missed')  AS missed,
        SUM(status='pending') AS pending
      FROM adherence
     WHERE $kpiWhere
");
$kpiStmt->execute($kpiParams);
$kpi = $kpiStmt->fetch() ?: ['taken'=>0,'missed'=>0,'pending'=>0];
$totalCounted = (int)$kpi['taken'] + (int)$kpi['missed'];
$rate = $totalCounted > 0 ? round(((int)$kpi['taken'] / $totalCounted) * 100, 1) : 0;

/* ---- Top patients by missed doses (last 30 days) ------------------ */
/* Surfaces the patients most at risk so admin can act immediately.    */
$missLeaderParams = [];
$missLeaderWhere  = "a.status = 'missed' AND a.confirmation_time >= (NOW() - INTERVAL 30 DAY)";
if ($patientFilter > 0) {
    $missLeaderWhere   .= ' AND a.patient_id = ?';
    $missLeaderParams[] = $patientFilter;
}
$missLeaderStmt = $pdo->prepare("
    SELECT a.patient_id,
           u.name                                        AS patient_name,
           COUNT(*)                                      AS missed_count,
           MAX(a.confirmation_time)                      AS last_missed_at
      FROM adherence a
      JOIN patients p ON p.patient_id = a.patient_id
      JOIN users    u ON u.user_id    = p.user_id
     WHERE $missLeaderWhere
     GROUP BY a.patient_id, u.name
     ORDER BY missed_count DESC, last_missed_at DESC
     LIMIT 5
");
$missLeaderStmt->execute($missLeaderParams);
$missLeaders = $missLeaderStmt->fetchAll();

/* ---- Patients dropdown -------------------------------------------- */
$patients = $pdo->query("
    SELECT p.patient_id, u.name FROM patients p JOIN users u ON u.user_id = p.user_id
     WHERE u.role='patient' ORDER BY u.name
")->fetchAll();

$page_title   = 'Adherence';
$page_section = 'adherence';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="page-head">
    <div>
        <h1>Adherence monitoring</h1>
        <div class="subtitle">Track medication confirmations across your facility.</div>
    </div>
    <a class="btn btn--outline" href="<?= e(base_url('admin/export.php?type=adherence')) ?>">↓ Export CSV</a>
</div>

<div class="stats">
    <div class="stat stat--success">
        <div class="stat__label">Doses taken (30d)</div>
        <div class="stat__value"><?= (int)$kpi['taken'] ?></div>
    </div>
    <div class="stat stat--danger">
        <div class="stat__label">Doses missed (30d)</div>
        <div class="stat__value"><?= (int)$kpi['missed'] ?></div>
    </div>
    <div class="stat stat--warning">
        <div class="stat__label">Pending</div>
        <div class="stat__value"><?= (int)$kpi['pending'] ?></div>
    </div>
    <div class="stat <?= $rate>=80?'stat--success':($rate>=50?'stat--warning':'stat--danger') ?>">
        <div class="stat__label">Adherence rate</div>
        <div class="stat__value"><?= e($rate) ?>%</div>
    </div>
</div>

<?php if ($missLeaders): ?>
<div class="card mb-4">
    <div class="card__header">
        <h3 class="card__title">⚠ Patients with missed doses · last 30 days</h3>
        <span class="text-muted">Top <?= count($missLeaders) ?></span>
    </div>
    <?php foreach ($missLeaders as $L): ?>
        <a class="miss-leader"
           href="<?= e(base_url('admin/adherence.php?patient_id=' . (int) $L['patient_id'] . '&status=missed')) ?>"
           style="text-decoration:none">
            <div>
                <div class="miss-leader__name"><?= e($L['patient_name']) ?></div>
                <div class="miss-leader__meta">
                    Last missed <?= e(date('M j, Y · H:i', strtotime($L['last_missed_at']))) ?>
                </div>
            </div>
            <span class="miss-leader__count"><?= (int) $L['missed_count'] ?></span>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card mb-4">
    <form method="get" class="form-row form-row--end">
        <div class="form-group form-group--tight">
            <label for="patient_id">Patient</label>
            <select id="patient_id" name="patient_id" class="input">
                <option value="0">All patients</option>
                <?php foreach ($patients as $p): ?>
                    <option value="<?= (int)$p['patient_id'] ?>"
                        <?= $patientFilter===(int)$p['patient_id']?'selected':'' ?>>
                        <?= e($p['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group form-group--tight">
            <label for="status">Status</label>
            <select id="status" name="status" class="input">
                <option value="">All statuses</option>
                <?php foreach ($validStatuses as $s): ?>
                    <option value="<?= $s ?>" <?= $statusFilter===$s?'selected':'' ?>><?= e(ucfirst($s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group form-group--tight">
            <button class="btn btn--primary" type="submit">Apply</button>
            <a class="btn btn--outline" href="<?= e(base_url('admin/adherence.php')) ?>">Reset</a>
        </div>
    </form>
</div>

<div class="table-wrap">
    <div class="table-toolbar">
        <span class="text-muted">
            <?= number_format($totalRows) ?> record<?= $totalRows===1?'':'s' ?>
            <?php if ($totalPages > 1): ?>
                &nbsp;· Page <?= $currentPage ?> of <?= $totalPages ?>
            <?php endif; ?>
        </span>
    </div>
    <table class="data-table">
        <thead><tr>
            <th>When</th><th>Patient</th><th>Medication</th><th>Dosage</th><th>Status</th><th>Notes</th>
        </tr></thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="6" class="table-empty">No adherence records match your filters.</td></tr>
        <?php else: foreach ($rows as $r):
            $isMissed = ($r['status'] === 'missed');
        ?>
            <tr<?= $isMissed ? ' class="row--missed"' : '' ?>>
                <td><?= e(date('M j, Y · H:i', strtotime($r['confirmation_time']))) ?></td>
                <td><a href="<?= e(base_url('admin/patient_detail.php?id=' . $r['patient_id'])) ?>"><?= e($r['patient_name']) ?></a></td>
                <td><?= e($r['medication_name']) ?></td>
                <td><?= e($r['dosage']) ?></td>
                <td>
                    <?php if ($isMissed): ?>
                        <span class="badge badge--danger badge--missed-strong">⚠ Missed</span>
                    <?php elseif ($r['status'] === 'taken'): ?>
                        <span class="badge badge--success">Taken</span>
                    <?php else: ?>
                        <span class="badge badge--warning">Pending</span>
                    <?php endif; ?>
                </td>
                <td><?= e($r['notes'] ?: '—') ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php
/* ---- Pagination controls ----------------------------------------- */
if ($totalPages > 1):
    // Build base query string without 'page' parameter
    $qp = array_filter(['patient_id' => $patientFilter ?: null, 'status' => $statusFilter ?: null]);
    $baseQuery = $qp ? '?' . http_build_query($qp) . '&' : '?';
?>
<div class="pagination">
    <?php if ($currentPage > 1): ?>
        <a class="btn btn--outline btn--sm" href="<?= e(base_url('admin/adherence.php') . $baseQuery . 'page=' . ($currentPage - 1)) ?>">← Prev</a>
    <?php endif; ?>
    <?php for ($p = max(1, $currentPage - 2); $p <= min($totalPages, $currentPage + 2); $p++): ?>
        <a class="btn btn--sm <?= $p === $currentPage ? 'btn--primary' : 'btn--outline' ?>"
           href="<?= e(base_url('admin/adherence.php') . $baseQuery . 'page=' . $p) ?>"><?= $p ?></a>
    <?php endfor; ?>
    <?php if ($currentPage < $totalPages): ?>
        <a class="btn btn--outline btn--sm" href="<?= e(base_url('admin/adherence.php') . $baseQuery . 'page=' . ($currentPage + 1)) ?>">Next →</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
