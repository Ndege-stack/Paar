<?php
/**
 * =====================================================================
 * PAAR — patient/appointments.php
 * ---------------------------------------------------------------------
 * Patient view of all their appointments, with upcoming and past
 * appointments separated for clarity.
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

$stmt = $pdo->prepare("
    SELECT * FROM appointments
     WHERE patient_id = ? AND appointment_date >= NOW()
     ORDER BY appointment_date ASC
");
$stmt->execute([$patientId]);
$upcoming = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT * FROM appointments
     WHERE patient_id = ? AND appointment_date < NOW()
     ORDER BY appointment_date DESC LIMIT 20
");
$stmt->execute([$patientId]);
$past = $stmt->fetchAll();

$page_title   = 'My Appointments';
$page_section = 'appointments';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_patient.php';
?>

<div class="page-head">
    <div>
        <h1>My appointments</h1>
        <div class="subtitle">Upcoming visits and recent history.</div>
    </div>
</div>

<div class="card">
    <div class="card__header"><h3 class="card__title">Upcoming</h3></div>
    <?php if (!$upcoming): ?>
        <div class="text-muted">No upcoming appointments.</div>
    <?php else: ?>
        <table class="table">
            <thead><tr>
                <th>When</th><th>Doctor</th><th>Department</th><th>Reason</th><th>Status</th>
            </tr></thead>
            <tbody>
            <?php foreach ($upcoming as $a): ?>
                <tr>
                    <td><?= e(date('l, j M Y · H:i', strtotime($a['appointment_date']))) ?></td>
                    <td><?= e($a['doctor_name']) ?></td>
                    <td><?= e($a['department']) ?></td>
                    <td><?= e($a['reason'] ?: '—') ?></td>
                    <td>
                        <span class="badge <?=
                            $a['status']==='completed' ? 'badge--success' :
                           ($a['status']==='missed'    ? 'badge--danger'  :
                           ($a['status']==='cancelled' ? 'badge--muted'   : 'badge--info'))
                        ?>"><?= e(ucfirst($a['status'])) ?></span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card__header"><h3 class="card__title">Past appointments</h3></div>
    <?php if (!$past): ?>
        <div class="text-muted">No past appointments yet.</div>
    <?php else: ?>
        <table class="table">
            <thead><tr>
                <th>When</th><th>Doctor</th><th>Department</th><th>Status</th>
            </tr></thead>
            <tbody>
            <?php foreach ($past as $a): ?>
                <tr>
                    <td><?= e(date('M j, Y · H:i', strtotime($a['appointment_date']))) ?></td>
                    <td><?= e($a['doctor_name']) ?></td>
                    <td><?= e($a['department']) ?></td>
                    <td>
                        <span class="badge <?=
                            $a['status']==='completed' ? 'badge--success' :
                           ($a['status']==='missed'    ? 'badge--danger'  :
                           ($a['status']==='cancelled' ? 'badge--muted'   : 'badge--info'))
                        ?>"><?= e(ucfirst($a['status'])) ?></span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
