<?php
/**
 * =====================================================================
 * PAAR — admin/appointments.php
 * ---------------------------------------------------------------------
 * Schedule new appointments, list all appointments, update their status
 * (scheduled / completed / missed / cancelled), and delete them.
 *
 * On schedule: queues a reminder 24 hours before the appointment so
 * cron.php can email the patient.
 * =====================================================================
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_role('admin');

$pdo = db();

/* ---- POST actions ------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $errors = [];
        $patientId = (int) ($_POST['patient_id'] ?? 0);
        $when      = $_POST['appointment_date'] ?? '';
        $doctor    = trim($_POST['doctor_name'] ?? '');
        $dept      = trim($_POST['department']  ?? '');
        $reason    = trim($_POST['reason']      ?? '');

        if ($patientId <= 0) $errors[] = 'Patient required.';
        if (!$doctor)        $errors[] = 'Doctor name required.';
        if (!$dept)          $errors[] = 'Department required.';
        $dt = DateTime::createFromFormat('Y-m-d\TH:i', $when)
           ?: DateTime::createFromFormat('Y-m-d H:i:s', $when);
        if (!$dt) $errors[] = 'Valid appointment date/time required.';

        if (!$errors) {
            try {
                $pdo->beginTransaction();
                $whenSql = $dt->format('Y-m-d H:i:s');

                $pdo->prepare(
                    'INSERT INTO appointments
                        (patient_id, appointment_date, doctor_name, department, reason, status)
                     VALUES (?, ?, ?, ?, ?, "scheduled")'
                )->execute([$patientId, $whenSql, $doctor, $dept, $reason ?: null]);
                $apptId = (int) $pdo->lastInsertId();

                // Queue a reminder 24 h before.
                $remindAt = (clone $dt)->modify('-24 hours')->format('Y-m-d H:i:s');
                $pdo->prepare(
                    'INSERT INTO reminders
                        (patient_id, reference_id, reminder_type, reminder_time, sent_status)
                     VALUES (?, ?, "appointment", ?, "pending")'
                )->execute([$patientId, $apptId, $remindAt]);

                // Notify the patient
                $stmtPU = $pdo->prepare(
                    'SELECT u.user_id FROM users u
                       JOIN patients p ON p.user_id = u.user_id
                      WHERE p.patient_id = ?'
                );
                $stmtPU->execute([$patientId]);
                if ($puId = $stmtPU->fetchColumn()) {
                    $pdo->prepare(
                        'INSERT INTO notifications (user_id, message) VALUES (?, ?)'
                    )->execute([
                        $puId,
                        'New appointment scheduled with Dr. ' . $doctor .
                        ' on ' . $dt->format('M j, Y · H:i') . ' (' . $dept . ').'
                    ]);
                }

                $pdo->commit();
                audit_log('appointment_create', 'appointment', $apptId, [
                    'patient_id'       => $patientId,
                    'appointment_date' => $whenSql,
                    'doctor'           => $doctor,
                    'department'       => $dept,
                ]);
                flash('success', 'Appointment scheduled.');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                flash('danger', DEBUG ? $e->getMessage() : 'Could not schedule appointment.');
            }
        } else {
            flash('danger', implode(' ', $errors));
        }
        redirect(base_url('admin/appointments.php'));
    }

    if ($action === 'update_status') {
        $id     = (int) ($_POST['appointment_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if ($id > 0 && in_array($status, ['scheduled','completed','missed','cancelled'], true)) {
            $pdo->prepare('UPDATE appointments SET status = ? WHERE appointment_id = ?')
                ->execute([$status, $id]);
            audit_log('appointment_update_status', 'appointment', $id, ['status' => $status]);
            flash('success', 'Appointment updated.');
        }
        redirect(base_url('admin/appointments.php'));
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['appointment_id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM appointments WHERE appointment_id = ?')->execute([$id]);
            audit_log('appointment_delete', 'appointment', $id);
            flash('success', 'Appointment removed.');
        }
        redirect(base_url('admin/appointments.php'));
    }
}

/* ---- Data --------------------------------------------------------- */
$patients = $pdo->query("
    SELECT p.patient_id, u.name FROM patients p JOIN users u ON u.user_id = p.user_id
     WHERE u.status='active' ORDER BY u.name
")->fetchAll();

$preselect = (int) ($_GET['patient_id'] ?? 0);

$rows = $pdo->query("
    SELECT a.*, u.name AS patient_name, p.patient_id
      FROM appointments a
      JOIN patients p ON p.patient_id = a.patient_id
      JOIN users    u ON u.user_id    = p.user_id
     ORDER BY a.appointment_date DESC
")->fetchAll();

$page_title   = 'Appointments';
$page_section = 'appointments';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="page-head">
    <div>
        <h1>Appointments</h1>
        <div class="subtitle">Schedule visits and update outcomes.</div>
    </div>
</div>

<div class="grid-2">
    <div class="table-wrap">
        <div class="table-toolbar">
            <input type="search" class="input search js-table-search"
                   data-target="#apptTable" placeholder="Search by patient, doctor, department...">
            <span class="text-muted"><?= count($rows) ?> total</span>
        </div>
        <table class="table" id="apptTable">
            <thead><tr>
                <th data-sort="date">Date</th>
                <th data-sort="text">Patient</th>
                <th data-sort="text">Doctor</th>
                <th data-sort="text">Department</th>
                <th data-sort="text">Status</th>
                <th>Actions</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="6" class="table-empty">No appointments yet.</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td><?= e(date('M j, Y · H:i', strtotime($r['appointment_date']))) ?></td>
                    <td><a href="<?= e(base_url('admin/patient_detail.php?id=' . $r['patient_id'])) ?>"><?= e($r['patient_name']) ?></a></td>
                    <td><?= e($r['doctor_name']) ?></td>
                    <td><?= e($r['department']) ?></td>
                    <td>
                        <form method="post" class="form-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action"         value="update_status">
                            <input type="hidden" name="appointment_id" value="<?= (int) $r['appointment_id'] ?>">
                            <select name="status" class="input input--compact"
                                    onchange="this.form.submit()">
                                <?php foreach (['scheduled','completed','missed','cancelled'] as $s): ?>
                                    <option value="<?= $s ?>" <?= $r['status']===$s?'selected':'' ?>>
                                        <?= e(ucfirst($s)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </td>
                    <td>
                        <form method="post"
                              data-confirm="Delete the appointment with Dr. <?= e($r['doctor_name']) ?> on <?= e(date('M j, Y · H:i', strtotime($r['appointment_date']))) ?>? This cannot be undone."
                              data-confirm-title="Delete appointment?"
                              data-confirm-action="Delete appointment"
                              data-confirm-variant="danger">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action"         value="delete">
                            <input type="hidden" name="appointment_id" value="<?= (int) $r['appointment_id'] ?>">
                            <button type="submit" class="btn btn--danger btn--sm">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <div class="card__header"><h3 class="card__title">Schedule appointment</h3></div>
        <form method="post" novalidate>
            <div class="form-group">
                <label for="patient_id">Patient *</label>
                <select id="patient_id" name="patient_id" class="input" required>
                    <option value="">Select patient...</option>
                    <?php foreach ($patients as $p): ?>
                        <option value="<?= (int) $p['patient_id'] ?>"
                            <?= $preselect === (int) $p['patient_id'] ? 'selected' : '' ?>>
                            <?= e($p['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="appointment_date">Date & time *</label>
                    <input id="appointment_date" name="appointment_date" type="datetime-local"
                           class="input" required min="<?= e(date('Y-m-d\TH:i')) ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="doctor_name">Doctor *</label>
                    <input id="doctor_name" name="doctor_name" type="text" class="input" required
                           placeholder="e.g. Dr. Mwangi">
                </div>
                <div class="form-group">
                    <label for="department">Department *</label>
                    <input id="department" name="department" type="text" class="input" required
                           placeholder="e.g. Outpatient, Cardiology">
                </div>
            </div>
            <div class="form-group">
                <label for="reason">Reason</label>
                <textarea id="reason" name="reason" class="input"
                          placeholder="Reason for visit..."></textarea>
            </div>

            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <button type="submit" class="btn btn--accent">Schedule</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
