<?php
/**
 * =====================================================================
 * PAAR — register.php
 * ---------------------------------------------------------------------
 * Patient self-registration. Newly created accounts are saved with
 * users.status = 'pending' and must be approved by an administrator
 * via /admin/pending_approvals.php before they can sign in.
 *
 * Creates two rows atomically:
 *   - users     (role='patient', status='pending')
 *   - patients  (extended profile linked by user_id)
 * =====================================================================
 */

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/mailer.php';

if (is_logged_in()) {
    redirect(current_role() === 'admin' ? 'admin/dashboard.php' : 'patient/dashboard.php');
}

$errors = [];
$old = [
    'name'              => '',
    'email'             => '',
    'phone'             => '',
    'date_of_birth'     => '',
    'gender'            => '',
    'address'           => '',
    'emergency_contact' => '',
    'emergency_phone'   => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    foreach ($old as $k => $_) {
        $old[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    $password  = $_POST['password']         ?? '';
    $password2 = $_POST['password_confirm'] ?? '';

    /* ---- Validation ------------------------------------------------ */
    if ($old['name'] === '' || mb_strlen($old['name']) < 2) {
        $errors['name'] = 'Please enter your full name.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    $pwError = validate_password($password);
    if ($pwError !== null) $errors['password'] = $pwError;
    if ($password !== $password2) {
        $errors['password_confirm'] = 'Passwords do not match.';
    }
    if ($old['gender'] !== '' && !in_array($old['gender'], ['male','female','other'], true)) {
        $errors['gender'] = 'Invalid gender selection.';
    }
    if ($old['date_of_birth'] !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $old['date_of_birth']);
        if (!$d || $d->format('Y-m-d') !== $old['date_of_birth'] || $d > new DateTime('today')) {
            $errors['date_of_birth'] = 'Enter a valid date of birth.';
        }
    }
    if ($old['phone'] !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $old['phone'])) {
        $errors['phone'] = 'Phone number looks invalid.';
    }

    /* ---- Rate limiting -------------------------------------------- */
    if (!$errors) {
        $wait = login_lockout_seconds($old['email']);
        if ($wait > 0) {
            $mins = (int) ceil($wait / 60);
            $errors['form'] = 'Too many registration attempts from this address. '
                . 'Please wait ' . $mins . ' minute' . ($mins === 1 ? '' : 's') . '.';
        }
    }

    /* ---- Uniqueness check ------------------------------------------ */
    if (!isset($errors['email'])) {
        $stmt = db()->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$old['email']]);
        if ($stmt->fetchColumn()) {
            $errors['email'] = 'An account with this email already exists.';
        }
    }

    /* ---- Persist (transactional) ----------------------------------- */
    if (!$errors) {
        $pdo = db();
        try {
            $pdo->beginTransaction();

            $hash = password_hash($password, PASSWORD_BCRYPT);

            $u = $pdo->prepare(
                'INSERT INTO users (name, email, password, role, status)
                 VALUES (?, ?, ?, "patient", "pending")'
            );
            $u->execute([$old['name'], $old['email'], $hash]);
            $userId = (int) $pdo->lastInsertId();

            $p = $pdo->prepare(
                'INSERT INTO patients
                    (user_id, date_of_birth, gender, phone, address,
                     emergency_contact, emergency_phone)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $p->execute([
                $userId,
                $old['date_of_birth'] ?: null,
                $old['gender']        ?: null,
                $old['phone']         ?: null,
                $old['address']       ?: null,
                $old['emergency_contact'] ?: null,
                $old['emergency_phone']   ?: null,
            ]);

            // Notify all admins about the new pending registration.
            $admins = $pdo->query("SELECT user_id FROM users WHERE role='admin' AND status='active'");
            $notif = $pdo->prepare(
                'INSERT INTO notifications (user_id, message) VALUES (?, ?)'
            );
            $msg = 'New patient registration awaiting approval: ' . $old['name'];
            foreach ($admins as $row) {
                $notif->execute([$row['user_id'], $msg]);
            }

            $pdo->commit();

            // Send "registration received" email so patient knows what to expect.
            if (paar_mail_available()) {
                $confirmBody = '<div style="font-family:Arial,sans-serif;color:#0d1f17">'
                    . '<h2 style="color:#0b3d2e">Hello ' . htmlspecialchars($old['name']) . ',</h2>'
                    . '<p>Thank you for registering with <strong>' . htmlspecialchars(SITE_NAME) . '</strong>.</p>'
                    . '<p>Your account is <strong>pending review</strong> by the clinic administrator. '
                    . 'You will receive another email once your account has been approved and you can sign in.</p>'
                    . '<p>If you did not register for this account, please ignore this email.</p>'
                    . '<p style="color:#5a7066;font-size:12px;margin-top:24px">— The ' . htmlspecialchars(SITE_NAME) . ' team</p>'
                    . '</div>';
                paar_send_mail($old['email'], $old['name'],
                    'Registration received — ' . SITE_NAME, $confirmBody);
            }

            audit_log('patient_self_register', 'user', $userId, [
                'name'  => $old['name'],
                'email' => $old['email'],
            ], $userId, 'patient');

            flash('success',
                'Registration submitted. An administrator will review your account before you can sign in.');
            redirect('login.php');
        } catch (Throwable $e) {
            // MySQL may have auto-rolled back already (deadlock, some constraint
            // failures); calling rollBack() with no active transaction throws and
            // would mask the original exception.
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors['form'] = DEBUG ? ('Registration failed: ' . $e->getMessage())
                                     : 'Registration failed. Please try again.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create account · <?= e(SITE_NAME) ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Serif+Display:ital@0;1&display=swap">
    <link rel="stylesheet" href="assets/css/style.css">
    <script defer src="assets/js/main.js"></script>
</head>
<body>
<div class="auth">
    <section class="auth__hero">
        <div class="auth__brand">
            <span class="dot"></span>
            <span><?= e(SITE_NAME) ?></span>
        </div>
        <div class="auth__copy">
            <h1>Join PAAR for <span class="accent-italic">better</span> care.</h1>
            <p>
                Join PAAR to receive automated medication reminders, manage appointments,
                and stay on track with your treatment plan. After registration, an
                administrator at your clinic will review and activate your account.
            </p>
            <ul class="auth__features">
                <li>Confirm doses with one tap</li>
                <li>Get reminded by email and in-app alerts</li>
                <li>See your adherence streak grow over time</li>
            </ul>
        </div>
        <div class="auth__footnote">
            &copy; <?= date('Y') ?> <?= e(SITE_NAME) ?>
        </div>
    </section>

    <section class="auth__panel">
        <form class="auth__form auth__form--wide" method="post" novalidate autocomplete="on">
            <h2>Patient registration</h2>
            <p class="muted">Already registered? <a href="login.php">Sign in</a>.</p>

            <?php if (!empty($errors['form'])): ?>
                <div class="alert alert--danger"><?= e($errors['form']) ?></div>
            <?php endif; ?>

            <div class="form-row">
                <div class="form-group">
                    <label for="name">Full name</label>
                    <input id="name" name="name" type="text"
                           class="input <?= isset($errors['name']) ? 'input--error' : '' ?>"
                           placeholder="Jane Doe" value="<?= e($old['name']) ?>" required>
                    <?php if (!empty($errors['name'])): ?>
                        <div class="field-error"><?= e($errors['name']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email"
                           class="input <?= isset($errors['email']) ? 'input--error' : '' ?>"
                           placeholder="you@example.com" value="<?= e($old['email']) ?>" required
                           autocomplete="username">
                    <?php if (!empty($errors['email'])): ?>
                        <div class="field-error"><?= e($errors['email']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="phone">Phone <span class="label-hint">(optional)</span></label>
                    <input id="phone" name="phone" type="tel"
                           class="input <?= isset($errors['phone']) ? 'input--error' : '' ?>"
                           placeholder="+254 7XX XXX XXX" value="<?= e($old['phone']) ?>">
                    <?php if (!empty($errors['phone'])): ?>
                        <div class="field-error"><?= e($errors['phone']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="date_of_birth">Date of birth <span class="label-hint">(optional)</span></label>
                    <input id="date_of_birth" name="date_of_birth" type="date"
                           class="input <?= isset($errors['date_of_birth']) ? 'input--error' : '' ?>"
                           value="<?= e($old['date_of_birth']) ?>" max="<?= date('Y-m-d') ?>">
                    <?php if (!empty($errors['date_of_birth'])): ?>
                        <div class="field-error"><?= e($errors['date_of_birth']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="gender">Gender <span class="label-hint">(optional)</span></label>
                    <select id="gender" name="gender" class="input">
                        <option value="">Select...</option>
                        <option value="male"   <?= $old['gender']==='male'?'selected':'' ?>>Male</option>
                        <option value="female" <?= $old['gender']==='female'?'selected':'' ?>>Female</option>
                        <option value="other"  <?= $old['gender']==='other'?'selected':'' ?>>Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="address">Address <span class="label-hint">(optional)</span></label>
                    <input id="address" name="address" type="text" class="input"
                           placeholder="Town, county" value="<?= e($old['address']) ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="emergency_contact">Emergency contact name <span class="label-hint">(optional)</span></label>
                    <input id="emergency_contact" name="emergency_contact" type="text" class="input"
                           value="<?= e($old['emergency_contact']) ?>">
                </div>
                <div class="form-group">
                    <label for="emergency_phone">Emergency contact phone <span class="label-hint">(optional)</span></label>
                    <input id="emergency_phone" name="emergency_phone" type="tel" class="input"
                           value="<?= e($old['emergency_phone']) ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-reveal">
                        <input id="password" name="password" type="password"
                               class="input <?= isset($errors['password']) ? 'input--error' : '' ?>"
                               minlength="<?= (int) MIN_PASSWORD_LENGTH ?>" required
                               placeholder="At least 8 characters" autocomplete="new-password"
                               data-pw-strength="pw-strength-reg">
                        <button type="button" class="input-reveal__btn" data-reveal-target="password"
                                aria-label="Show password">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <div class="pw-strength" id="pw-strength-reg">
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
                    <label for="password_confirm">Confirm password</label>
                    <input id="password_confirm" name="password_confirm" type="password"
                           class="input <?= isset($errors['password_confirm']) ? 'input--error' : '' ?>"
                           minlength="<?= (int) MIN_PASSWORD_LENGTH ?>" required
                           autocomplete="new-password">
                    <?php if (!empty($errors['password_confirm'])): ?>
                        <div class="field-error"><?= e($errors['password_confirm']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <?= csrf_field() ?>
            <button type="submit" class="btn btn--primary btn--block btn--lg">Create account</button>

            <p class="alt-action">
                <a href="login.php">← Back to sign in</a>
            </p>
        </form>
    </section>
</div>
</body>
</html>
