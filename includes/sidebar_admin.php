<?php
/**
 * =====================================================================
 * PAAR — sidebar_admin.php
 * ---------------------------------------------------------------------
 * Left-hand navigation for administrator pages.
 * Caller may set $page_section to highlight the active item, e.g.:
 *   $page_section = 'patients';
 * =====================================================================
 */
require_once __DIR__ . '/auth_check.php';

$section = $page_section ?? '';
$active = static function (string $key) use ($section): string {
    return $section === $key ? ' is-active' : '';
};
/** Render aria-current="page" on the matching nav item for screen readers. */
$current = static function (string $key) use ($section): string {
    return $section === $key ? ' aria-current="page"' : '';
};
$userName = current_user_name();

// Pending approvals count for badge
$pendingCount = 0;
try {
    $pendingCount = (int) db()->query(
        "SELECT COUNT(*) FROM users WHERE role='patient' AND status='pending'"
    )->fetchColumn();
} catch (Throwable $e) { /* silently ignore */ }
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar__brand">
        <span class="sidebar__brand-mark"></span>
        <span class="sidebar__brand-name"><?= e(SITE_NAME) ?></span>
        <span class="sidebar__brand-tag">Admin</span>
        <button type="button" class="sidebar__close" id="sidebarClose" aria-label="Close menu">
            <?= icon('x', 18) ?>
        </button>
    </div>

    <nav class="sidebar__nav" aria-label="Admin navigation">
        <div class="group-label">Overview</div>
        <a class="nav-item<?= $active('dashboard') ?>"<?= $current('dashboard') ?> href="<?= e(base_url('admin/dashboard.php')) ?>">
            <span class="nav-item__icon"><?= icon('home') ?></span>
            <span class="nav-item__label">Dashboard</span>
        </a>
        <a class="nav-item<?= $active('analytics') ?>"<?= $current('analytics') ?> href="<?= e(base_url('admin/analytics.php')) ?>">
            <span class="nav-item__icon"><?= icon('chart') ?></span>
            <span class="nav-item__label">Analytics</span>
        </a>

        <div class="group-label">Patients</div>
        <a class="nav-item<?= $active('patients') ?>"<?= $current('patients') ?> href="<?= e(base_url('admin/patients.php')) ?>">
            <span class="nav-item__icon"><?= icon('users') ?></span>
            <span class="nav-item__label">Patients</span>
        </a>
        <a class="nav-item<?= $active('add_patient') ?>"<?= $current('add_patient') ?> href="<?= e(base_url('admin/add_patient.php')) ?>">
            <span class="nav-item__icon"><?= icon('plus') ?></span>
            <span class="nav-item__label">Add Patient</span>
        </a>
        <a class="nav-item<?= $active('pending') ?>"<?= $current('pending') ?> href="<?= e(base_url('admin/pending_approvals.php')) ?>">
            <span class="nav-item__icon"><?= icon('check') ?></span>
            <span class="nav-item__label">Pending Approvals</span>
            <?php if ($pendingCount > 0): ?>
                <span class="nav-badge" aria-label="<?= $pendingCount ?> awaiting approval"><?= $pendingCount ?></span>
            <?php endif; ?>
        </a>

        <div class="group-label">Care</div>
        <a class="nav-item<?= $active('medications') ?>"<?= $current('medications') ?> href="<?= e(base_url('admin/medications.php')) ?>">
            <span class="nav-item__icon"><?= icon('pill') ?></span>
            <span class="nav-item__label">Medications</span>
        </a>
        <a class="nav-item<?= $active('appointments') ?>"<?= $current('appointments') ?> href="<?= e(base_url('admin/appointments.php')) ?>">
            <span class="nav-item__icon"><?= icon('calendar') ?></span>
            <span class="nav-item__label">Appointments</span>
        </a>
        <a class="nav-item<?= $active('adherence') ?>"<?= $current('adherence') ?> href="<?= e(base_url('admin/adherence.php')) ?>">
            <span class="nav-item__icon"><?= icon('heart') ?></span>
            <span class="nav-item__label">Adherence</span>
        </a>

        <div class="group-label">Communication</div>
        <a class="nav-item<?= $active('inbox') ?>"<?= $current('inbox') ?> href="<?= e(base_url('admin/inbox.php')) ?>">
            <span class="nav-item__icon"><?= icon('mail') ?></span>
            <span class="nav-item__label">Inbox</span>
        </a>
        <a class="nav-item<?= $active('notifications') ?>"<?= $current('notifications') ?> href="<?= e(base_url('admin/notifications.php')) ?>">
            <span class="nav-item__icon"><?= icon('megaphone') ?></span>
            <span class="nav-item__label">Send Notifications</span>
        </a>

        <div class="group-label">Security</div>
        <a class="nav-item<?= $active('profile') ?>"<?= $current('profile') ?> href="<?= e(base_url('admin/profile.php')) ?>">
            <span class="nav-item__icon"><?= icon('user') ?></span>
            <span class="nav-item__label">My Profile</span>
        </a>
        <a class="nav-item<?= $active('admin_accounts') ?>"<?= $current('admin_accounts') ?> href="<?= e(base_url('admin/admin_accounts.php')) ?>">
            <span class="nav-item__icon"><?= icon('users') ?></span>
            <span class="nav-item__label">Admin Accounts</span>
        </a>
        <a class="nav-item<?= $active('audit_log') ?>"<?= $current('audit_log') ?> href="<?= e(base_url('admin/audit_log.php')) ?>">
            <span class="nav-item__icon"><?= icon('shield') ?></span>
            <span class="nav-item__label">Audit Log</span>
        </a>
    </nav>

    <div class="sidebar__footer">
        <div class="sidebar__user">
            <div class="user-avatar"><?= e(initials($userName)) ?></div>
            <div class="sidebar__user-meta">
                <div class="sidebar__user-greet">Signed in as</div>
                <div class="sidebar__user-name"><?= e($userName) ?></div>
            </div>
        </div>
        <a class="sidebar__signout" href="<?= e(base_url('logout.php')) ?>">
            <?= icon('logout', 16) ?>
            <span>Sign out</span>
        </a>
        <div class="sidebar__credit">Made by <strong>Ndege</strong></div>
    </div>
</aside>

<main class="main" id="main-content" tabindex="-1">
    <header class="topbar">
        <div class="topbar__left">
            <button type="button" class="menu-toggle" id="menuToggle" aria-label="Open menu" aria-expanded="false" aria-controls="sidebar">
                <?= icon('menu', 20) ?>
            </button>
            <div class="topbar__title"><?= e($page_title ?? 'Admin') ?></div>
        </div>
        <div class="topbar__right">
            <a class="notif-bell" href="<?= e(base_url('admin/inbox.php')) ?>" aria-label="Inbox">
                <?= icon('bell', 18) ?>
                <?php if (!empty($unread)): ?>
                    <span class="count"><?= (int) $unread ?></span>
                <?php endif; ?>
            </a>
            <div class="topbar__user">
                <div class="user-avatar user-avatar--sm"><?= e(initials($userName)) ?></div>
                <div class="topbar__user-text">
                    <span class="topbar__user-greet">Hello,</span>
                    <span class="topbar__user-name"><?= e($userName) ?></span>
                </div>
            </div>
        </div>
    </header>
    <div class="content">
        <?= render_flashes() ?>
