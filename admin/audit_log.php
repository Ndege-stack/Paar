<?php
/**
 * =====================================================================
 * PAAR — admin/audit_log.php
 * ---------------------------------------------------------------------
 * Read-only viewer for the audit_log table. Lists the most recent 200
 * entries with filters for action and actor. Designed to be useful to a
 * compliance-minded admin without becoming a sprawling reporting tool.
 * =====================================================================
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/security.php';
require_role('admin');

$pdo = db();

/* ---- Filters ------------------------------------------------------- */
$fAction = trim((string) ($_GET['action'] ?? ''));
$fActor  = trim((string) ($_GET['actor']  ?? ''));

$where  = [];
$params = [];

if ($fAction !== '') {
    $where[] = 'a.action = ?';
    $params[] = $fAction;
}
if ($fActor !== '') {
    $where[] = '(u.name LIKE ? OR u.email LIKE ?)';
    $params[] = '%' . $fActor . '%';
    $params[] = '%' . $fActor . '%';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* ---- Pagination --------------------------------------------------- */
$perPage     = 50;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));

/* ---- Distinct actions for the filter dropdown ---------------------- */
/* Both queries are wrapped together so that an admin running against   */
/* an outdated DB (missing the audit_log table) gets a friendly         */
/* "re-import paar_db.sql" card instead of a 500.                        */
$tableMissing = false;
$actions      = [];
$rows         = [];
$totalRows    = 0;
$totalPages   = 1;
try {
    $actions = $pdo->query(
        'SELECT DISTINCT action FROM audit_log ORDER BY action ASC'
    )->fetchAll(PDO::FETCH_COLUMN);

    $countStmt = $pdo->prepare("
        SELECT COUNT(*)
          FROM audit_log a
          LEFT JOIN users u ON u.user_id = a.actor_user_id
          $whereSql
    ");
    $countStmt->execute($params);
    $totalRows  = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($totalRows / $perPage));
    if ($currentPage > $totalPages) $currentPage = $totalPages;
    $offset = ($currentPage - 1) * $perPage;

    $sql = "
        SELECT a.id, a.actor_user_id, a.actor_role, a.action, a.entity, a.entity_id,
               a.meta_json, a.ip, a.user_agent, a.created_at,
               u.name AS actor_name, u.email AS actor_email
          FROM audit_log a
          LEFT JOIN users u ON u.user_id = a.actor_user_id
          $whereSql
         ORDER BY a.created_at DESC
         LIMIT $perPage OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} catch (PDOException $e) {
    // SQLSTATE 42S02 = base table not found.
    if ($e->getCode() === '42S02' || str_contains($e->getMessage(), 'audit_log')) {
        $tableMissing = true;
    } else {
        throw $e;
    }
}

/* ---- Action -> friendly tone class -------------------------------- */
function audit_tone(string $action): string {
    if (str_contains($action, 'fail')   || str_contains($action, 'lock')
     || str_contains($action, 'reject') || str_contains($action, 'delete'))
        return 'danger';
    if (str_contains($action, 'success') || str_contains($action, 'approve')
     || str_contains($action, 'create')  || str_contains($action, 'completed'))
        return 'success';
    if (str_contains($action, 'block')   || str_contains($action, 'request'))
        return 'warning';
    return 'info';
}

$page_title   = 'Audit Log';
$page_section = 'audit_log';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="page-head">
    <div>
        <h1>Audit log</h1>
        <div class="subtitle">
            Append-only trail of meaningful actions across the system.
            <?php if (!$tableMissing): ?>
                <?= number_format($totalRows) ?> total
                <?php if ($totalPages > 1): ?> &middot; page <?= $currentPage ?> of <?= $totalPages ?><?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($tableMissing): ?>
    <div class="alert alert--warning">
        <strong>Database out of date.</strong>
        The <code>audit_log</code> table doesn't exist yet. Re-import
        <code>paar_db.sql</code> against your database (drop the
        <code>paar_db</code> database in phpMyAdmin first, then run
        <code>paar_db.sql</code>) and reload this page.
    </div>
<?php else: ?>

<form class="filters" method="get">
    <div class="form-group">
        <label for="action">Action</label>
        <select id="action" name="action" class="input">
            <option value="">All actions</option>
            <?php foreach ($actions as $act): ?>
                <option value="<?= e($act) ?>" <?= $fAction === $act ? 'selected' : '' ?>>
                    <?= e($act) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="actor">Actor (name or email)</label>
        <input id="actor" name="actor" type="search" class="input"
               value="<?= e($fActor) ?>" placeholder="e.g. admin@paar.local">
    </div>
    <div class="form-group filters__buttons">
        <button class="btn btn--primary" type="submit">Filter</button>
        <a class="btn btn--ghost" href="<?= e(base_url('admin/audit_log.php')) ?>">Reset</a>
    </div>
</form>

<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>When</th>
                <th>Actor</th>
                <th>Action</th>
                <th>Target</th>
                <th>Details</th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="6" class="table-empty">No audit entries match the current filters.</td></tr>
        <?php else: foreach ($rows as $r):
            $tone = audit_tone($r['action']);
            $meta = $r['meta_json'] ? json_decode($r['meta_json'], true) : [];
        ?>
            <tr>
                <td><?= e(date('M j, Y · H:i', strtotime($r['created_at']))) ?></td>
                <td>
                    <?php if ($r['actor_name']): ?>
                        <b><?= e($r['actor_name']) ?></b>
                        <div class="text-muted text-sm"><?= e($r['actor_email']) ?></div>
                    <?php else: ?>
                        <span class="text-muted">anonymous</span>
                    <?php endif; ?>
                </td>
                <td><span class="badge badge--<?= $tone ?>"><?= e($r['action']) ?></span></td>
                <td>
                    <?php if ($r['entity']): ?>
                        <code><?= e($r['entity']) ?><?= $r['entity_id'] ? '#' . (int) $r['entity_id'] : '' ?></code>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (is_array($meta) && $meta): ?>
                        <details class="audit-meta">
                            <summary>view</summary>
                            <pre><?= e(json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre>
                        </details>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td><code class="text-sm"><?= e($r['ip'] ?: '—') ?></code></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php if (!$tableMissing && $totalPages > 1):
    $qp = array_filter(['action' => $fAction ?: null, 'actor' => $fActor ?: null]);
    $base = $qp ? '?' . http_build_query($qp) . '&' : '?';
?>
<div class="pagination">
    <?php if ($currentPage > 1): ?>
        <a class="btn btn--outline btn--sm" href="<?= e(base_url('admin/audit_log.php') . $base . 'page=' . ($currentPage - 1)) ?>">← Prev</a>
    <?php endif; ?>
    <?php for ($p = max(1, $currentPage - 2); $p <= min($totalPages, $currentPage + 2); $p++): ?>
        <a class="btn btn--sm <?= $p === $currentPage ? 'btn--primary' : 'btn--outline' ?>"
           href="<?= e(base_url('admin/audit_log.php') . $base . 'page=' . $p) ?>"><?= $p ?></a>
    <?php endfor; ?>
    <?php if ($currentPage < $totalPages): ?>
        <a class="btn btn--outline btn--sm" href="<?= e(base_url('admin/audit_log.php') . $base . 'page=' . ($currentPage + 1)) ?>">Next →</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php endif; /* !$tableMissing */ ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
