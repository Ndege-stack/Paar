<?php
/**
 * =====================================================================
 * PAAR — forgot_password.php
 * ---------------------------------------------------------------------
 * Step 1 of the password-reset flow. The user enters an email address;
 * we ALWAYS show the same success message (regardless of whether the
 * email exists) so attackers cannot enumerate accounts.
 *
 * If the email exists, we issue a single-use token (stored as SHA-256)
 * and deliver the plain token to the user as a reset link via SMTP.
 * =====================================================================
 */

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/mailer.php';

if (is_logged_in()) {
    redirect(current_role() === 'admin' ? 'admin/dashboard.php' : 'patient/dashboard.php');
}

$errors = [];
$email  = '';
$sent   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = trim($_POST['email'] ?? '');
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    // Cheap rate-limit reuse: same window/threshold as login lockout.
    if (!$errors && login_lockout_seconds($email) > 0) {
        $errors['form'] = 'Too many attempts. Please try again in a few minutes.';
    }

    if (!$errors) {
        $stmt = db()->prepare('SELECT user_id, name FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = create_password_reset((int) $user['user_id']);
            if ($token) {
                $resetUrl = rtrim(SITE_URL, '/') . '/reset_password.php?token=' . urlencode($token);
                $bodyHtml = '<p>Hi ' . e($user['name']) . ',</p>'
                    . '<p>We received a request to reset your <strong>' . e(SITE_NAME) . '</strong> password. '
                    . 'Click the link below to choose a new one. The link expires in '
                    . PASSWORD_RESET_TTL_MINUTES . ' minutes and can only be used once.</p>'
                    . '<p><a href="' . e($resetUrl) . '" style="display:inline-block;background:#0b3d2e;color:#fff;text-decoration:none;padding:12px 22px;border-radius:10px;font-weight:600;">Reset my password</a></p>'
                    . '<p>If the button does not work, paste this URL into your browser:<br>'
                    . '<code>' . e($resetUrl) . '</code></p>'
                    . '<p>If you did not request this, you can safely ignore the email — '
                    . 'your password will not change.</p>'
                    . '<p>— The ' . e(SITE_NAME) . ' team</p>';

                paar_send_mail($email, $user['name'], 'Reset your ' . SITE_NAME . ' password', $bodyHtml);
                audit_log(
                    'password_reset_requested',
                    'user',
                    (int) $user['user_id'],
                    ['email' => $email],
                    (int) $user['user_id']
                );
            }
        } else {
            // Audit anonymous attempts too — useful to detect probing.
            audit_log('password_reset_requested_unknown_email', null, null, ['email' => $email]);
        }

        // Same response either way to prevent account enumeration.
        $sent = true;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset password · <?= e(SITE_NAME) ?></title>
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
                <h1 class="land-login__title">Forgot your password?</h1>
                <p class="land-login__subtitle">
                    Enter the email associated with your account and we'll send you a link to choose a new one.
                </p>
            </div>

            <?php if ($sent): ?>
                <div class="alert alert--success">
                    If <strong><?= e($email) ?></strong> is on file, a reset link is on its way.
                    The link expires in <?= (int) PASSWORD_RESET_TTL_MINUTES ?> minutes.
                </div>
                <p class="land-login__alt">
                    Didn't receive anything? Check your spam folder, or
                    <a href="forgot_password.php">try again</a>.
                </p>
            <?php else: ?>
                <?php if (!empty($errors['form'])): ?>
                    <div class="alert alert--danger"><?= e($errors['form']) ?></div>
                <?php endif; ?>

                <form class="land-login__form" method="post" novalidate autocomplete="on">
                    <div class="form-group">
                        <label for="email">Email address</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            class="input <?= isset($errors['email']) ? 'input--error' : '' ?>"
                            placeholder="you@example.com"
                            value="<?= e($email) ?>"
                            required
                            autocomplete="username"
                            autofocus>
                        <?php if (!empty($errors['email'])): ?>
                            <div class="field-error"><?= e($errors['email']) ?></div>
                        <?php endif; ?>
                    </div>

                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--primary btn--block btn--lg">
                        Send reset link
                    </button>

                    <p class="land-login__alt">
                        Remembered it? <a href="login.php">Sign in</a>
                    </p>
                </form>
            <?php endif; ?>
        </div>

        <div class="land-login__pitch">
            <div class="land-login__pitch-eyebrow">Account safety</div>
            <div class="land-login__pitch-stat">
                <span class="land-login__pitch-num">60<span class="land-login__pitch-sym">m</span></span>
                <span class="land-login__pitch-label">Reset links expire after sixty minutes — and can only be used once.</span>
            </div>
            <ul class="land-login__pitch-list">
                <li><span class="land-login__check"></span> Single-use, time-bound tokens</li>
                <li><span class="land-login__check"></span> No phone or in-person reset required</li>
                <li><span class="land-login__check"></span> Every request is audited</li>
            </ul>
        </div>
    </main>

    <footer class="land-login__foot">
        <span>&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?> · Made by <strong>Ndege</strong></span>
    </footer>
</div>

</body>
</html>
