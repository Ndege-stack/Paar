<?php
/**
 * =====================================================================
 * PAAR — patient/medications.php
 * ---------------------------------------------------------------------
 * Full medication schedule for the logged-in patient. Lists active and
 * past prescriptions, with each medication's daily slots and today's
 * status per slot. Active medications can be confirmed inline.
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

$today = date('Y-m-d');

$stmt = $pdo->prepare("
    SELECT * FROM medications
     WHERE patient_id = ?
     ORDER BY (end_date >= CURDATE()) DESC, start_date DESC
");
$stmt->execute([$patientId]);
$meds = $stmt->fetchAll();

/**
 * For a medication, build today's slots with their current status.
 */
function build_today_view(PDO $pdo, array $med): array {
    $today = date('Y-m-d');
    $slots = slots_for_medication($med['frequency'], $med['start_date'], $med['end_date'], $today);
    $view  = [];
    foreach ($slots as $s) {
        $q = $pdo->prepare("
            SELECT status, confirmation_time FROM adherence
             WHERE medication_id = ?
               AND patient_id    = ?
               AND slot_idx      = ?
               AND DATE(confirmation_time) = ?
             ORDER BY confirmation_time DESC LIMIT 1
        ");
        $q->execute([$med['medication_id'], $med['patient_id'], $s['idx'], $today]);
        $row = $q->fetch();
        $view[] = array_merge($s, [
            'status' => $row['status'] ?? 'pending',
            'confirmed_at' => $row['confirmation_time'] ?? null,
        ]);
    }
    return $view;
}

$page_title   = 'My Medications';
$page_section = 'medications';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_patient.php';
?>

<div class="page-head">
    <div>
        <h1>My medications</h1>
        <div class="subtitle">All medications prescribed to you, past and present.</div>
    </div>
</div>

<?php if (!$meds): ?>
    <div class="card">
        <div class="text-muted">You have no medications on file yet.</div>
    </div>
<?php else: ?>
    <?php foreach ($meds as $m):
        $isActive = ($m['start_date'] <= $today && $m['end_date'] >= $today);
        $todayView = $isActive ? build_today_view($pdo, $m) : [];
    ?>
        <div class="card">
            <div class="card__header">
                <div>
                    <h3 class="card__title">
                        <?= e($m['medication_name']) ?>
                        <span class="badge <?= $isActive ? 'badge--success' : 'badge--muted' ?>">
                            <?= $isActive ? 'Active' : 'Ended' ?>
                        </span>
                    </h3>
                    <div class="text-muted mt-2">
                        <?= e($m['dosage']) ?> · <?= e(str_replace('_',' ', $m['frequency'])) ?>
                        · <?= e($m['start_date']) ?> → <?= e($m['end_date']) ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($m['notes'])): ?>
                <p class="text-muted"><b>Notes:</b> <?= e($m['notes']) ?></p>
            <?php endif; ?>

            <?php if ($isActive): ?>
                <h4 class="mb-3">Today's doses</h4>
                <?php if (!$todayView): ?>
                    <div class="text-muted">No doses scheduled today (e.g. weekly dose on a different day).</div>
                <?php else: ?>
                    <ul class="dose-list">
                    <?php foreach ($todayView as $s):
                        $cls = $s['status']==='taken' ? 'dose--taken'
                             : ($s['status']==='missed' ? 'dose--missed' : 'dose--pending');
                    ?>
                        <li class="dose <?= $cls ?>">
                            <div>
                                <div class="dose__name"><?= e($s['label']) ?></div>
                                <div class="dose__meta">
                                    Scheduled <?= e($s['time']) ?>
                                    <?php if ($s['confirmed_at']): ?>
                                        · confirmed <?= e(date('H:i', strtotime($s['confirmed_at']))) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <?php if ($s['status'] === 'taken'): ?>
                                    <span class="badge badge--success">Taken</span>
                                <?php elseif ($s['status'] === 'missed'): ?>
                                    <span class="badge badge--danger badge--missed-strong">⚠ Missed</span>
                                    <form method="post" action="<?= e(base_url('patient/confirm_medication.php')) ?>" class="form-inline gap-2">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="medication_id" value="<?= (int) $m['medication_id'] ?>">
                                        <input type="hidden" name="slot_idx"      value="<?= (int) $s['idx'] ?>">
                                        <button class="btn btn--outline btn--sm" type="submit">Confirm late</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" action="<?= e(base_url('patient/confirm_medication.php')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="medication_id" value="<?= (int) $m['medication_id'] ?>">
                                        <input type="hidden" name="slot_idx"      value="<?= (int) $s['idx'] ?>">
                                        <button class="btn btn--success btn--sm" type="submit">Confirm taken</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
