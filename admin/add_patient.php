<?php
/**
 * =====================================================================
 * PAAR — admin/add_patient.php
 * ---------------------------------------------------------------------
 * Manual patient creation by an administrator. Unlike self-registration,
 * accounts created here are immediately status='active' so the patient
 * can sign in straight away with the temporary password set by the admin.
 * =====================================================================
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_role('admin');

$pdo = db();

$errors = [];
$old = [
    'name' => '', 'email' => '', 'phone' => '', 'date_of_birth' => '',
    'gender' => '', 'address' => '', 'emergency_contact' => '', 'emergency_phone' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    foreach ($old as $k => $_) { $old[$k] = trim((string) ($_POST[$k] ?? '')); }
    $password = $_POST['password'] ?? '';

    /* ---- Validation ----------------------------------------------- */
    if ($old['name'] === '')                                     $errors['name']     = 'Required.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL))       $errors['email']    = 'Valid email required.';
    $pwError = validate_password($password);
    if ($pwError !== null) $errors['password'] = $pwError;
    if ($old['gender'] !== '' && !in_array($old['gender'], ['male','female','other'], true))
        $errors['gender']   = 'Invalid value.';
    if ($old['date_of_birth'] !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $old['date_of_birth']);
        if (!$d || $d->format('Y-m-d') !== $old['date_of_birth'] || $d > new DateTime('today'))
            $errors['date_of_birth'] = 'Invalid date.';
    }
    if ($old['phone'] !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $old['phone']))
        $errors['phone']    = 'Invalid phone.';

    if (!isset($errors['email'])) {
        $stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$old['email']]);
        if ($stmt->fetchColumn()) $errors['email'] = 'Email already in use.';
    }

    /* ---- Persist (transactional) ---------------------------------- */
    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $u = $pdo->prepare(
                'INSERT INTO users (name, email, password, role, status)
                 VALUES (?, ?, ?, "patient", "active")'
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

            // Welcome notification for the new patient.
            $pdo->prepare(
                'INSERT INTO notifications (user_id, message) VALUES (?, ?)'
            )->execute([$userId, 'Welcome to PAAR! Your account has been created.']);

            $pdo->commit();
            audit_log('patient_create', 'user', $userId, [
                'name'  => $old['name'],
                'email' => $old['email'],
            ]);
            flash('success', 'Patient "' . $old['name'] . '" created and activated.');
            redirect(base_url('admin/patients.php'));
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors['form'] = DEBUG ? $e->getMessage() : 'Could not create patient. Please try again.';
        }
    }
}

$page_title   = 'Add Patient';
$page_section = 'add_patient';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="page-head">
    <div>
        <h1>Add a patient</h1>
        <div class="subtitle">Create an account on behalf of a patient. They can sign in immediately.</div>
    </div>
    <a class="btn btn--outline" href="<?= e(base_url('admin/patients.php')) ?>">← Patients</a>
</div>

<div class="card card--narrow">
    <?php if (!empty($errors['form'])): ?>
        <div class="alert alert--danger"><?= e($errors['form']) ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
        <div class="form-row">
            <div class="form-group">
                <label for="name">Full name *</label>
                <input id="name" name="name" type="text"
                       class="input <?= isset($errors['name'])?'input--error':'' ?>"
                       value="<?= e($old['name']) ?>" required>
                <?php if (!empty($errors['name'])): ?><div class="field-error"><?= e($errors['name']) ?></div><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="email">Email *</label>
                <input id="email" name="email" type="email"
                       class="input <?= isset($errors['email'])?'input--error':'' ?>"
                       value="<?= e($old['email']) ?>" required>
                <?php if (!empty($errors['email'])): ?><div class="field-error"><?= e($errors['email']) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="phone">Phone</label>
                <input id="phone" name="phone" type="tel"
                       class="input <?= isset($errors['phone'])?'input--error':'' ?>"
                       value="<?= e($old['phone']) ?>">
                <?php if (!empty($errors['phone'])): ?><div class="field-error"><?= e($errors['phone']) ?></div><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="date_of_birth">Date of birth</label>
                <input id="date_of_birth" name="date_of_birth" type="date"
                       class="input <?= isset($errors['date_of_birth'])?'input--error':'' ?>"
                       value="<?= e($old['date_of_birth']) ?>" max="<?= date('Y-m-d') ?>">
                <?php if (!empty($errors['date_of_birth'])): ?><div class="field-error"><?= e($errors['date_of_birth']) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="gender">Gender</label>
                <select id="gender" name="gender" class="input">
                    <option value="">—</option>
                    <option value="male"   <?= $old['gender']==='male'?'selected':'' ?>>Male</option>
                    <option value="female" <?= $old['gender']==='female'?'selected':'' ?>>Female</option>
                    <option value="other"  <?= $old['gender']==='other'?'selected':'' ?>>Other</option>
                </select>
            </div>
            <div class="form-group">
                <label for="address">Address</label>
                <input id="address" name="address" type="text" class="input" value="<?= e($old['address']) ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="emergency_contact">Emergency contact name</label>
                <input id="emergency_contact" name="emergency_contact" type="text" class="input"
                       value="<?= e($old['emergency_contact']) ?>">
            </div>
            <div class="form-group">
                <label for="emergency_phone">Emergency contact phone</label>
                <input id="emergency_phone" name="emergency_phone" type="tel" class="input"
                       value="<?= e($old['emergency_phone']) ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="password">Temporary password *</label>
            <input id="password" name="password" type="password"
                   class="input <?= isset($errors['password'])?'input--error':'' ?>"
                   minlength="<?= (int) MIN_PASSWORD_LENGTH ?>" required
                   placeholder="Share with the patient securely">
            <?php if (!empty($errors['password'])): ?>
                <div class="field-error"><?= e($errors['password']) ?></div>
            <?php else: ?>
                <div class="field-help">Minimum <?= (int) MIN_PASSWORD_LENGTH ?> characters. Ask the patient to change it on first sign-in.</div>
            <?php endif; ?>
        </div>

        <?= csrf_field() ?>
        <div class="btn-row">
            <button type="submit" class="btn btn--accent">Create patient</button>
            <a class="btn btn--outline" href="<?= e(base_url('admin/patients.php')) ?>">Cancel</a>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
