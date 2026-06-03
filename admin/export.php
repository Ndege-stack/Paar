<?php
/**
 * =====================================================================
 * PAAR — admin/export.php
 * ---------------------------------------------------------------------
 * CSV data export for administrators. Supports:
 *   ?type=patients    – all patient records
 *   ?type=adherence   – all adherence records (last 365 days)
 *   ?type=appointments – all appointments
 * =====================================================================
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/security.php';
require_role('admin');

$pdo  = db();
$type = $_GET['type'] ?? '';
$allowed = ['patients', 'adherence', 'appointments'];

if (!in_array($type, $allowed, true)) {
    http_response_code(400);
    die('Invalid export type. Use: patients, adherence, or appointments.');
}

audit_log('data_export', null, null, ['type' => $type]);

$filename = 'paar_' . $type . '_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store');
header('Pragma: no-cache');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM for Excel

if ($type === 'patients') {
    fputcsv($out, ['ID', 'Name', 'Email', 'Phone', 'Gender', 'Date of Birth',
                   'Address', 'Emergency Contact', 'Emergency Phone', 'Status', 'Registered']);
    $rows = $pdo->query("
        SELECT u.user_id, u.name, u.email, p.phone, p.gender, p.date_of_birth,
               p.address, p.emergency_contact, p.emergency_phone, u.status, u.created_at
          FROM users u
          JOIN patients p ON p.user_id = u.user_id
         WHERE u.role = 'patient'
         ORDER BY u.created_at DESC
    ")->fetchAll();
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['user_id'], $r['name'], $r['email'], $r['phone'] ?? '',
            $r['gender'] ?? '', $r['date_of_birth'] ?? '', $r['address'] ?? '',
            $r['emergency_contact'] ?? '', $r['emergency_phone'] ?? '',
            $r['status'], $r['created_at'],
        ]);
    }

} elseif ($type === 'adherence') {
    fputcsv($out, ['Adherence ID', 'Patient', 'Medication', 'Dosage', 'Frequency',
                   'Slot', 'Status', 'Confirmation Time', 'Notes']);
    $rows = $pdo->query("
        SELECT a.adherence_id, u.name AS patient, m.medication_name, m.dosage, m.frequency,
               a.slot_idx, a.status, a.confirmation_time, a.notes
          FROM adherence a
          JOIN medications m ON m.medication_id = a.medication_id
          JOIN patients   p ON p.patient_id     = a.patient_id
          JOIN users      u ON u.user_id         = p.user_id
         WHERE a.confirmation_time >= (NOW() - INTERVAL 365 DAY)
         ORDER BY a.confirmation_time DESC
    ")->fetchAll();
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['adherence_id'], $r['patient'], $r['medication_name'], $r['dosage'],
            $r['frequency'], $r['slot_idx'], $r['status'], $r['confirmation_time'],
            $r['notes'] ?? '',
        ]);
    }

} elseif ($type === 'appointments') {
    fputcsv($out, ['Appointment ID', 'Patient', 'Date', 'Doctor', 'Department', 'Reason', 'Status', 'Created']);
    $rows = $pdo->query("
        SELECT a.appointment_id, u.name AS patient, a.appointment_date,
               a.doctor_name, a.department, a.reason, a.status, a.created_at
          FROM appointments a
          JOIN patients p ON p.patient_id = a.patient_id
          JOIN users    u ON u.user_id    = p.user_id
         ORDER BY a.appointment_date DESC
    ")->fetchAll();
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['appointment_id'], $r['patient'], $r['appointment_date'],
            $r['doctor_name'], $r['department'], $r['reason'] ?? '',
            $r['status'], $r['created_at'],
        ]);
    }
}

fclose($out);
exit;
