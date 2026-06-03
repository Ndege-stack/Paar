<?php
/**
 * =====================================================================
 * PAAR — reset_password.php
 * ---------------------------------------------------------------------
 * Step 2 of the password-reset flow. Validates the token from the email
 * link and lets the user choose a new password. On success we mark the
 * token used, invalidate all other unused tokens for that user, audit
 * the event, and redirect to the login page with a success flash.
 * =====================================================================
 */

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/security.php';

if (is_logged_in()) {
    redirect(current_role() === 'admin' ? 'admin/dashboard.php' : 'patient/dashboard.php');
}

$token   = trim($_GET['token'] ?? $_POST['token'] ?? '');
$errors  = [];
$validReset = $token !== '' ? find_valid_password_reset($token) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $password = $_POST['password']         ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    if (!$validReset) $errors['form'] = 'This reset link is invalid or has expired.';
    $pwError = validate_password($password);
    if ($pwError !== null) $errors['password'] = $pwError;
    if ($password !== $confirm) $errors['confirm'] = 'Passwords do not match.';

    if (!$errors) {
        if (consume_password_reset($token, $password)) {
            flash('success', 'Password updated. You can now sign in with your new password.');
            redirect('login.php');
        }
        $errors['form'] = 'Could not reset your password. Please request a new link.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Choose a new password · <?= e(SITE_NAME) ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Serif+Display:ital@0;1&display=swap">
    <link rel="stylesheet" href="assets/css/style.css">
    <script defer src="assets/js/main.js"></script>
</head>
<body class="land-body">

<div class="land-login">
    <div class="land-login__bg" aria-hidden="true">
        <div class="land-login__blob"></div>
        <div class="land-login__grid"></div>
    </div>

    <header class="land-login__top">
        <a class="land-login__brand" href="index.php">
            <span class="land-login__brand-mark"></span>
            <span class="land-login__brand-name"><?= e(SITE_NAME) ?></span>
        </a>
        <a class="land-login__back" href="login.php">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>
            Back to sign in
        </a>
    </header>

    <main class="land-login__main">
        <div class="land-login__card">
            <div class="land-login__card-head">
                <h1 class="land-login__title">Choose a new password</h1>
                <p class="land-login__subtitle">
                    For your security, your new password must be at least
                    <?= (int) MIN_PASSWORD_LENGTH ?> characters long.
                </p>
            </div>

            <?php if (!$validReset): ?>
                <div class="alert alert--danger">
                    This reset link is invalid or has expired.
                </div>
                <p class="land-login__alt">
                    <a href="forgot_password.php">Request a new reset link</a>
                </p>
            <?php else: ?>
                <?php if (!empty($errors['form'])): ?>
                    <div class="alert alert--danger"><?= e($errors['form']) ?></div>
                <?php endif; ?>

                <form class="land-login__form" method="post" novalidate>
                    <input type="hidden" name="token" value="<?= e($token) ?>">

                    <div class="form-group">
                        <label for="password">New password</label>
                        <div class="input-reveal">
                            <input
                                id="password"
                                name="password"
                                type="password"
                                class="input <?= isset($errors['password']) ? 'input--error' : '' ?>"
                                placeholder="At least <?= (int) MIN_PASSWORD_LENGTH ?> characters"
                                minlength="<?= (int) MIN_PASSWORD_LENGTH ?>"
                                required
                                autocomplete="new-password"
                                autofocus
                                data-pw-strength="pw-strength-reset">
                            <button type="button" class="input-reveal__btn" data-reveal-target="password"
                                    aria-label="Show password">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        <div class="pw-strength" id="pw-strength-reset">
                            <div class="pw-strength__bar">
                                <div class="pw-strength__seg"></div>
                                <div class="pw-strength__seg"></div>
                                <div class="pw-strength__seg"></div>
                                <div class="pw-strength__seg"></div>
                            </div>
                            <span class="pw-strength__label"></span>
                        </div>
                        <?php if (!empty($errors['password'])): ?>
                            <div class="field-error"><?= e($errors['password']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="password_confirm">Confirm new password</label>
                        <div class="input-reveal">
                            <input
                                id="password_confirm"
                                name="password_confirm"
                                type="password"
                                class="input <?= isset($errors['confirm']) ? 'input--error' : '' ?>"
                                placeholder="Type it again"
                                minlength="<?= (int) MIN_PASSWORD_LENGTH ?>"
                                required
                                autocomplete="new-password">
                            <button type="button" class="input-reveal__btn" data-reveal-target="password_confirm"
                                    aria-label="Show password">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        <?php if (!empty($errors['confirm'])): ?>
                            <div class="field-error"><?= e($errors['confirm']) ?></div>
                        <?php endif; ?>
                    </div>

                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--primary btn--block btn--lg">
                        Update password
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <div class="land-login__pitch">
            <div class="land-login__pitch-eyebrow">Why this matters</div>
            <div class="land-login__pitch-stat">
                <span class="land-login__pitch-num">1<span class="land-login__pitch-sym">×</span></span>
                <span class="land-login__pitch-label">Each reset link works exactly once. After this, you'll need a fresh request.</span>
            </div>
            <ul class="land-login__pitch-list">
                <li><span class="land-login__check"></span> All older reset links are invalidated</li>
                <li><span class="land-login__check"></span> Your new password is hashed with bcrypt</li>
                <li><span class="land-login__check"></span> The change is recorded in the audit trail</li>
            </ul>
        </div>
    </main>

    <footer class="land-login__foot">
        <span>&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?> · Made by <strong>Ndege</strong></span>
    </footer>
</div>

</body>
</html>
