<?php
/**
 * =====================================================================
 * PAAR — Local environment overrides (TEMPLATE)
 * ---------------------------------------------------------------------
 * HOW TO USE
 *   1. Copy this file to `config.local.php` (same directory).
 *   2. Fill in real values for your local OR production environment.
 *   3. `config.local.php` is git-ignored — it must NEVER be committed.
 *
 * `config.php` will load `config.local.php` if it exists, and any value
 * here OVERRIDES the safe defaults in `config.php`. You can also set
 * any of these via real environment variables (which win over this file).
 *
 * GENERATE A STRONG CRON_TOKEN
 *   openssl rand -hex 32
 *
 * =====================================================================
 */

return [
    /* ---- Site identity --------------------------------------------- */
    // Local example:    'http://localhost:8888/paar'
    // Production:       'https://paar.xo.je/paar'
    'SITE_URL'        => 'http://localhost:8888/paar',

    /* ---- Database (MySQL via PDO) ---------------------------------- */
    'DB_HOST'         => 'localhost',
    'DB_PORT'         => 8889,                 // MAMP=8889, standard=3306
    'DB_NAME'         => 'paar_db',
    'DB_USER'         => 'root',
    'DB_PASS'         => 'root',

    /* ---- Mail (PHPMailer / SMTP) ----------------------------------- */
    // For Gmail use an App Password: https://myaccount.google.com/apppasswords
    'MAIL_HOST'       => 'smtp.gmail.com',
    'MAIL_PORT'       => 587,
    'MAIL_USERNAME'   => 'your_email@gmail.com',
    'MAIL_PASSWORD'   => 'your_app_password_here',
    'MAIL_ENCRYPTION' => 'tls',
    'MAIL_FROM'       => 'no-reply@paar.local',
    'MAIL_FROM_NAME'  => 'PAAR Reminders',

    /* ---- Cron shared secret ---------------------------------------- */
    // REPLACE this with output of:  openssl rand -hex 32
    'CRON_TOKEN'      => 'REPLACE_ME_WITH_openssl_rand_hex_32',

    /* ---- Debug ----------------------------------------------------- */
    // Leave this key OUT for auto-detect (true on localhost, false in prod).
    // Or set explicitly:
    // 'DEBUG' => false,
];
