<?php
/**
 * =====================================================================
 * PAAR — admin/pending_approvals.php
 * ---------------------------------------------------------------------
 * Approve or reject self-registered patients (status='pending').
 *
 * Approve -> users.status = 'active'  + welcome notification
 * Reject  -> deletes the user (cascade removes patients row + everything)
 * =====================================================================
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/security.php';
require_role('admin');

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $userId = (int) ($_POST['user_id'] ?? 0);

    if ($userId > 0 && $action === 'approve') {
        $pdo->prepare(
            "UPDATE users SET status='active' WHERE user_id = ? AND role='patient' AND status='pending'"
        )->execute([$userId]);
        $pdo->prepare(
            'INSERT INTO notifications (user_id, message) VALUES (?, ?)'
        )->execute([$userId, 'Your account has been approved. Welcome to PAAR!']);
        audit_log('patient_approve', 'user', $userId);
        flash('success', 'Patient approved.');
    } elseif ($userId > 0 && $action === 'reject') {
        $pdo->prepare(
            "DELETE FROM users WHERE user_id = ? AND role='patient' AND status='pending'"
        )->execute([$userId]);
        audit_log('patient_reject', 'user', $userId);
        flash('success', 'Pending registration rejected.');
    } elseif ($action === 'approve_all') {
        $pending = $pdo->query(
            "SELECT user_id FROM users WHERE role='patient' AND status='pending'"
        )->fetchAll(PDO::FETCH_COLUMN);
        $notif = $pdo->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
        foreach ($pending as $uid) {
            $uid = (int) $uid;
            $pdo->prepare("UPDATE users SET status='active' WHERE user_id=? AND role='patient' AND status='pending'")
                ->execute([$uid]);
            $notif->execute([$uid, 'Your account has been approved. Welcome to PAAR!']);
            audit_log('patient_approve', 'user', $uid);
        }
        flash('success', count($pending) . ' patient' . (count($pending)===1?'':'s') . ' approved.');
    }
    redirect(base_url('admin/pending_approvals.php'));
}

$rows = $pdo->query("
    SELECT u.user_id, u.name, u.email, u.created_at,
           p.phone, p.date_of_birth, p.gender
      FROM users u
      LEFT JOIN patients p ON p.user_id = u.user_id
     WHERE u.role='patient' AND u.status='pending'
     ORDER BY u.created_at ASC
")->fetchAll();

$page_title   = 'Pending Approvals';
$page_section = 'pending';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="page-head">
    <div>
        <h1>Pending approvals</h1>
        <div class="subtitle">Review and approve patients who self-registered.
            <?php if ($rows): ?> <?= count($rows) ?> pending.<?php endif; ?>
        </div>
    </div>
    <?php if (count($rows) > 1): ?>
        <form method="post"
              data-confirm="Approve all <?= count($rows) ?> pending registrations? They will all be able to sign in immediately."
              data-confirm-title="Approve all?" data-confirm-action="Approve all">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="approve_all">
            <button class="btn btn--success" type="submit">Approve all (<?= count($rows) ?>)</button>
        </form>
    <?php endif; ?>
</div>

<div class="table-wrap">
    <table class="table">
        <thead><tr>
            <th>Name</th><th>Email</th><th>Phone</th><th>DOB</th><th>Gender</th><th>Submitted</th><th>Actions</th>
        </tr></thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="7" class="table-empty">No pending registrations. Great work!</td></tr>
        <?php else: foreach ($rows as $r): ?>
            <tr>
                <td><b><?= e($r['name']) ?></b></td>
                <td><?= e($r['email']) ?></td>
                <td><?= e($r['phone'] ?: '—') ?></td>
                <td><?= e($r['date_of_birth'] ?: '—') ?></td>
                <td><?= e(ucfirst($r['gender'] ?: '—')) ?></td>
                <td><?= e(date('M j, Y · H:i', strtotime($r['created_at']))) ?></td>
                <td>
                    <div class="actions">
                        <form method="post"
                              data-confirm="Approve <?= e($r['name']) ?> (<?= e($r['email']) ?>)? They will be able to sign in immediately."
                              data-confirm-title="Approve patient?"
                              data-confirm-action="Approve">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action"  value="approve">
                            <input type="hidden" name="user_id" value="<?= (int) $r['user_id'] ?>">
                            <button class="btn btn--success btn--sm" type="submit">Approve</button>
                        </form>
                        <form method="post"
                              data-confirm="Reject and permanently delete the registration for <?= e($r['name']) ?> (<?= e($r['email']) ?>)? This cannot be undone."
                              data-confirm-title="Reject registration?"
                              data-confirm-action="Reject and delete"
                              data-confirm-variant="danger">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action"  value="reject">
                            <input type="hidden" name="user_id" value="<?= (int) $r['user_id'] ?>">
                            <button class="btn btn--danger btn--sm" type="submit">Reject</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
