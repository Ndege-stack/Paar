<?php
/**
 * =====================================================================
 * PAAR — patient/confirm_medication.php
 * ---------------------------------------------------------------------
 * POST endpoint. Marks a dose as taken for the current patient.
 * Inputs (POST):
 *   csrf_token, medication_id, slot_idx
 *
 * Security:
 *   - Requires patient role.
 *   - Validates the medication belongs to the current patient.
 *   - Validates slot_idx against the medication's frequency.
 *   - Prevents duplicate confirmation within the same slot window today.
 * =====================================================================
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_role('patient');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(base_url('patient/dashboard.php'));
}
verify_csrf();

$pdo       = db();
$patientId = current_patient_id();
$medId     = (int) ($_POST['medication_id'] ?? 0);
$slotIdx   = (int) ($_POST['slot_idx'] ?? -1);

if (!$patientId || $medId <= 0 || $slotIdx < 0) {
    flash('danger', 'Invalid request.');
    redirect(base_url('patient/dashboard.php'));
}

/* ---- Load medication (and check ownership) ----------------------- */
$stmt = $pdo->prepare("
    SELECT * FROM medications
     WHERE medication_id = ? AND patient_id = ? LIMIT 1
");
$stmt->execute([$medId, $patientId]);
$med = $stmt->fetch();
if (!$med) {
    flash('danger', 'Medication not found.');
    redirect(base_url('patient/dashboard.php'));
}

$today = date('Y-m-d');

/* ---- Validate slot_idx against today's slots --------------------- */
$slots = slots_for_medication($med['frequency'], $med['start_date'], $med['end_date'], $today);
$slot  = null;
foreach ($slots as $s) {
    if ($s['idx'] === $slotIdx) { $slot = $s; break; }
}
if (!$slot) {
    flash('danger', 'That dose is not scheduled for today.');
    redirect(base_url('patient/dashboard.php'));
}

/* ---- Check if this slot has already been confirmed today --------- */
/* Identify the row by (medication_id, patient_id, slot_idx, date),  */
/* NOT by HOUR(confirmation_time) — patients may confirm late.       */
$check = $pdo->prepare("
    SELECT adherence_id, status FROM adherence
     WHERE medication_id = ?
       AND patient_id    = ?
       AND slot_idx      = ?
       AND DATE(confirmation_time) = ?
     LIMIT 1
");
$check->execute([$medId, $patientId, $slotIdx, $today]);
$existing = $check->fetch();

if ($existing) {
    if ($existing['status'] === 'taken') {
        flash('info', 'This dose was already confirmed.');
    } elseif ($existing['status'] === 'missed') {
        // Allow the patient to update a missed slot to taken (late confirmation).
        $pdo->prepare(
            "UPDATE adherence
                SET status = 'taken', confirmation_time = NOW(), notes = ?
              WHERE adherence_id = ?"
        )->execute(['Confirmed late (' . $slot['label'] . ')', $existing['adherence_id']]);
        flash('success', 'Dose confirmed (late).');
    } else {
        // status='pending' rows can be promoted to 'taken'
        $pdo->prepare(
            "UPDATE adherence
                SET status = 'taken', confirmation_time = NOW(), notes = ?
              WHERE adherence_id = ?"
        )->execute([$slot['label'], $existing['adherence_id']]);
        flash('success', 'Dose confirmed. Great job staying on track!');
    }
} else {
    /* ---- Insert a fresh 'taken' record --------------------------- */
    $pdo->prepare("
        INSERT INTO adherence (medication_id, patient_id, slot_idx, confirmation_time, status, notes)
        VALUES (?, ?, ?, NOW(), 'taken', ?)
    ")->execute([$medId, $patientId, $slotIdx, $slot['label']]);
    flash('success', 'Dose confirmed. Great job staying on track!');
}

redirect(base_url('patient/dashboard.php'));
