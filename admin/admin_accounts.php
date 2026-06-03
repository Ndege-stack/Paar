<?php
/**
 * =====================================================================
 * PAAR — admin/admin_accounts.php
 * ---------------------------------------------------------------------
 * List all admin users and add new ones. Only admins can access this
 * page. A new admin is inserted into `users` only — no `patients` row
 * is created, so they never appear in the patient list.
 * =====================================================================
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_role('admin');

$pdo = db();

$errors = [];
$old = ['name' => '', 'email' => ''];

/* ================================================================== */
/* POST — create new admin                                            */
/* ================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $old['name']  = trim((string) ($_POST['name']  ?? ''));
    $old['email'] = trim((string) ($_POST['email'] ?? ''));
    $password     = $_POST['password'] ?? '';

    /* ---- Validation ----------------------------------------------- */
    if ($old['name'] === '')
        $errors['name']  = 'Full name is required.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL))
        $errors['email'] = 'A valid email address is required.';
    $pwError = validate_password($password);
    if ($pwError !== null) $errors['password'] = $pwError;

    if (!isset($errors['email'])) {
        $chk = $pdo->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
        $chk->execute([$old['email']]);
        if ($chk->fetchColumn())
            $errors['email'] = 'That email is already in use.';
    }

    /* ---- Persist -------------------------------------------------- */
    if (!$errors) {
        try {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare(
                "INSERT INTO users (name, email, password, role, status)
                 VALUES (?, ?, ?, 'admin', 'active')"
            );
            $stmt->execute([$old['name'], $old['email'], $hash]);
            $newId = (int) $pdo->lastInsertId();

            audit_log('admin_create', 'user', $newId, [
                'name'  => $old['name'],
                'email' => $old['email'],
            ]);
            flash('success', 'Admin account for "' . $old['name'] . '" created.');
            redirect(base_url('admin/admin_accounts.php'));
        } catch (Throwable $e) {
            $errors['form'] = DEBUG ? $e->getMessage() : 'Could not create admin account. Please try again.';
        }
    }
}

/* ================================================================== */
/* GET — load admin list                                              */
/* ================================================================== */
$admins = $pdo->query(
    "SELECT user_id, name, email, status, created_at
       FROM users
      WHERE role = 'admin'
      ORDER BY created_at ASC"
)->fetchAll();

$page_title   = 'Admin Accounts';
$page_section = 'admin_accounts';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="page-head">
    <div>
        <h1>Admin Accounts</h1>
        <div class="subtitle">Manage who has administrator access to PAAR.</div>
    </div>
</div>

<!-- ============================================================== -->
<!-- Admin list                                                     -->
<!-- ============================================================== -->
<div class="card" style="margin-bottom:var(--sp-6)">
    <div class="card__header">
        <h2 class="card__title">Current Admins</h2>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($admins as $a): ?>
                <tr>
                    <td>
                        <?= e($a['name']) ?>
                        <?php if ((int) $a['user_id'] === current_user_id()): ?>
                            <span class="badge badge--info" style="font-size:11px;margin-left:6px">You</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($a['email']) ?></td>
                    <td>
                        <?php if ($a['status'] === 'active'): ?>
                            <span class="badge badge--success">Active</span>
                        <?php else: ?>
                            <span class="badge badge--danger">Suspended</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e(date('j M Y', strtotime($a['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============================================================== -->
<!-- Add admin form                                                 -->
<!-- ============================================================== -->
<div class="card card--narrow">
    <div class="card__header">
        <h2 class="card__title">Add New Admin</h2>
    </div>

    <?php if (!empty($errors['form'])): ?>
        <div class="alert alert--danger"><?= e($errors['form']) ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
        <?= csrf_field() ?>

        <div class="form-row">
            <div class="form-group">
                <label for="name">Full name *</label>
                <input id="name" name="name" type="text"
                       class="input <?= isset($errors['name']) ? 'input--error' : '' ?>"
                       value="<?= e($old['name']) ?>" required
                       aria-describedby="<?= isset($errors['name']) ? 'err-name' : '' ?>"
                       aria-invalid="<?= isset($errors['name']) ? 'true' : 'false' ?>">
                <?php if (!empty($errors['name'])): ?>
                    <div class="field-error" id="err-name"><?= e($errors['name']) ?></div>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="email">Email address *</label>
                <input id="email" name="email" type="email"
                       class="input <?= isset($errors['email']) ? 'input--error' : '' ?>"
                       value="<?= e($old['email']) ?>" required
                       aria-describedby="<?= isset($errors['email']) ? 'err-email' : '' ?>"
                       aria-invalid="<?= isset($errors['email']) ? 'true' : 'false' ?>">
                <?php if (!empty($errors['email'])): ?>
                    <div class="field-error" id="err-email"><?= e($errors['email']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-group">
            <label for="password">Password *</label>
            <input id="password" name="password" type="password"
                   class="input <?= isset($errors['password']) ? 'input--error' : '' ?>"
                   minlength="<?= (int) MIN_PASSWORD_LENGTH ?>" required
                   placeholder="They should change this on first sign-in"
                   aria-describedby="<?= isset($errors['password']) ? 'err-pw' : 'help-pw' ?>"
                   aria-invalid="<?= isset($errors['password']) ? 'true' : 'false' ?>">
            <?php if (!empty($errors['password'])): ?>
                <div class="field-error" id="err-pw"><?= e($errors['password']) ?></div>
            <?php else: ?>
                <div class="field-help" id="help-pw">
                    Minimum <?= (int) MIN_PASSWORD_LENGTH ?> characters. Share securely — the new admin can change it after sign-in.
                </div>
            <?php endif; ?>
        </div>

        <div class="btn-row">
            <button type="submit" class="btn btn--accent">Create admin account</button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
