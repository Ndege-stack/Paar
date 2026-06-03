<?php
/**
 * =====================================================================
 * PAAR — admin/patients.php
 * ---------------------------------------------------------------------
 * Searchable, sortable list of all patients (active + suspended).
 * Admin actions: View, Suspend / Re-activate.
 * =====================================================================
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/security.php';
require_role('admin');

$pdo = db();

/* ---- POST actions: suspend / activate ----------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $userId = (int) ($_POST['user_id'] ?? 0);

    if ($userId > 0 && in_array($action, ['suspend','activate'], true)) {
        $newStatus = $action === 'suspend' ? 'suspended' : 'active';
        $stmt = $pdo->prepare(
            "UPDATE users SET status = ? WHERE user_id = ? AND role = 'patient'"
        );
        $stmt->execute([$newStatus, $userId]);
        audit_log('patient_' . $action, 'user', $userId, ['new_status' => $newStatus]);
        flash('success', $action === 'suspend'
            ? 'Patient account suspended.'
            : 'Patient account re-activated.');
        redirect(base_url('admin/patients.php'));
    }
}

/* ---- Server-side search ------------------------------------------ */
$search = trim((string) ($_GET['q'] ?? ''));

/* ---- Fetch patients ----------------------------------------------- */
$baseQuery = "
    SELECT
        u.user_id, u.name, u.email, u.status, u.created_at,
        p.patient_id, p.phone,
        COUNT(DISTINCT m.medication_id)          AS med_count,
        MAX(CASE WHEN a.status='taken' THEN a.confirmation_time END) AS last_taken
      FROM users u
      JOIN patients p     ON p.user_id     = u.user_id
      LEFT JOIN medications m ON m.patient_id = p.patient_id
      LEFT JOIN adherence   a ON a.patient_id = p.patient_id
     WHERE u.role = 'patient'";

if ($search !== '') {
    $baseQuery .= " AND (u.name LIKE ? OR u.email LIKE ? OR p.phone LIKE ?)";
    $likeVal = '%' . $search . '%';
    $searchParams = [$likeVal, $likeVal, $likeVal];
} else {
    $searchParams = [];
}
$baseQuery .= " GROUP BY u.user_id, u.name, u.email, u.status, u.created_at, p.patient_id, p.phone
               ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($baseQuery);
$stmt->execute($searchParams);
$rows = $stmt->fetchAll();

$page_title   = 'Patients';
$page_section = 'patients';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="page-head">
    <div>
        <h1>Patients</h1>
        <div class="subtitle">All registered patients in your facility.</div>
    </div>
    <div class="btn-row">
        <a class="btn btn--outline" href="<?= e(base_url('admin/export.php?type=patients')) ?>">↓ Export CSV</a>
        <a class="btn" href="<?= e(base_url('admin/add_patient.php')) ?>">+ Add Patient</a>
    </div>
</div>

<div class="table-wrap">
    <form class="table-toolbar" method="get" role="search">
        <input
            type="search"
            name="q"
            class="input search"
            value="<?= e($search) ?>"
            placeholder="Search by name, email, phone..."
            aria-label="Search patients">
        <button class="btn btn--primary btn--sm" type="submit">Search</button>
        <?php if ($search !== ''): ?>
            <a class="btn btn--outline btn--sm" href="<?= e(base_url('admin/patients.php')) ?>">Clear</a>
        <?php endif; ?>
        <span class="text-muted"><?= count($rows) ?> patient<?= count($rows)===1?'':'s' ?>
            <?= $search !== '' ? 'matching &ldquo;' . e($search) . '&rdquo;' : '' ?>
        </span>
    </form>

    <table class="table" id="patientsTable">
        <thead>
            <tr>
                <th data-sort="text">Name</th>
                <th data-sort="text">Email</th>
                <th data-sort="text">Phone</th>
                <th data-sort="text">Status</th>
                <th data-sort="number">Medications</th>
                <th data-sort="date">Last adherence</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="7" class="table-empty">
                <div class="empty-state">
                    <div class="empty-state__icon"><?= icon('users', 28) ?></div>
                    <div class="empty-state__title">No patients yet</div>
                    <div class="empty-state__desc">Add your first patient to begin tracking adherence.</div>
                    <a class="btn btn--accent btn--sm" href="<?= e(base_url('admin/add_patient.php')) ?>">+ Add Patient</a>
                </div>
            </td></tr>
        <?php else: foreach ($rows as $r): ?>
            <tr>
                <td><b><?= e($r['name']) ?></b></td>
                <td><?= e($r['email']) ?></td>
                <td><?= e($r['phone'] ?: '—') ?></td>
                <td>
                    <span class="badge <?= $r['status']==='active' ? 'badge--success'
                                          : ($r['status']==='pending' ? 'badge--warning' : 'badge--danger') ?>">
                        <?= e(ucfirst($r['status'])) ?>
                    </span>
                </td>
                <td><?= (int) $r['med_count'] ?></td>
                <td><?= $r['last_taken'] ? e(date('M j, Y · H:i', strtotime($r['last_taken']))) : '—' ?></td>
                <td>
                    <div class="actions">
                        <a class="btn btn--outline btn--sm"
                           href="<?= e(base_url('admin/patient_detail.php?id=' . $r['patient_id'])) ?>">View</a>
                        <?php if ($r['status'] !== 'suspended'): ?>
                            <form method="post" class="form-inline"
                                  data-confirm="Suspend <?= e($r['name']) ?>'s account? They will be unable to sign in until you re-activate them."
                                  data-confirm-title="Suspend patient?"
                                  data-confirm-action="Suspend"
                                  data-confirm-variant="danger">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action"  value="suspend">
                                <input type="hidden" name="user_id" value="<?= (int) $r['user_id'] ?>">
                                <button type="submit" class="btn btn--danger btn--sm">Suspend</button>
                            </form>
                        <?php else: ?>
                            <form method="post" class="form-inline"
                                  data-confirm="Re-activate <?= e($r['name']) ?>? They will be able to sign in again."
                                  data-confirm-title="Re-activate patient?"
                                  data-confirm-action="Re-activate">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action"  value="activate">
                                <input type="hidden" name="user_id" value="<?= (int) $r['user_id'] ?>">
                                <button type="submit" class="btn btn--success btn--sm">Re-activate</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
