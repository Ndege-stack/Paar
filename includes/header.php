<?php
/**
 * =====================================================================
 * PAAR — header.php
 * ---------------------------------------------------------------------
 * Common <head> + opening body markup for every authenticated page.
 *
 * Caller may set these BEFORE including this file:
 *   $page_title  - text shown in <title> and topbar
 *   $page_section - which sidebar nav item is "active"
 *   $extra_head  - any extra HTML to inject in <head>
 * =====================================================================
 */

// Pages that include this should have already required auth_check.php,
// but we include it defensively so render_flashes() and current_*()
// always exist.
require_once __DIR__ . '/auth_check.php';

$page_title  = $page_title  ?? 'PAAR';
$extra_head  = $extra_head  ?? '';
$unread = is_logged_in() ? unread_notifications_count() : 0;
?>
<!doctype html>
<html lang="en" class="has-toasts">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($page_title) ?> · <?= e(SITE_NAME) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(base_url('assets/img/favicon.svg')) ?>">

    <!-- Typography: Plus Jakarta Sans (body) + DM Serif Display (display) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Serif+Display:ital@0;1&display=swap">

    <!-- Stylesheet -->
    <link rel="stylesheet" href="<?= e(base_url('assets/css/style.css')) ?>?v=<?= filemtime(BASE_PATH.'/assets/css/style.css') ?>">

    <!-- Chart.js: only load on pages that set $use_chartjs = true -->
    <?php if (!empty($use_chartjs)): ?>
    <script defer src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <?php endif; ?>

    <!-- Project JS -->
    <script defer src="<?= e(base_url('assets/js/main.js')) ?>?v=<?= filemtime(BASE_PATH.'/assets/js/main.js') ?>"></script>

    <?= $extra_head /* trusted, set by caller */ ?>
    <noscript>
        <!-- Without JS we cannot upgrade flash banners into toasts, so show
             them as inline alerts the way they used to render. -->
        <style>.has-toasts .alert[data-flash] { display: block !important; }</style>
    </noscript>
</head>
<body>
<a class="skip-link" href="#main-content">Skip to main content</a>
<div class="app">
