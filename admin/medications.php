<?php
/**
 * =====================================================================
 * PAAR — admin/medications.php
 * ---------------------------------------------------------------------
 * Assign new medications to patients, list all existing prescriptions,
 * and delete medications. Optionally pre-selects a patient via
 *   ?patient_id={id}  (used from patient_detail.php).
 *
 * Side-effect on insert: a row is queued in the `reminders` table at
 * (start_date, time-of-day depending on frequency) so cron.php can
 * process it. We seed only the next reminder slot; cron.php then keeps
 * generating future ones as needed.
 * =====================================================================
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/security.php';
require_role('admin');

$pdo = db();

/** Map a frequency to a list of HH:MM slots used for queuing reminders. */
function frequency_slots(string $freq): array {
    return match ($freq) {
        'once_daily'         => ['08:00'],
        'twice_daily'        => ['08:00','20:00'],
        'three_times_daily'  => ['08:00','14:00','20:00'],
        'weekly'             => ['08:00'], // first slot of the week
        default              => ['08:00'],
    };
}

/* ---- POST: assign or delete --------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $medId = (int) ($_POST['medication_id'] ?? 0);
        if ($medId > 0) {
            $pdo->prepare('DELETE FROM medications WHERE medication_id = ?')->execute([$medId]);
            audit_log('medication_delete', 'medication', $medId);
            flash('success', 'Medication removed.');
        }
        redirect(base_url('admin/medications.php'));
    }

    if ($action === 'assign') {
        $errors = [];
        $patientId = (int) ($_POST['patient_id'] ?? 0);
        $name      = trim($_POST['medication_name'] ?? '');
        $dosage    = trim($_POST['dosage'] ?? '');
        $freq      = $_POST['frequency'] ?? '';
        $start     = $_POST['start_date'] ?? '';
        $end       = $_POST['end_date'] ?? '';
        $notes     = trim($_POST['notes'] ?? '');

        if ($patientId <= 0)               $errors['patient_id'] = 'Pick a patient.';
        if ($name === '')                  $errors['medication_name'] = 'Required.';
        if ($dosage === '')                $errors['dosage'] = 'Required.';
        if (!in_array($freq, ['once_daily','twice_daily','three_times_daily','weekly'], true))
            $errors['frequency'] = 'Invalid frequency.';
        if (!DateTime::createFromFormat('Y-m-d', $start)) $errors['start_date'] = 'Invalid date.';
        if (!DateTime::createFromFormat('Y-m-d', $end))   $errors['end_date']   = 'Invalid date.';
        if (!isset($errors['start_date'], $errors['end_date']) && $start > $end)
            $errors['end_date'] = 'End date must be on/after start.';

        if (!$errors) {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    'INSERT INTO medications
                       (patient_id, medication_name, dosage, frequency,
                        start_date, end_date, notes, assigned_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $patientId, $name, $dosage, $freq,
                    $start, $end, $notes ?: null,
                    current_user_id(),
                ]);
                $medId = (int) $pdo->lastInsertId();

                // Seed today's (or start_date's) reminder slots for cron.php.
                $today = date('Y-m-d');
                $seedDay = $start > $today ? $start : $today;
                $stmtR = $pdo->prepare(
                    'INSERT INTO reminders
                        (patient_id, reference_id, reminder_type, reminder_time, sent_status)
                     VALUES (?, ?, "medication", ?, "pending")'
                );
                foreach (frequency_slots($freq) as $hhmm) {
                    $stmtR->execute([$patientId, $medId, "$seedDay $hhmm:00"]);
                }

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
                        'A new medication has been assigned to you: ' . $name . ' (' . $dosage . ').'
                    ]);
                }

                $pdo->commit();
                audit_log('medication_assign', 'medication', $medId, [
                    'patient_id' => $patientId,
                    'name'       => $name,
                    'dosage'     => $dosage,
                    'frequency'  => $freq,
                    'start_date' => $start,
                    'end_date'   => $end,
                ]);
                flash('success', 'Medication assigned.');
                redirect(base_url('admin/medications.php'));
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                flash('danger', DEBUG ? $e->getMessage() : 'Could not assign medication.');
            }
        } else {
            // Surface first error as a flash; full per-field errors require an
            // inline form re-render (kept simple for project scope).
            flash('danger', 'Please fix the form: ' . implode(' ', $errors));
        }
    }
}

/* ---- Patients dropdown -------------------------------------------- */
$patients = $pdo->query("
    SELECT p.patient_id, u.name
      FROM patients p JOIN users u ON u.user_id = p.user_id
     WHERE u.status = 'active'
     ORDER BY u.name
")->fetchAll();

$preselect = (int) ($_GET['patient_id'] ?? 0);

/* ---- All medications ---------------------------------------------- */
$rows = $pdo->query("
    SELECT m.*, u.name AS patient_name, p.patient_id
      FROM medications m
      JOIN patients p ON p.patient_id = m.patient_id
      JOIN users    u ON u.user_id    = p.user_id
     ORDER BY m.created_at DESC
")->fetchAll();

$page_title   = 'Medications';
$page_section = 'medications';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="page-head">
    <div>
        <h1>Medications</h1>
        <div class="subtitle">Assign and manage prescriptions for your patients.</div>
    </div>
</div>

<div class="grid-2">
    <!-- Existing medications -->
    <div class="table-wrap">
        <div class="table-toolbar">
            <input type="search" class="input search js-table-search"
                   data-target="#medsTable" placeholder="Search medications...">
            <span class="text-muted"><?= count($rows) ?> total</span>
        </div>
        <table class="table" id="medsTable">
            <thead><tr>
                <th data-sort="text">Patient</th>
                <th data-sort="text">Medication</th>
                <th data-sort="text">Dosage</th>
                <th data-sort="text">Frequency</th>
                <th data-sort="date">Period</th>
                <th>Actions</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="6" class="table-empty">No medications yet.</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td><a href="<?= e(base_url('admin/patient_detail.php?id=' . $r['patient_id'])) ?>"><?= e($r['patient_name']) ?></a></td>
                    <td><b><?= e($r['medication_name']) ?></b></td>
                    <td><?= e($r['dosage']) ?></td>
                    <td><?= e(str_replace('_',' ', $r['frequency'])) ?></td>
                    <td><?= e($r['start_date']) ?> → <?= e($r['end_date']) ?></td>
                    <td>
                        <form method="post"
                              data-confirm="Delete the medication '<?= e($r['medication_name']) ?>' (<?= e($r['dosage']) ?>) for <?= e($r['patient_name']) ?>? This cannot be undone."
                              data-confirm-title="Delete medication?"
                              data-confirm-action="Delete medication"
                              data-confirm-variant="danger">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action"        value="delete">
                            <input type="hidden" name="medication_id" value="<?= (int) $r['medication_id'] ?>">
                            <button class="btn btn--danger btn--sm" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Assign form -->
    <div class="card">
        <div class="card__header"><h3 class="card__title">Assign new medication</h3></div>
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
                    <label for="medication_name">Medication name *</label>
                    <input id="medication_name" name="medication_name" type="text" class="input" required
                           placeholder="e.g. Amoxicillin">
                </div>
                <div class="form-group">
                    <label for="dosage">Dosage *</label>
                    <input id="dosage" name="dosage" type="text" class="input" required
                           placeholder="e.g. 500 mg, 1 tablet">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="frequency">Frequency *</label>
                    <select id="frequency" name="frequency" class="input" required>
                        <option value="once_daily">Once daily</option>
                        <option value="twice_daily">Twice daily</option>
                        <option value="three_times_daily">Three times daily</option>
                        <option value="weekly">Weekly</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="start_date">Start date *</label>
                    <input id="start_date" name="start_date" type="date" class="input"
                           value="<?= e(date('Y-m-d')) ?>" required>
                </div>
                <div class="form-group">
                    <label for="end_date">End date *</label>
                    <input id="end_date" name="end_date" type="date" class="input"
                           value="<?= e(date('Y-m-d', strtotime('+7 days'))) ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" class="input" placeholder="Take with food..."></textarea>
            </div>

            <?= csrf_field() ?>
            <input type="hidden" name="action" value="assign">
            <button type="submit" class="btn btn--accent">Assign medication</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
