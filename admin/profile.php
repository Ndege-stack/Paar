<?php
/**
 * =====================================================================
 * PAAR — admin/profile.php
 * ---------------------------------------------------------------------
 * Lets a signed-in administrator update their display name and change
 * their password. Email is read-only.
 * =====================================================================
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/security.php';
require_role('admin');

$pdo    = db();
$userId = current_user_id();
$errors = [];

/* ---- Load current values ----------------------------------------- */
$me = $pdo->prepare('SELECT name, email FROM users WHERE user_id = ? LIMIT 1');
$me->execute([$userId]);
$me = $me->fetch();
if (!$me) {
    flash('danger', 'Profile not found.');
    redirect(base_url('logout.php'));
}

/* ================================================================== */
/* POST handlers                                                       */
/* ================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    /* ---- Update display name ------------------------------------- */
    if ($action === 'update_name') {
        $name = trim($_POST['name'] ?? '');
        if (mb_strlen($name) < 2) {
            $errors['name'] = 'Name must be at least 2 characters.';
        }
        if (!$errors) {
            $pdo->prepare('UPDATE users SET name = ? WHERE user_id = ?')
                ->execute([$name, $userId]);
            $_SESSION['name'] = $name;
            $me['name'] = $name;
            audit_log('admin_name_update', 'user', $userId, ['name' => $name]);
            flash('success', 'Display name updated.');
            redirect(base_url('admin/profile.php'));
        }
    }

    /* ---- Change password ----------------------------------------- */
    if ($action === 'change_password') {
        $current  = $_POST['current_password']  ?? '';
        $new      = $_POST['new_password']       ?? '';
        $confirm  = $_POST['confirm_password']   ?? '';

        // Verify current password
        $row = $pdo->prepare('SELECT password FROM users WHERE user_id = ? LIMIT 1');
        $row->execute([$userId]);
        $hash = $row->fetchColumn();

        if (!password_verify($current, $hash)) {
            $errors['current_password'] = 'Current password is incorrect.';
        }
        $pwError = validate_password($new);
        if ($pwError !== null) $errors['new_password'] = $pwError;
        if ($new !== $confirm) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }

        if (!$errors) {
            $newHash = password_hash($new, PASSWORD_BCRYPT);
            $pdo->prepare('UPDATE users SET password = ? WHERE user_id = ?')
                ->execute([$newHash, $userId]);
            audit_log('admin_password_change', 'user', $userId, []);
            flash('success', 'Password changed successfully.');
            redirect(base_url('admin/profile.php'));
        }
    }
}

$page_title   = 'My Profile';
$page_section = 'profile';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="page-head">
    <div>
        <h1>My Profile</h1>
        <div class="subtitle">Update your display name or change your password.</div>
    </div>
</div>

<!-- ============================================================== -->
<!-- Display name                                                   -->
<!-- ============================================================== -->
<div class="card card--narrow" style="margin-bottom:var(--sp-6)">
    <div class="card__header">
        <h2 class="card__title">Display name</h2>
    </div>

    <form method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_name">

        <div class="form-row">
            <div class="form-group">
                <label for="name">Full name *</label>
                <input id="name" name="name" type="text"
                       class="input <?= isset($errors['name']) ? 'input--error' : '' ?>"
                       value="<?= e($me['name']) ?>" required minlength="2"
                       aria-invalid="<?= isset($errors['name']) ? 'true' : 'false' ?>">
                <?php if (!empty($errors['name'])): ?>
                    <div class="field-error"><?= e($errors['name']) ?></div>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="email_ro">Email address</label>
                <input id="email_ro" type="email" class="input" value="<?= e($me['email']) ?>" readonly
                       aria-describedby="email-hint">
                <div class="field-help" id="email-hint">Email cannot be changed here. Contact another admin if needed.</div>
            </div>
        </div>

        <div class="btn-row">
            <button type="submit" class="btn btn--accent">Save name</button>
        </div>
    </form>
</div>

<!-- ============================================================== -->
<!-- Change password                                                -->
<!-- ============================================================== -->
<div class="card card--narrow">
    <div class="card__header">
        <h2 class="card__title">Change password</h2>
    </div>

    <form method="post" novalidate autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="change_password">

        <div class="form-group">
            <label for="current_password">Current password *</label>
            <div class="input-reveal">
                <input id="current_password" name="current_password" type="password"
                       class="input <?= isset($errors['current_password']) ? 'input--error' : '' ?>"
                       required autocomplete="current-password"
                       aria-invalid="<?= isset($errors['current_password']) ? 'true' : 'false' ?>">
                <button type="button" class="input-reveal__btn" data-reveal-target="current_password"
                        aria-label="Show/hide password">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            </div>
            <?php if (!empty($errors['current_password'])): ?>
                <div class="field-error"><?= e($errors['current_password']) ?></div>
            <?php endif; ?>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="new_password">New password *</label>
                <div class="input-reveal">
                    <input id="new_password" name="new_password" type="password"
                           class="input <?= isset($errors['new_password']) ? 'input--error' : '' ?>"
                           minlength="<?= (int) MIN_PASSWORD_LENGTH ?>" required
                           autocomplete="new-password"
                           aria-invalid="<?= isset($errors['new_password']) ? 'true' : 'false' ?>">
                    <button type="button" class="input-reveal__btn" data-reveal-target="new_password"
                            aria-label="Show/hide password">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
                <?php if (!empty($errors['new_password'])): ?>
                    <div class="field-error"><?= e($errors['new_password']) ?></div>
                <?php else: ?>
                    <div class="field-help">Minimum <?= (int) MIN_PASSWORD_LENGTH ?> characters.</div>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm new password *</label>
                <div class="input-reveal">
                    <input id="confirm_password" name="confirm_password" type="password"
                           class="input <?= isset($errors['confirm_password']) ? 'input--error' : '' ?>"
                           minlength="<?= (int) MIN_PASSWORD_LENGTH ?>" required
                           autocomplete="new-password"
                           aria-invalid="<?= isset($errors['confirm_password']) ? 'true' : 'false' ?>">
                    <button type="button" class="input-reveal__btn" data-reveal-target="confirm_password"
                            aria-label="Show/hide password">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
                <?php if (!empty($errors['confirm_password'])): ?>
                    <div class="field-error"><?= e($errors['confirm_password']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="btn-row">
            <button type="submit" class="btn btn--accent">Change password</button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
