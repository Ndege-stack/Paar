<?php
/**
 * =====================================================================
 * PAAR — patient/notifications.php
 * ---------------------------------------------------------------------
 * In-app notification inbox for the patient. Supports:
 *   - Mark a single notification as read (POST action=read)
 *   - Mark all as read (POST action=read_all)
 *   - Delete a single notification (POST action=delete)
 * =====================================================================
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_role('patient');

$pdo    = db();
$userId = current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $id     = (int) ($_POST['notification_id'] ?? 0);

    if ($action === 'read_all') {
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0')
            ->execute([$userId]);
    } elseif ($id > 0 && $action === 'read') {
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?')
            ->execute([$id, $userId]);
    } elseif ($id > 0 && $action === 'delete') {
        $pdo->prepare('DELETE FROM notifications WHERE notification_id = ? AND user_id = ?')
            ->execute([$id, $userId]);
    } elseif ($action === 'delete_all') {
        $pdo->prepare('DELETE FROM notifications WHERE user_id = ?')->execute([$userId]);
    }
    redirect(base_url('patient/notifications.php'));
}

$stmt = $pdo->prepare("
    SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 200
");
$stmt->execute([$userId]);
$rows = $stmt->fetchAll();

$unread = 0;
foreach ($rows as $r) { if (!$r['is_read']) $unread++; }

$page_title   = 'Notifications';
$page_section = 'notifications';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_patient.php';
?>

<div class="page-head">
    <div>
        <h1>Notifications</h1>
        <div class="subtitle">
            You have <?= (int) $unread ?> unread message<?= $unread===1?'':'s' ?>.
        </div>
    </div>
    <div class="btn-row">
        <?php if ($unread > 0): ?>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="read_all">
                <button class="btn btn--outline" type="submit">Mark all read</button>
            </form>
        <?php endif; ?>
        <?php if ($rows): ?>
            <form method="post"
                  data-confirm="Delete all <?= count($rows) ?> notification<?= count($rows)===1?'':'s' ?>? This cannot be undone."
                  data-confirm-ok="Delete all" data-confirm-variant="danger">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_all">
                <button class="btn btn--outline-danger" type="submit">Delete all</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if (!$rows): ?>
    <div class="card">
        <div class="text-muted">No notifications yet.</div>
    </div>
<?php else: ?>
    <ul class="notif-list">
    <?php foreach ($rows as $n): ?>
        <li class="<?= $n['is_read'] ? '' : 'unread' ?>">
            <?php if (!$n['is_read']): ?>
                <!-- Whole-row button: clicking the message marks it as read -->
                <form method="post" class="notif-row-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action"          value="read">
                    <input type="hidden" name="notification_id" value="<?= (int) $n['notification_id'] ?>">
                    <button type="submit" class="notif-row-btn" aria-label="Mark as read">
                        <span class="dot-indicator"></span>
                        <span class="notif-row-msg"><?= e($n['message']) ?></span>
                        <div class="when">
                            <?= e(date('M j, Y · H:i', strtotime($n['created_at']))) ?>
                        </div>
                    </button>
                </form>
            <?php else: ?>
                <div class="notif-row-form">
                    <span class="notif-row-msg"><?= e($n['message']) ?></span>
                    <div class="when text-muted">
                        <?= e(date('M j, Y · H:i', strtotime($n['created_at']))) ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="actions">
                <form method="post"
                      data-confirm="Permanently delete this notification?"
                      data-confirm-title="Delete notification?"
                      data-confirm-action="Delete"
                      data-confirm-variant="danger">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action"          value="delete">
                    <input type="hidden" name="notification_id" value="<?= (int) $n['notification_id'] ?>">
                    <button class="btn btn--danger btn--sm" type="submit">Delete</button>
                </form>
            </div>
        </li>
    <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
