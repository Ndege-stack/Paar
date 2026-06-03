<?php
/**
 * =====================================================================
 * PAAR — patient/profile.php
 * ---------------------------------------------------------------------
 * Lets the logged-in patient edit their own personal information and
 * change their password. Email is read-only (administrator-controlled
 * to keep account-uniqueness invariants intact).
 *
 * Two independent forms on one page:
 *   - action=update_profile  → updates users.name + patients.* fields
 *   - action=change_password → verifies current password, updates hash
 * =====================================================================
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_role('patient');

$pdo       = db();
$userId    = current_user_id();
$patientId = current_patient_id();
if (!$patientId) {
    flash('danger', 'Patient profile missing. Please contact your clinic.');
    redirect(base_url('logout.php'));
}

/* ---- Load current values ----------------------------------------- */
$stmt = $pdo->prepare("
    SELECT u.name, u.email, p.*
      FROM users u
      JOIN patients p ON p.user_id = u.user_id
     WHERE u.user_id = ?
     LIMIT 1
");
$stmt->execute([$userId]);
$me = $stmt->fetch();
if (!$me) {
    flash('danger', 'Profile not found.');
    redirect(base_url('logout.php'));
}

$errors = [];

/* ---- POST: update profile or change password --------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    /* ----------------- Update personal information ---------------- */
    if ($action === 'update_profile') {
        $fields = [
            'name'              => trim((string) ($_POST['name'] ?? '')),
            'phone'             => trim((string) ($_POST['phone'] ?? '')),
            'date_of_birth'     => trim((string) ($_POST['date_of_birth'] ?? '')),
            'gender'            => trim((string) ($_POST['gender'] ?? '')),
            'address'           => trim((string) ($_POST['address'] ?? '')),
            'emergency_contact' => trim((string) ($_POST['emergency_contact'] ?? '')),
            'emergency_phone'   => trim((string) ($_POST['emergency_phone'] ?? '')),
        ];

        if ($fields['name'] === '') {
            $errors['name'] = 'Required.';
        }
        if ($fields['phone'] !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $fields['phone'])) {
            $errors['phone'] = 'Invalid phone number.';
        }
        if ($fields['emergency_phone'] !== ''
            && !preg_match('/^[0-9+\-\s()]{7,20}$/', $fields['emergency_phone'])) {
            $errors['emergency_phone'] = 'Invalid phone number.';
        }
        if ($fields['date_of_birth'] !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $fields['date_of_birth']);
            if (!$d || $d->format('Y-m-d') !== $fields['date_of_birth'] || $d > new DateTime('today')) {
                $errors['date_of_birth'] = 'Invalid date.';
            }
        }
        if ($fields['gender'] !== '' && !in_array($fields['gender'], ['male','female','other'], true)) {
            $errors['gender'] = 'Invalid value.';
        }

        if (!$errors) {
            try {
                $pdo->beginTransaction();
                $pdo->prepare('UPDATE users SET name = ? WHERE user_id = ?')
                    ->execute([$fields['name'], $userId]);

                $pdo->prepare(
                    'UPDATE patients SET
                         phone             = ?,
                         date_of_birth     = ?,
                         gender            = ?,
                         address           = ?,
                         emergency_contact = ?,
                         emergency_phone   = ?
                       WHERE patient_id = ?'
                )->execute([
                    $fields['phone']             ?: null,
                    $fields['date_of_birth']     ?: null,
                    $fields['gender']            ?: null,
                    $fields['address']           ?: null,
                    $fields['emergency_contact'] ?: null,
                    $fields['emergency_phone']   ?: null,
                    $patientId,
                ]);
                $pdo->commit();

                // Keep the displayed name in the topbar in sync immediately.
                $_SESSION['user_name'] = $fields['name'];

                flash('success', 'Profile updated successfully.');
                redirect(base_url('patient/profile.php'));
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors['form'] = DEBUG ? $e->getMessage() : 'Could not update profile.';
            }
        }

        // Re-render with submitted values on validation error.
        $me = array_merge($me, $fields);
    }

    /* ----------------- Change password ---------------------------- */
    if ($action === 'change_password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['new_password']     ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        $stmt = $pdo->prepare('SELECT password FROM users WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $hash = (string) $stmt->fetchColumn();

        if (!password_verify($current, $hash)) {
            $errors['current_password'] = 'Current password is incorrect.';
        }
        if (strlen($new) < MIN_PASSWORD_LENGTH) {
            $errors['new_password'] = 'New password must be at least '
                . MIN_PASSWORD_LENGTH . ' characters.';
        }
        if ($new !== $confirm) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }
        if (!empty($current) && $current === $new && empty($errors['current_password'])) {
            $errors['new_password'] = 'New password must differ from the current one.';
        }

        if (!array_intersect_key(
            $errors,
            array_flip(['current_password','new_password','confirm_password'])
        )) {
            $newHash = password_hash($new, PASSWORD_BCRYPT);
            $pdo->prepare('UPDATE users SET password = ? WHERE user_id = ?')
                ->execute([$newHash, $userId]);

            // Defence in depth: rotate the session id after a credential change.
            session_regenerate_id(true);

            flash('success', 'Password changed successfully.');
            redirect(base_url('patient/profile.php'));
        }
    }
}

$page_title   = 'Edit Profile';
$page_section = 'profile';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_patient.php';
?>

<div class="page-head">
    <div>
        <h1>Edit profile</h1>
        <div class="subtitle">Keep your personal information up to date.</div>
    </div>
    <a class="btn btn--outline" href="<?= e(base_url('patient/dashboard.php')) ?>">← Back to dashboard</a>
</div>

<div class="grid-2">
    <!-- ============== Personal information form ============== -->
    <div class="card">
        <div class="card__header">
            <h3 class="card__title">Personal information</h3>
        </div>

        <?php if (!empty($errors['form'])): ?>
            <div class="alert alert--danger"><?= e($errors['form']) ?></div>
        <?php endif; ?>

        <form method="post" novalidate>
            <div class="form-group">
                <label for="name">Full name *</label>
                <input id="name" name="name" type="text"
                       class="input <?= isset($errors['name'])?'input--error':'' ?>"
                       value="<?= e($me['name']) ?>" required>
                <?php if (!empty($errors['name'])): ?><div class="field-error"><?= e($errors['name']) ?></div><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input id="email" type="email" class="input" value="<?= e($me['email']) ?>" disabled>
                <div class="field-help">Email is managed by your clinic. Contact an administrator to change it.</div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input id="phone" name="phone" type="tel"
                           class="input <?= isset($errors['phone'])?'input--error':'' ?>"
                           value="<?= e($me['phone']) ?>">
                    <?php if (!empty($errors['phone'])): ?><div class="field-error"><?= e($errors['phone']) ?></div><?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="date_of_birth">Date of birth</label>
                    <input id="date_of_birth" name="date_of_birth" type="date"
                           class="input <?= isset($errors['date_of_birth'])?'input--error':'' ?>"
                           value="<?= e($me['date_of_birth']) ?>" max="<?= date('Y-m-d') ?>">
                    <?php if (!empty($errors['date_of_birth'])): ?><div class="field-error"><?= e($errors['date_of_birth']) ?></div><?php endif; ?>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="gender">Gender</label>
                    <select id="gender" name="gender" class="input">
                        <option value="">—</option>
                        <option value="male"   <?= ($me['gender']??'')==='male'?'selected':'' ?>>Male</option>
                        <option value="female" <?= ($me['gender']??'')==='female'?'selected':'' ?>>Female</option>
                        <option value="other"  <?= ($me['gender']??'')==='other'?'selected':'' ?>>Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="address">Address</label>
                    <input id="address" name="address" type="text" class="input"
                           value="<?= e($me['address']) ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="emergency_contact">Emergency contact name</label>
                    <input id="emergency_contact" name="emergency_contact" type="text" class="input"
                           value="<?= e($me['emergency_contact']) ?>">
                </div>
                <div class="form-group">
                    <label for="emergency_phone">Emergency contact phone</label>
                    <input id="emergency_phone" name="emergency_phone" type="tel"
                           class="input <?= isset($errors['emergency_phone'])?'input--error':'' ?>"
                           value="<?= e($me['emergency_phone']) ?>">
                    <?php if (!empty($errors['emergency_phone'])): ?><div class="field-error"><?= e($errors['emergency_phone']) ?></div><?php endif; ?>
                </div>
            </div>

            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_profile">
            <button type="submit" class="btn btn--accent">Save changes</button>
        </form>
    </div>

    <!-- ============== Change password form ============== -->
    <div class="card">
        <div class="card__header">
            <h3 class="card__title">Change password</h3>
        </div>

        <form method="post" novalidate>
            <div class="form-group">
                <label for="current_password">Current password *</label>
                <input id="current_password" name="current_password" type="password"
                       class="input <?= isset($errors['current_password'])?'input--error':'' ?>"
                       autocomplete="current-password" required>
                <?php if (!empty($errors['current_password'])): ?>
                    <div class="field-error"><?= e($errors['current_password']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="new_password">New password *</label>
                <input id="new_password" name="new_password" type="password"
                       class="input <?= isset($errors['new_password'])?'input--error':'' ?>"
                       minlength="<?= (int) MIN_PASSWORD_LENGTH ?>"
                       autocomplete="new-password" required>
                <?php if (!empty($errors['new_password'])): ?>
                    <div class="field-error"><?= e($errors['new_password']) ?></div>
                <?php else: ?>
                    <div class="field-help">Minimum <?= (int) MIN_PASSWORD_LENGTH ?> characters.</div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm new password *</label>
                <input id="confirm_password" name="confirm_password" type="password"
                       class="input <?= isset($errors['confirm_password'])?'input--error':'' ?>"
                       autocomplete="new-password" required>
                <?php if (!empty($errors['confirm_password'])): ?>
                    <div class="field-error"><?= e($errors['confirm_password']) ?></div>
                <?php endif; ?>
            </div>

            <?= csrf_field() ?>
            <input type="hidden" name="action" value="change_password">
            <button type="submit" class="btn btn--accent">Update password</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
