<?php
/**
 * =====================================================================
 * PAAR — privacy.php
 * Public privacy policy. No authentication required.
 * =====================================================================
 */
require_once __DIR__ . '/includes/auth_check.php';
$year = date('Y');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Privacy Policy · <?= e(SITE_NAME) ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Serif+Display:ital@0;1&display=swap">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(BASE_PATH.'/assets/css/style.css') ?>">
</head>
<body class="land-body">

<header class="land-nav" id="land-nav">
    <div class="land-nav__inner">
        <a class="land-nav__brand" href="index.php">
            <span class="land-chip__dot"></span>
            <span class="land-nav__brand-name"><?= e(SITE_NAME) ?></span>
        </a>
        <a class="land-btn land-btn--ghost" href="index.php">← Back to home</a>
    </div>
</header>

<main style="max-width:760px;margin:60px auto;padding:0 24px 80px">

    <h1 style="font-family:'DM Serif Display',serif;font-weight:400;font-size:clamp(28px,4vw,44px);color:var(--primary);margin-bottom:8px">
        Privacy Policy
    </h1>
    <p style="color:var(--text-muted);margin-bottom:40px">
        Last updated: <?= date('j F Y') ?>
    </p>

    <section>
        <h2>1. Who we are</h2>
        <p>
            <?= e(SITE_NAME) ?> ("we", "us", "our") is a patient adherence and appointment
            reminder platform built for small and medium clinics. This policy explains what
            personal data we collect, how we use it, and your rights over it, in accordance
            with the <strong>Kenya Data Protection Act, 2019</strong>.
        </p>
    </section>

    <section>
        <h2>2. Data we collect</h2>
        <p>We collect and store the following categories of personal data:</p>
        <ul>
            <li><strong>Identity data</strong> — full name, gender, date of birth</li>
            <li><strong>Contact data</strong> — email address, phone number, postal address</li>
            <li><strong>Emergency contact</strong> — name and phone of an emergency contact you provide</li>
            <li><strong>Health data</strong> — medications, dosages, appointment records, adherence history</li>
            <li><strong>Technical data</strong> — IP address, browser user-agent, session identifiers</li>
            <li><strong>Usage data</strong> — actions taken within the platform (audit log)</li>
        </ul>
        <p>We do not collect payment information, biometric data, or any data from third parties.</p>
    </section>

    <section>
        <h2>3. How we use your data</h2>
        <p>Your data is used exclusively for the following purposes:</p>
        <ul>
            <li>Providing medication and appointment reminders</li>
            <li>Enabling your clinic to monitor your adherence to prescribed treatment</li>
            <li>Authenticating your identity when you sign in</li>
            <li>Security and fraud prevention (rate limiting, audit logging)</li>
        </ul>
        <p>We do not sell, share, or transfer your data to third parties for marketing purposes.</p>
    </section>

    <section>
        <h2>4. Email communications</h2>
        <p>
            If you provide an email address, we use it to send:
        </p>
        <ul>
            <li>Medication and appointment reminders (from your clinic's configured SMTP account)</li>
            <li>Account status notifications (approval, password reset)</li>
        </ul>
        <p>
            Reminder emails are transactional in nature. You cannot unsubscribe from them while
            your account is active, as they are a core function of the service. Contact your
            clinic to deactivate your account if you no longer wish to receive them.
        </p>
    </section>

    <section>
        <h2>5. Data retention</h2>
        <p>
            Your personal data is retained for the duration of your active patient relationship
            with the clinic, plus a reasonable period thereafter for clinical record-keeping
            purposes. Audit log entries older than 12 months are automatically purged.
        </p>
    </section>

    <section>
        <h2>6. Your rights</h2>
        <p>Under the Kenya Data Protection Act, 2019, you have the right to:</p>
        <ul>
            <li><strong>Access</strong> — request a copy of the personal data we hold about you</li>
            <li><strong>Correction</strong> — update inaccurate data via your profile page</li>
            <li><strong>Erasure</strong> — request deletion of your account and associated data</li>
            <li><strong>Portability</strong> — ask your clinic's administrator to export your data as CSV</li>
            <li><strong>Objection</strong> — object to processing where you have grounds to do so</li>
        </ul>
        <p>
            To exercise these rights, contact your clinic's administrator directly. They are the
            data controller for your personal information.
        </p>
    </section>

    <section>
        <h2>7. Security</h2>
        <p>
            We implement industry-standard technical measures including bcrypt password hashing,
            CSRF protection, TLS-encrypted transmission, session hardening (HttpOnly/SameSite
            cookies), and role-based access controls. Despite these measures, no system is
            completely immune to security risks.
        </p>
    </section>

    <section>
        <h2>8. Cookies</h2>
        <p>
            We use a single session cookie (<code><?= e(SESSION_NAME) ?></code>) which is
            strictly necessary for authentication. No tracking, analytics, or advertising
            cookies are set. The cookie expires when you close your browser or are signed out.
        </p>
    </section>

    <section>
        <h2>9. Changes to this policy</h2>
        <p>
            We may update this policy when our practices change. The "Last updated" date at
            the top will reflect any changes. Continued use of the platform after changes
            constitutes acceptance of the revised policy.
        </p>
    </section>

    <section>
        <h2>10. Contact</h2>
        <p>
            Questions about this privacy policy should be directed to your clinic administrator.
            For platform-level concerns, contact the PAAR development team.
        </p>
    </section>

</main>

<footer style="text-align:center;padding:24px;color:var(--text-muted);font-size:13px;border-top:1px solid var(--border)">
    &copy; <?= $year ?> <?= e(SITE_NAME) ?> · Made by <strong>Ndege</strong>
    &nbsp;&middot;&nbsp;
    <a href="index.php">Home</a>
    &nbsp;&middot;&nbsp;
    <a href="login.php">Sign in</a>
</footer>

</body>
</html>
