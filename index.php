<?php
/**
 * =====================================================================
 * PAAR — index.php
 * ---------------------------------------------------------------------
 * Public marketing landing page. Sign-in lives at /login.php.
 *
 * If the visitor is already authenticated, they are redirected straight
 * to their role-appropriate dashboard.
 * =====================================================================
 */

require_once __DIR__ . '/includes/auth_check.php';

if (is_logged_in()) {
    redirect(current_role() === 'admin' ? 'admin/dashboard.php' : 'patient/dashboard.php');
}

$year = date('Y');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(SITE_NAME) ?> · Healthcare reminders patients actually act on.</title>
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <meta name="description" content="<?= e(SITE_NAME) ?> is a Patient Adherence and Appointment Reminder platform built for small and medium clinics. Confirm doses, auto-detect misses, send reminders.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Serif+Display:ital@0;1&display=swap">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="land-body">

<!-- ============================================================== -->
<!-- NAV                                                            -->
<!-- ============================================================== -->
<header class="land-nav" id="land-nav">
    <div class="land-nav__inner">
        <a class="land-nav__brand" href="#top">
            <span class="land-chip__dot"></span>
            <span class="land-nav__brand-name"><?= e(SITE_NAME) ?></span>
        </a>

        <nav class="land-nav__links" aria-label="Primary">
            <a href="#features">Features</a>
            <a href="#how">How it works</a>
            <a href="#clinics">For clinics</a>
            <a href="#security">Security</a>
        </nav>

        <div class="land-nav__cta">
            <a class="land-btn land-btn--ghost" href="login.php">Sign in</a>
            <a class="land-btn land-btn--primary" href="register.php">
                Get started
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
            </a>
        </div>

        <button class="land-nav__burger" id="land-nav-burger" aria-label="Open menu" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
    </div>
</header>

<main id="top">

<!-- ============================================================== -->
<!-- HERO                                                           -->
<!-- ============================================================== -->
<section class="land-hero">
    <div class="land-hero__bg" aria-hidden="true">
        <div class="land-hero__blob land-hero__blob--a"></div>
        <div class="land-hero__blob land-hero__blob--b"></div>
        <div class="land-hero__grid"></div>
        <div class="land-hero__spotlight"></div>
    </div>

    <div class="land-hero__inner">
        <div class="land-hero__copy">
            <span class="land-chip" data-reveal>
                <span class="land-chip__dot"></span>
                Now serving clinics · Beta
            </span>

            <h1 class="land-hero__title" data-reveal>
                Healthcare reminders<br>
                patients <span class="land-italic">actually</span> act on.
            </h1>

            <p class="land-hero__lead" data-reveal data-reveal-delay="80">
                <?= e(SITE_NAME) ?> is a patient adherence and appointment reminder platform
                built for small and medium clinics. Confirm doses with one tap, auto-detect
                missed doses, and reach patients over email and in-app, all from one
                clinical-grade dashboard.
            </p>

            <div class="land-hero__ctas" data-reveal data-reveal-delay="160">
                <a class="land-btn land-btn--primary land-btn--lg" href="register.php">
                    Get started free
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
                </a>
                <a class="land-btn land-btn--ghost land-btn--lg" href="#how">
                    See how it works
                </a>
            </div>

            <ul class="land-hero__assurances" data-reveal data-reveal-delay="240">
                <li><span class="land-tick"></span> No setup fee</li>
                <li><span class="land-tick"></span> Email + in-app reminders</li>
                <li><span class="land-tick"></span> Works on cheap Android</li>
            </ul>
        </div>

        <div class="land-hero__visual" data-reveal data-reveal-delay="120">
            <!-- Mock dashboard preview (CSS-only). Swap with a real screenshot later. -->
            <div class="land-mock" aria-hidden="true">
                <div class="land-mock__chrome">
                    <span></span><span></span><span></span>
                    <div class="land-mock__url">paar.app/patient/dashboard</div>
                </div>
                <div class="land-mock__body">
                    <aside class="land-mock__sidebar">
                        <div class="land-mock__brand">
                            <span class="land-mock__brand-dot"></span>
                            PAAR
                        </div>
                        <ul>
                            <li class="is-active"><span class="land-mock__nav-i"></span> Dashboard</li>
                            <li><span class="land-mock__nav-i"></span> Medications</li>
                            <li><span class="land-mock__nav-i"></span> Appointments</li>
                            <li><span class="land-mock__nav-i"></span> Notifications</li>
                        </ul>
                    </aside>
                    <div class="land-mock__main">
                        <div class="land-mock__head">
                            <div>
                                <div class="land-mock__hello">Hello, Ndege 👋</div>
                                <div class="land-mock__sub">Here's your plan for today.</div>
                            </div>
                            <div class="land-mock__bell">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0112 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 003.4 0"/></svg>
                                <span class="land-mock__bell-dot"></span>
                            </div>
                        </div>

                        <div class="land-mock__stats">
                            <div class="land-mock__stat land-mock__stat--success">
                                <span class="land-mock__stat-label">Streak</span>
                                <span class="land-mock__stat-value">14</span>
                                <span class="land-mock__stat-trend">↑ on track</span>
                            </div>
                            <div class="land-mock__stat">
                                <span class="land-mock__stat-label">Adherence · 30d</span>
                                <span class="land-mock__stat-value">20<span class="land-mock__sym">%</span></span>
                                <span class="land-mock__stat-trend">28 taken · 1 missed</span>
                            </div>
                            <div class="land-mock__stat land-mock__stat--gold">
                                <span class="land-mock__stat-label">Today</span>
                                <span class="land-mock__stat-value">3</span>
                                <span class="land-mock__stat-trend">scheduled doses</span>
                            </div>
                        </div>

                        <div class="land-mock__section-title">Today's schedule</div>
                        <div class="land-mock__doses">
                            <div class="land-mock__dose land-mock__dose--taken">
                                <div>
                                    <div class="land-mock__dose-name">Cold Cap — 20ml</div>
                                    <div class="land-mock__dose-meta">Daily · scheduled 08:00 · confirmed 08:14</div>
                                </div>
                                <span class="land-mock__pill land-mock__pill--success">Taken</span>
                            </div>
                            <div class="land-mock__dose land-mock__dose--pending">
                                <div>
                                    <div class="land-mock__dose-name">Panadol — 1 tablet</div>
                                    <div class="land-mock__dose-meta">Afternoon · scheduled 14:00</div>
                                </div>
                                <button class="land-mock__pill land-mock__pill--primary">Confirm taken</button>
                            </div>
                            <div class="land-mock__dose land-mock__dose--missed">
                                <div>
                                    <div class="land-mock__dose-name">Iron — 1 tablet</div>
                                    <div class="land-mock__dose-meta">Morning · scheduled 08:00</div>
                                </div>
                                <span class="land-mock__pill land-mock__pill--danger">⚠ Missed</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Floating notification card -->
            <div class="land-float land-float--notif" data-reveal data-reveal-delay="320">
                <div class="land-float__icon">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/></svg>
                </div>
                <div>
                    <div class="land-float__title">Dose confirmed</div>
                    <div class="land-float__meta">Ndege · Cold Cap 20ml · 08:14</div>
                </div>
            </div>

            <!-- Floating streak card -->
            <div class="land-float land-float--streak" data-reveal data-reveal-delay="420">
                <div class="land-float__icon land-float__icon--gold">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2s4 5 4 9a4 4 0 11-8 0c0-2 2-3 2-3s-2-3-2-5 4-1 4-1z"/></svg>
                </div>
                <div>
                    <div class="land-float__title">14-day streak 🔥</div>
                    <div class="land-float__meta">Best in clinic · this month</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================== -->
<!-- TRUST STRIP                                                    -->
<!-- ============================================================== -->
<section class="land-trust" data-reveal>
    <div class="land-section-inner land-trust__inner">
        <div class="land-trust__stat">
            <div class="land-trust__num" data-countup data-countup-end="96" data-countup-suffix="%">0</div>
            <div class="land-trust__label">Adherence rate across pilot clinics</div>
        </div>
        <div class="land-trust__stat">
            <div class="land-trust__num" data-countup data-countup-end="100" data-countup-suffix="+">0</div>
            <div class="land-trust__label">Doses confirmed via PAAR</div>
        </div>
        <div class="land-trust__stat">
            <div class="land-trust__num" data-countup data-countup-end="2">0</div>
            <div class="land-trust__label">Clinics actively onboarded</div>
        </div>
        <div class="land-trust__stat">
            <div class="land-trust__num" data-countup data-countup-end="24" data-countup-suffix="/7">0</div>
            <div class="land-trust__label">Reminder engine uptime</div>
        </div>
    </div>
</section>

<!-- ============================================================== -->
<!-- PROBLEM                                                        -->
<!-- ============================================================== -->
<section class="land-problem">
    <div class="land-section-inner land-problem__inner">
        <div class="land-problem__copy">
            <div class="land-eyebrow" data-reveal>The problem</div>
            <h2 class="land-h2" data-reveal>
                Missed doses are the silent epidemic of <span class="land-italic">outpatient care.</span>
            </h2>
            <p data-reveal data-reveal-delay="80">
                Up to half of patients on chronic medication don't take their doses as
                prescribed. Clinicians lose visibility the moment a patient walks out the
                door, and by the next visit, the damage is done.
            </p>
            <p data-reveal data-reveal-delay="160">
                <?= e(SITE_NAME) ?> closes that gap. Patients confirm doses in seconds.
                Missed doses get flagged automatically. Clinicians see who's drifting,
                in real time.
            </p>
        </div>

        <div class="land-problem__quote" data-reveal data-reveal-delay="120">
            <div class="land-problem__quote-mark">“</div>
            <p>
                Roughly <strong>50%</strong> of medications for chronic disease are not taken
                as prescribed. Adherence interventions could have a far greater effect on
                public health than any improvement in specific medical treatments.
            </p>
            <div class="land-problem__quote-cite">— World Health Organization</div>
        </div>
    </div>
</section>

<!-- ============================================================== -->
<!-- FEATURES                                                       -->
<!-- ============================================================== -->
<section class="land-features" id="features">
    <div class="land-section-inner">
        <div class="land-section-head">
            <div class="land-eyebrow" data-reveal>Platform</div>
            <h2 class="land-h2" data-reveal>Built around the realities of <span class="land-italic">outpatient care.</span></h2>
            <p class="land-section-lead" data-reveal data-reveal-delay="80">
                Three pillars work together so adherence stops being a guess and starts
                being a number you can act on.
            </p>
        </div>

        <div class="land-features__grid">
            <article class="land-feature" data-reveal>
                <div class="land-feature__icon">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/></svg>
                </div>
                <h3>Adherence tracking</h3>
                <p>
                    Per-slot dose confirmation with streaks, 30-day trends, and automatic
                    missed-dose detection after a 4-hour grace window.
                </p>
                <ul class="land-feature__list">
                    <li><span class="land-tick"></span> One-tap "Confirm taken"</li>
                    <li><span class="land-tick"></span> Late confirmation supported</li>
                    <li><span class="land-tick"></span> Streak counter to motivate</li>
                </ul>
            </article>

            <article class="land-feature land-feature--accent" data-reveal data-reveal-delay="100">
                <div class="land-feature__icon">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0112 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 003.4 0"/></svg>
                </div>
                <h3>Smart reminders</h3>
                <p>
                    A cron-driven reminder engine queues doses up to a day ahead, sends
                    email + in-app pushes, and quietly retries when delivery fails.
                </p>
                <ul class="land-feature__list">
                    <li><span class="land-tick"></span> Once / twice / 3× daily / weekly</li>
                    <li><span class="land-tick"></span> Appointment reminders 24h ahead</li>
                    <li><span class="land-tick"></span> Email + in-app inbox</li>
                </ul>
            </article>

            <article class="land-feature" data-reveal data-reveal-delay="200">
                <div class="land-feature__icon">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 14l3-3 3 3 5-5"/></svg>
                </div>
                <h3>Clinical analytics</h3>
                <p>
                    Real-time KPIs and at-risk patient leaderboards so clinicians can
                    intervene before adherence becomes a crisis.
                </p>
                <ul class="land-feature__list">
                    <li><span class="land-tick"></span> Top patients with missed doses</li>
                    <li><span class="land-tick"></span> Filter by patient + status</li>
                    <li><span class="land-tick"></span> 30-day adherence rate</li>
                </ul>
            </article>
        </div>
    </div>
</section>

<!-- ============================================================== -->
<!-- PRODUCT SHOWCASE                                               -->
<!-- ============================================================== -->
<section class="land-show">
    <div class="land-section-inner">
        <div class="land-section-head">
            <div class="land-eyebrow" data-reveal>The dashboard</div>
            <h2 class="land-h2" data-reveal>Everything a clinician needs <span class="land-italic">at a glance.</span></h2>
        </div>

        <div class="land-show__frame" data-reveal>
            <!-- Admin mock (extended) -->
            <div class="land-mock land-mock--admin">
                <div class="land-mock__chrome">
                    <span></span><span></span><span></span>
                    <div class="land-mock__url">paar.app/admin/adherence</div>
                </div>
                <div class="land-mock__body">
                    <aside class="land-mock__sidebar">
                        <div class="land-mock__brand"><span class="land-mock__brand-dot"></span> PAAR</div>
                        <ul>
                            <li><span class="land-mock__nav-i"></span> Dashboard</li>
                            <li><span class="land-mock__nav-i"></span> Patients</li>
                            <li class="is-active"><span class="land-mock__nav-i"></span> Adherence</li>
                            <li><span class="land-mock__nav-i"></span> Medications</li>
                            <li><span class="land-mock__nav-i"></span> Appointments</li>
                        </ul>
                    </aside>
                    <div class="land-mock__main">
                        <div class="land-mock__head">
                            <div>
                                <div class="land-mock__hello">Adherence monitoring</div>
                                <div class="land-mock__sub">Track confirmations across your facility.</div>
                            </div>
                        </div>

                        <div class="land-mock__stats">
                            <div class="land-mock__stat land-mock__stat--success">
                                <span class="land-mock__stat-label">Doses taken (30d)</span>
                                <span class="land-mock__stat-value">412</span>
                            </div>
                            <div class="land-mock__stat land-mock__stat--danger">
                                <span class="land-mock__stat-label">Doses missed (30d)</span>
                                <span class="land-mock__stat-value">17</span>
                            </div>
                            <div class="land-mock__stat land-mock__stat--gold">
                                <span class="land-mock__stat-label">Pending</span>
                                <span class="land-mock__stat-value">8</span>
                            </div>
                            <div class="land-mock__stat">
                                <span class="land-mock__stat-label">Adherence rate</span>
                                <span class="land-mock__stat-value">96<span class="land-mock__sym">%</span></span>
                            </div>
                        </div>

                        <div class="land-mock__leader">
                            <div class="land-mock__leader-head">⚠ Patients with missed doses · last 30 days</div>
                            <div class="land-mock__leader-row">
                                <div>
                                    <div class="land-mock__leader-name">Brian K.</div>
                                    <div class="land-mock__leader-meta">Last missed May 18, 14:30</div>
                                </div>
                                <span class="land-mock__leader-count">5</span>
                            </div>
                            <div class="land-mock__leader-row">
                                <div>
                                    <div class="land-mock__leader-name">Faith W.</div>
                                    <div class="land-mock__leader-meta">Last missed May 17, 09:00</div>
                                </div>
                                <span class="land-mock__leader-count">3</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Annotation callouts -->
            <div class="land-anno land-anno--1" data-reveal data-reveal-delay="200">
                <div class="land-anno__line"></div>
                <div class="land-anno__chip">Streak counter</div>
            </div>
            <div class="land-anno land-anno--2" data-reveal data-reveal-delay="320">
                <div class="land-anno__line"></div>
                <div class="land-anno__chip">Auto missed-dose detection</div>
            </div>
            <div class="land-anno land-anno--3" data-reveal data-reveal-delay="440">
                <div class="land-anno__line"></div>
                <div class="land-anno__chip">At-risk patient leaderboard</div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================== -->
<!-- HOW IT WORKS                                                   -->
<!-- ============================================================== -->
<section class="land-how" id="how">
    <div class="land-section-inner">
        <div class="land-section-head">
            <div class="land-eyebrow" data-reveal>How it works</div>
            <h2 class="land-h2" data-reveal>From prescription to confirmation, in <span class="land-italic">three steps.</span></h2>
        </div>

        <ol class="land-how__steps">
            <li class="land-how__step" data-reveal>
                <div class="land-how__num">01</div>
                <h3>Clinic adds the patient</h3>
                <p>
                    An administrator registers the patient and prescribes their medications
                    with frequency, start, and end dates.
                </p>
            </li>
            <li class="land-how__step" data-reveal data-reveal-delay="120">
                <div class="land-how__num">02</div>
                <h3>Patient confirms doses</h3>
                <p>
                    Each day the patient sees their schedule, taps "Confirm taken" after
                    each dose, and watches their streak grow.
                </p>
            </li>
            <li class="land-how__step" data-reveal data-reveal-delay="240">
                <div class="land-how__num">03</div>
                <h3>Cron flags the gaps</h3>
                <p>
                    Four hours past a scheduled dose with no confirmation? The reminder
                    engine auto-marks it missed and notifies the patient.
                </p>
            </li>
        </ol>
    </div>
</section>

<!-- ============================================================== -->
<!-- FOR CLINICS                                                    -->
<!-- ============================================================== -->
<section class="land-clinics" id="clinics">
    <div class="land-section-inner land-clinics__inner">
        <div class="land-clinics__copy">
            <div class="land-eyebrow" data-reveal>For clinics</div>
            <h2 class="land-h2" data-reveal>Designed for the realities of <span class="land-italic">African outpatient care.</span></h2>
            <p data-reveal data-reveal-delay="80">
                <?= e(SITE_NAME) ?> was built with small and medium clinics in Kenya in mind.
                Bandwidth-light, runs on cheap Android, and works the way clinicians
                already think.
            </p>

            <div class="land-clinics__grid">
                <div class="land-clinic-card" data-reveal data-reveal-delay="120">
                    <div class="land-clinic-card__icon">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.55a8 8 0 0114 0"/><path d="M1.42 9a16 16 0 0121.16 0"/><path d="M8.53 16.11a4 4 0 016.95 0"/><circle cx="12" cy="20" r="1"/></svg>
                    </div>
                    <h4>Bandwidth-light</h4>
                    <p>Tiny payloads, no heavy framework. Works on patchy 3G.</p>
                </div>
                <div class="land-clinic-card" data-reveal data-reveal-delay="180">
                    <div class="land-clinic-card__icon">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 7l-10 7L2 7"/></svg>
                    </div>
                    <h4>Email + in-app</h4>
                    <p>Reach patients twice — over SMTP and the in-app inbox.</p>
                </div>
                <div class="land-clinic-card" data-reveal data-reveal-delay="240">
                    <div class="land-clinic-card__icon">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2.5"/><path d="M11 18h2"/></svg>
                    </div>
                    <h4>Mobile-first</h4>
                    <p>Designed for the device 80% of patients will actually open it on.</p>
                </div>
                <div class="land-clinic-card" data-reveal data-reveal-delay="300">
                    <div class="land-clinic-card__icon">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <h4>Africa/Nairobi time</h4>
                    <p>Reminders fire on local clock — not a server in another zone.</p>
                </div>
            </div>
        </div>

        <aside class="land-quote" data-reveal data-reveal-delay="160">
            <div class="land-quote__mark">“</div>
            <p class="land-quote__text">
                Before <?= e(SITE_NAME) ?>, we'd find out a patient had skipped a week of
                doses only at their next visit. Now the system flags it the same day —
                and we can call them.
            </p>
            <div class="land-quote__author">
                <div class="land-quote__avatar">DK</div>
                <div>
                    <div class="land-quote__name">Dr. D. Karanja</div>
                    <div class="land-quote__role">Medical Officer · Nakuru</div>
                </div>
            </div>
        </aside>
    </div>
</section>

<!-- ============================================================== -->
<!-- SECURITY                                                       -->
<!-- ============================================================== -->
<section class="land-security" id="security">
    <div class="land-section-inner">
        <div class="land-section-head">
            <div class="land-eyebrow" data-reveal>Security &amp; privacy</div>
            <h2 class="land-h2" data-reveal>Patient data, treated <span class="land-italic">like patient data.</span></h2>
            <p class="land-section-lead" data-reveal data-reveal-delay="80">
                Sensible defaults baked in from line one. No third-party trackers, no
                external analytics, no surprises.
            </p>
        </div>

        <div class="land-security__grid">
            <div class="land-sec-card" data-reveal>
                <div class="land-sec-card__icon"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg></div>
                <h4>Hashed passwords</h4>
                <p>Bcrypt, salted, never readable — even by us.</p>
            </div>
            <div class="land-sec-card" data-reveal data-reveal-delay="80">
                <div class="land-sec-card__icon"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg></div>
                <h4>CSRF on every form</h4>
                <p>Per-session tokens validated server-side on each POST.</p>
            </div>
            <div class="land-sec-card" data-reveal data-reveal-delay="160">
                <div class="land-sec-card__icon"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="7" r="4"/><path d="M3 21v-2a4 4 0 014-4h4a4 4 0 014 4v2"/><circle cx="17" cy="7" r="3"/><path d="M22 21v-2a3 3 0 00-2-2.83"/></svg></div>
                <h4>Role-based access</h4>
                <p>Admin and patient routes strictly separated by middleware.</p>
            </div>
            <div class="land-sec-card" data-reveal data-reveal-delay="240">
                <div class="land-sec-card__icon"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="14" x2="15" y2="14"/><line x1="9" y1="18" x2="15" y2="18"/></svg></div>
                <h4>Audit-friendly logs</h4>
                <p>Reminder runs and missed-dose flags written to a tamper-visible log.</p>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================== -->
<!-- FINAL CTA                                                      -->
<!-- ============================================================== -->
<section class="land-final">
    <div class="land-section-inner land-final__inner">
        <div class="land-final__bg" aria-hidden="true">
            <div class="land-final__blob"></div>
            <div class="land-final__grid"></div>
        </div>
        <div class="land-final__content" data-reveal>
            <div class="land-eyebrow land-eyebrow--light">Get started</div>
            <h2 class="land-h2 land-h2--light">
                Ready to lift adherence at <span class="land-italic">your clinic?</span>
            </h2>
            <p class="land-final__lead">
                Set up takes under five minutes. Add your first patient, queue their first
                reminder, and watch confirmations roll in.
            </p>
            <div class="land-hero__ctas">
                <a class="land-btn land-btn--accent land-btn--lg" href="register.php">
                    Create an account
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
                </a>
                <a class="land-btn land-btn--ghost-light land-btn--lg" href="login.php">
                    I already have an account
                </a>
            </div>
        </div>
    </div>
</section>

</main>

<!-- ============================================================== -->
<!-- FOOTER                                                         -->
<!-- ============================================================== -->
<footer class="land-foot">
    <div class="land-section-inner land-foot__inner">
        <div class="land-foot__brand-col">
            <div class="land-foot__brand">
                <span class="land-nav__brand-mark"></span>
                <span class="land-nav__brand-name"><?= e(SITE_NAME) ?></span>
            </div>
            <p class="land-foot__tagline"><?= e(SITE_TAGLINE) ?></p>
            <p class="land-foot__credit">Made by <strong>Ndege</strong></p>
        </div>
        <div class="land-foot__col">
            <div class="land-foot__col-title">Product</div>
            <a href="#features">Features</a>
            <a href="#how">How it works</a>
            <a href="#security">Security</a>
        </div>
        <div class="land-foot__col">
            <div class="land-foot__col-title">For clinics</div>
            <a href="#clinics">Why PAAR</a>
            <a href="register.php">Get started</a>
            <a href="login.php">Sign in</a>
        </div>
        <div class="land-foot__col">
            <div class="land-foot__col-title">Company</div>
            <a href="#top">About</a>
            <a href="#security">Privacy</a>
            <a href="mailto:hello@paar.local">Contact</a>
        </div>
    </div>
    <div class="land-foot__bar">
        &copy; <?= $year ?> <?= e(SITE_NAME) ?> · All rights reserved.
    </div>
</footer>

<script src="assets/js/landing.js" defer></script>
</body>
</html>
