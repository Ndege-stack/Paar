<?php
/**
 * =====================================================================
 * PAAR — sidebar_patient.php
 * ---------------------------------------------------------------------
 * Left-hand navigation for patient pages.
 * Caller may set $page_section to highlight the active item.
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
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar__brand">
        <span class="sidebar__brand-mark"></span>
        <span class="sidebar__brand-name"><?= e(SITE_NAME) ?></span>
        <button type="button" class="sidebar__close" id="sidebarClose" aria-label="Close menu">
            <?= icon('x', 18) ?>
        </button>
    </div>

    <nav class="sidebar__nav" aria-label="Patient navigation">
        <div class="group-label">My Health</div>
        <a class="nav-item<?= $active('dashboard') ?>"<?= $current('dashboard') ?> href="<?= e(base_url('patient/dashboard.php')) ?>">
            <span class="nav-item__icon"><?= icon('home') ?></span>
            <span class="nav-item__label">Dashboard</span>
        </a>
        <a class="nav-item<?= $active('medications') ?>"<?= $current('medications') ?> href="<?= e(base_url('patient/medications.php')) ?>">
            <span class="nav-item__icon"><?= icon('pill') ?></span>
            <span class="nav-item__label">My Medications</span>
        </a>
        <a class="nav-item<?= $active('appointments') ?>"<?= $current('appointments') ?> href="<?= e(base_url('patient/appointments.php')) ?>">
            <span class="nav-item__icon"><?= icon('calendar') ?></span>
            <span class="nav-item__label">Appointments</span>
        </a>
        <a class="nav-item<?= $active('history') ?>"<?= $current('history') ?> href="<?= e(base_url('patient/adherence_history.php')) ?>">
            <span class="nav-item__icon"><?= icon('heart') ?></span>
            <span class="nav-item__label">Adherence History</span>
        </a>

        <div class="group-label">Inbox</div>
        <a class="nav-item<?= $active('notifications') ?>"<?= $current('notifications') ?> href="<?= e(base_url('patient/notifications.php')) ?>">
            <span class="nav-item__icon"><?= icon('mail') ?></span>
            <span class="nav-item__label">Notifications</span>
        </a>

        <div class="group-label">Account</div>
        <a class="nav-item<?= $active('profile') ?>"<?= $current('profile') ?> href="<?= e(base_url('patient/profile.php')) ?>">
            <span class="nav-item__icon"><?= icon('user') ?></span>
            <span class="nav-item__label">Edit Profile</span>
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
            <div class="topbar__title"><?= e($page_title ?? 'My Health') ?></div>
        </div>
        <div class="topbar__right">
            <a class="notif-bell" href="<?= e(base_url('patient/notifications.php')) ?>" aria-label="Notifications">
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
