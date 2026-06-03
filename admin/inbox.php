<?php
/**
 * =====================================================================
 * PAAR — admin/inbox.php
 * ---------------------------------------------------------------------
 * Personal notification inbox for the logged-in administrator. Mirrors
 * patient/notifications.php but is namespaced under /admin/ so it can be
 * linked from the admin topbar bell and sidebar.
 *
 * Supported POST actions:
 *   - read       : mark a single notification as read
 *   - read_all   : mark every notification as read
 *   - delete     : delete a single notification
 * =====================================================================
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_role('admin');

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
    redirect(base_url('admin/inbox.php'));
}

$stmt = $pdo->prepare("
    SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 200
");
$stmt->execute([$userId]);
$rows = $stmt->fetchAll();

$unread = 0;
foreach ($rows as $r) { if (!$r['is_read']) $unread++; }

$page_title   = 'Inbox';
$page_section = 'inbox';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="page-head">
    <div>
        <h1>Inbox</h1>
        <div class="subtitle">
            <?= (int) $unread ?> unread message<?= $unread === 1 ? '' : 's' ?>.
            Click an unread message to mark it as read.
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
