<?php
/**
 * =====================================================================
 * PAAR — admin/patient_detail.php
 * ---------------------------------------------------------------------
 * Detailed view of a single patient: profile, adherence summary,
 * current medications and recent appointments.
 *
 * URL:  admin/patient_detail.php?id={patient_id}
 * =====================================================================
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/security.php';
require_role('admin');

$pdo = db();
$patientId = (int) ($_GET['id'] ?? 0);
if ($patientId <= 0) {
    flash('danger', 'Patient not found.');
    redirect(base_url('admin/patients.php'));
}

/* ---- POST: delete patient ----------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_patient') {
    verify_csrf();
    $delId = (int) ($_POST['patient_id'] ?? 0);
    if ($delId > 0) {
        // Fetch name for audit before deleting
        $nameRow = $pdo->prepare('SELECT u.name, u.email FROM users u JOIN patients p ON p.user_id=u.user_id WHERE p.patient_id=? LIMIT 1');
        $nameRow->execute([$delId]);
        $nameData = $nameRow->fetch();
        // CASCADE deletes patients, medications, adherence, appointments, notifications, reminders
        $pdo->prepare("DELETE FROM users WHERE user_id = (SELECT user_id FROM patients WHERE patient_id = ?) AND role = 'patient'")
            ->execute([$delId]);
        audit_log('patient_delete', 'user', $delId, [
            'name'  => $nameData['name']  ?? '',
            'email' => $nameData['email'] ?? '',
        ]);
        flash('success', 'Patient record permanently deleted.');
    }
    redirect(base_url('admin/patients.php'));
}

/* ---- Profile ------------------------------------------------------ */
$stmt = $pdo->prepare("
    SELECT p.*, u.name, u.email, u.status, u.created_at AS joined_at
      FROM patients p
      JOIN users    u ON u.user_id = p.user_id
     WHERE p.patient_id = ?
     LIMIT 1
");
$stmt->execute([$patientId]);
$patient = $stmt->fetch();
if (!$patient) {
    flash('danger', 'Patient not found.');
    redirect(base_url('admin/patients.php'));
}

/* ---- Adherence summary (last 30 days) ----------------------------- */
$stmt = $pdo->prepare("
    SELECT
        SUM(status='taken')   AS taken,
        SUM(status='missed')  AS missed,
        SUM(status='pending') AS pending
      FROM adherence
     WHERE patient_id = ? AND confirmation_time >= (NOW() - INTERVAL 30 DAY)
");
$stmt->execute([$patientId]);
$ad = $stmt->fetch() ?: ['taken'=>0,'missed'=>0,'pending'=>0];
$total = (int)$ad['taken'] + (int)$ad['missed'];
$rate  = $total > 0 ? round(((int)$ad['taken'] / $total) * 100, 1) : 0;

/* ---- Medications -------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT * FROM medications
     WHERE patient_id = ?
     ORDER BY end_date >= CURDATE() DESC, start_date DESC
");
$stmt->execute([$patientId]);
$meds = $stmt->fetchAll();

/* ---- Appointments ------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT * FROM appointments WHERE patient_id = ? ORDER BY appointment_date DESC LIMIT 10
");
$stmt->execute([$patientId]);
$appts = $stmt->fetchAll();

$page_title   = 'Patient · ' . $patient['name'];
$page_section = 'patients';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="page-head">
    <div>
        <h1><?= e($patient['name']) ?></h1>
        <div class="subtitle">
            <?= e($patient['email']) ?> ·
            <span class="badge <?= $patient['status']==='active'?'badge--success':($patient['status']==='pending'?'badge--warning':'badge--danger') ?>">
                <?= e(ucfirst($patient['status'])) ?>
            </span>
            · Joined <?= e(date('M j, Y', strtotime($patient['joined_at']))) ?>
        </div>
    </div>
    <div class="btn-row">
        <a class="btn btn--outline" href="<?= e(base_url('admin/patients.php')) ?>">← Back</a>
        <a class="btn btn--accent"
           href="<?= e(base_url('admin/medications.php?patient_id=' . $patientId)) ?>">Assign medication</a>
        <a class="btn"
           href="<?= e(base_url('admin/appointments.php?patient_id=' . $patientId)) ?>">Schedule appointment</a>
        <form method="post" style="display:inline"
              data-confirm="Permanently delete <?= e($patient['name']) ?>'s account and ALL their data (medications, adherence, appointments, notifications)? This cannot be undone."
              data-confirm-ok="Delete permanently" data-confirm-variant="danger"
              data-confirm-title="Delete patient data?">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_patient">
            <input type="hidden" name="patient_id" value="<?= (int) $patientId ?>">
            <button type="submit" class="btn btn--danger">Delete patient</button>
        </form>
    </div>
</div>

<div class="grid-2">
    <div>
        <!-- Profile card -->
        <div class="card">
            <div class="card__header"><h3 class="card__title">Profile</h3></div>
            <div class="form-row">
                <div><label>Phone</label><div><?= e($patient['phone'] ?: '—') ?></div></div>
                <div><label>Date of birth</label><div><?= e($patient['date_of_birth'] ?: '—') ?></div></div>
                <div><label>Gender</label><div><?= e(ucfirst($patient['gender'] ?: '—')) ?></div></div>
                <div><label>Address</label><div><?= e($patient['address'] ?: '—') ?></div></div>
                <div><label>Emergency contact</label><div><?= e($patient['emergency_contact'] ?: '—') ?></div></div>
                <div><label>Emergency phone</label><div><?= e($patient['emergency_phone'] ?: '—') ?></div></div>
            </div>
        </div>

        <!-- Medications card -->
        <div class="card">
            <div class="card__header">
                <h3 class="card__title">Medications</h3>
                <span class="text-muted"><?= count($meds) ?> total</span>
            </div>
            <?php if (!$meds): ?>
                <div class="text-muted">No medications assigned yet.</div>
            <?php else: ?>
                <table class="table">
                    <thead><tr>
                        <th>Medication</th><th>Dosage</th><th>Frequency</th>
                        <th>Period</th><th>Status</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($meds as $m):
                        $active = ($m['start_date'] <= date('Y-m-d') && $m['end_date'] >= date('Y-m-d'));
                    ?>
                        <tr>
                            <td><b><?= e($m['medication_name']) ?></b><?= $m['notes'] ? '<div class="text-muted text-12">' . e($m['notes']) . '</div>' : '' ?></td>
                            <td><?= e($m['dosage']) ?></td>
                            <td><?= e(str_replace('_',' ', $m['frequency'])) ?></td>
                            <td><?= e($m['start_date']) ?> → <?= e($m['end_date']) ?></td>
                            <td><span class="badge <?= $active ? 'badge--success' : 'badge--muted' ?>"><?= $active ? 'Active' : 'Ended' ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Appointments card -->
        <div class="card">
            <div class="card__header">
                <h3 class="card__title">Recent appointments</h3>
            </div>
            <?php if (!$appts): ?>
                <div class="text-muted">No appointments scheduled.</div>
            <?php else: ?>
                <table class="table">
                    <thead><tr>
                        <th>Date</th><th>Doctor</th><th>Department</th><th>Status</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($appts as $ap): ?>
                        <tr>
                            <td><?= e(date('M j, Y · H:i', strtotime($ap['appointment_date']))) ?></td>
                            <td><?= e($ap['doctor_name']) ?></td>
                            <td><?= e($ap['department']) ?></td>
                            <td><span class="badge <?=
                                $ap['status']==='completed' ? 'badge--success' :
                               ($ap['status']==='missed'    ? 'badge--danger'  :
                               ($ap['status']==='cancelled' ? 'badge--muted'   : 'badge--info'))
                            ?>"><?= e(ucfirst($ap['status'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div>
        <!-- Adherence summary -->
        <div class="card">
            <div class="card__header"><h3 class="card__title">Adherence — last 30 days</h3></div>
            <div class="stat__value text-center"><?= e($rate) ?>%</div>
            <div class="text-muted text-center mb-3">
                <?= (int)$ad['taken'] ?> taken · <?= (int)$ad['missed'] ?> missed · <?= (int)$ad['pending'] ?> pending
            </div>
            <canvas id="patientAdh" height="160"></canvas>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;
    new Chart(document.getElementById('patientAdh'), {
        type: 'doughnut',
        data: {
            labels: ['Taken', 'Missed', 'Pending'],
            datasets: [{
                data: [<?= (int)$ad['taken'] ?>, <?= (int)$ad['missed'] ?>, <?= (int)$ad['pending'] ?>],
                backgroundColor: ['#00c896', '#e63946', '#f0a500']
            }]
        },
        options: { plugins: { legend: { position: 'bottom' } } }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
