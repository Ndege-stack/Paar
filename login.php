<?php
/**
 * =====================================================================
 * PAAR — login.php
 * ---------------------------------------------------------------------
 * Focused sign-in page. The marketing landing lives in index.php; this
 * page only handles the credentials flow.
 *
 * On successful POST:
 *   - admin   -> admin/dashboard.php
 *   - patient -> patient/dashboard.php
 *
 * Pending or suspended accounts are blocked with a friendly message.
 * =====================================================================
 */

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/security.php';

// If the user is already logged in, send them straight to their dashboard.
if (is_logged_in()) {
    redirect(current_role() === 'admin' ? 'admin/dashboard.php' : 'patient/dashboard.php');
}

$errors = [];
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email    = trim($_POST['email']    ?? '');
    $password =      $_POST['password'] ?? '';

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    if ($password === '' || strlen($password) < MIN_PASSWORD_LENGTH) {
        $errors['password'] = 'Password must be at least ' . MIN_PASSWORD_LENGTH . ' characters.';
    }

    if (!$errors) {
        // Rate-limit: block if too many recent failures from this IP or email.
        $wait = login_lockout_seconds($email);
        if ($wait > 0) {
            $mins = (int) ceil($wait / 60);
            $errors['form'] = 'Too many failed attempts. Please wait ' . $mins
                . ' minute' . ($mins === 1 ? '' : 's') . ' before trying again.';
        }
    }

    if (!$errors) {
        $stmt = db()->prepare(
            'SELECT user_id, name, email, password, role, status
               FROM users WHERE email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            $errors['form'] = 'Invalid email or password.';
            record_login_attempt($email, false);
            audit_log('login_failed', 'user', null, ['email' => $email], null, null);
        } elseif ($user['status'] === 'pending') {
            $errors['form'] = 'Your account is awaiting administrator approval.';
            record_login_attempt($email, false);
        } elseif ($user['status'] === 'suspended') {
            $errors['form'] = 'Your account has been suspended. Contact the clinic.';
            record_login_attempt($email, false);
        } else {
            record_login_attempt($email, true);
            audit_log('login_success', 'user', (int) $user['user_id'],
                [], (int) $user['user_id'], $user['role']);
            session_regenerate_id(true);
            $_SESSION['user_id']      = (int) $user['user_id'];
            $_SESSION['role']         = $user['role'];
            $_SESSION['name']         = $user['name'];
            $_SESSION['last_active']  = time();
            unset($_SESSION[CSRF_TOKEN_KEY]);
            csrf_token();

            redirect($user['role'] === 'admin' ? 'admin/dashboard.php' : 'patient/dashboard.php');
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · <?= e(SITE_NAME) ?></title>
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
        <a class="land-login__back" href="index.php">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>
            Back to home
        </a>
    </header>

    <main class="land-login__main">
        <div class="land-login__card">
            <div class="land-login__card-head">
                <h1 class="land-login__title">Welcome back.</h1>
                <p class="land-login__subtitle">Sign in to your <?= e(SITE_NAME) ?> dashboard.</p>
            </div>

            <?= render_flashes() ?>

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

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-reveal">
                        <input
                            id="password"
                            name="password"
                            type="password"
                            class="input <?= isset($errors['password']) ? 'input--error' : '' ?>"
                            placeholder="At least <?= (int) MIN_PASSWORD_LENGTH ?> characters"
                            minlength="<?= (int) MIN_PASSWORD_LENGTH ?>"
                            required
                            autocomplete="current-password">
                        <button type="button" class="input-reveal__btn" data-reveal-target="password"
                                aria-label="Show password">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <?php if (!empty($errors['password'])): ?>
                        <div class="field-error"><?= e($errors['password']) ?></div>
                    <?php endif; ?>
                    <div class="land-login__forgot">
                        <a href="forgot_password.php">Forgot password?</a>
                    </div>
                </div>

                <?= csrf_field() ?>
                <button type="submit" class="btn btn--primary btn--block btn--lg">
                    Sign in
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left:6px;vertical-align:-3px"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
                </button>

                <p class="land-login__alt">
                    New patient? <a href="register.php">Create an account</a>
                </p>
            </form>
        </div>

        <div class="land-login__pitch">
            <div class="land-login__pitch-eyebrow">Why <?= e(SITE_NAME) ?></div>
            <div class="land-login__pitch-stat">
                <span class="land-login__pitch-num">96<span class="land-login__pitch-sym">%</span></span>
                <span class="land-login__pitch-label">of doses confirmed within the reminder window across pilot clinics.</span>
            </div>
            <ul class="land-login__pitch-list">
                <li><span class="land-login__check"></span> One-tap dose confirmation</li>
                <li><span class="land-login__check"></span> Automated email + in-app reminders</li>
                <li><span class="land-login__check"></span> Real-time adherence analytics</li>
                <li><span class="land-login__check"></span> Built for African clinics</li>
            </ul>
        </div>
    </main>

    <footer class="land-login__foot">
        <span>&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?> · Made by <strong>Ndege</strong></span>
    </footer>
</div>

</body>
</html>
