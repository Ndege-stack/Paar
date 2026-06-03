# PAAR — Patient Adherence and Appointment Reminder System

> A web-based system that helps small and medium-sized Kenyan healthcare facilities automate medication reminders, manage appointments, and track patient adherence — built with PHP, MySQL, and vanilla JavaScript.

**Live demo:** https://paarsys.gt.tc/paar &nbsp;|&nbsp; **Final-Year CS Research Project — Kabarak University, 2026**

---

## Features

### Hospital Administrator
- Add patients manually or approve self-registered patients
- Assign medications with frequency: once / twice / three times daily, or weekly
- Schedule and manage appointments
- Monitor adherence per patient or across the entire facility
- Analytics: 14-day adherence trend, appointment attendance breakdown, top medications by missed doses
- Send broadcast in-app notifications to all patients
- Export adherence records as CSV
- Full audit log of all administrative actions

### Patient
- View today's medication schedule with one-tap **Confirm taken**
- View upcoming and past appointments
- Personal adherence history with 14-day chart and 30-day statistics
- Adherence streak counter
- In-app notification inbox
- Automated email and in-app reminders for upcoming doses and appointments

### Reminder Engine (`cron.php`)
- Sends medication reminders for doses due within the next 60 minutes
- Sends appointment reminders for visits within the next 24 hours
- Auto-marks expired doses as missed after a 1-hour grace period
- Logs every action to `logs/reminders.log`

---

## Tech Stack

| Layer       | Technology                         |
|-------------|------------------------------------|
| Backend     | PHP 8+, procedural                 |
| Database    | MySQL 5.7+ / 8.0+ (utf8mb4)        |
| Frontend    | HTML5, CSS3, Vanilla JavaScript    |
| Charts      | Chart.js (CDN)                     |
| Email       | PHPMailer over Gmail SMTP          |
| Reminders   | Cron-compatible `cron.php` script  |

No external APIs. No SMS gateway. No AI features. No framework dependencies.

---

## Folder Structure

```
paar/
├── index.php                  Marketing landing page
├── login.php                  Sign-in (rate-limited, audited)
├── register.php               Patient self-registration
├── forgot_password.php        Request a password-reset link
├── reset_password.php         Set a new password via emailed token
├── logout.php                 Session termination
├── cron.php                   Reminder engine (run hourly)
│
├── config.php                 Safe defaults + override loader (committed)
├── config.local.example.php   Template for real secrets — copy and edit
├── config.local.php           Your real secrets (git-ignored)
├── database.php               PDO connection helper
├── paar_db.sql                Full database schema + seed admin account
├── README.md                  This file
│
├── admin/                     Administrator pages (incl. audit_log.php)
├── patient/                   Patient pages
├── includes/
│   ├── auth_check.php         Session, CSRF, RBAC, helpers
│   ├── security.php           Audit log, login lockout, reset tokens
│   ├── mailer.php             Shared PHPMailer wrapper
│   ├── header.php             <head> + .app wrapper
│   ├── footer.php             Closing tags + global modal mount
│   ├── sidebar_admin.php      Admin navigation
│   └── sidebar_patient.php    Patient navigation
├── assets/
│   ├── css/style.css          Complete custom stylesheet
│   ├── js/main.js             Validation, sortable tables, modal, etc.
│   └── img/                   Logo and screenshots
├── vendor/
│   └── phpmailer/             PHPMailer library
└── logs/                      reminders.log, mailer.log, audit_fallback.log
```

---

## Setup

### Requirements
- PHP **8.0+** with extensions: `pdo_mysql`, `openssl`, `mbstring`
- MySQL **5.7+** or **8.0+**
- Apache (XAMPP / MAMP / LAMP) or PHP's built-in server for local testing
- An SMTP account for outbound email (Gmail App Password, Mailtrap, etc.)

### 1. Install the code

Copy the entire `paar/` folder into your web server's document root.

- **XAMPP (Windows / Linux):** `/path/to/htdocs/paar`
- **MAMP / XAMPP (macOS):** `/Applications/XAMPP/xamppfiles/htdocs/paar`
- **LAMP:** `/var/www/html/paar`

### 2. Create the database

```bash
mysql -u root -p < paar_db.sql
```

This creates the `paar_db` database, all tables, indexes, foreign-key constraints, and a default administrator account:

| Field    | Value              |
|----------|--------------------|
| Email    | `admin@paar.local` |
| Password | `Admin@123`        |

> **Change this password immediately after first login.**

### 3. Create a dedicated MySQL user (production)

```sql
CREATE USER 'paar_app'@'localhost' IDENTIFIED BY 'CHANGE_ME_strong_password';
GRANT SELECT, INSERT, UPDATE, DELETE ON paar_db.* TO 'paar_app'@'localhost';
FLUSH PRIVILEGES;
```

Then update `config.php`:

```php
define('DB_USER', 'paar_app');
define('DB_PASS', 'CHANGE_ME_strong_password');
```

### 4. Configure secrets

`config.php` ships with safe defaults only and is committed to git. Real secrets live in `config.local.php`, which is git-ignored.

```bash
cp config.local.example.php config.local.php
```

Edit `config.local.php` with your actual values:

```php
return [
    'SITE_URL'        => 'https://paar.example.com/paar',
    'DB_HOST'         => 'localhost',
    'DB_PORT'         => 3306,
    'DB_NAME'         => 'paar_db',
    'DB_USER'         => 'paar_app',
    'DB_PASS'         => 'CHANGE_ME',

    'MAIL_HOST'       => 'smtp.gmail.com',
    'MAIL_PORT'       => 587,
    'MAIL_USERNAME'   => 'you@example.com',
    'MAIL_PASSWORD'   => 'your_app_password',
    'MAIL_ENCRYPTION' => 'tls',
    'MAIL_FROM'       => 'no-reply@example.com',
    'MAIL_FROM_NAME'  => 'PAAR Reminders',

    'CRON_TOKEN'      => 'paste_a_strong_random_secret', // openssl rand -hex 32
];
```

`DEBUG` auto-detects: enabled on `localhost`, `127.0.0.1`, `*.local`, `*.test`; disabled everywhere else. Override by setting `'DEBUG' => true|false` in `config.local.php`.

### 5. Install PHPMailer

PHPMailer is required only for outbound email. The system runs without it — in-app notifications still fire, but the email channel is skipped.

**Option A — Manual (no Composer needed):**

Download the latest release from https://github.com/PHPMailer/PHPMailer/releases, then copy these three files from `src/` into `paar/vendor/phpmailer/`:

```
vendor/phpmailer/
├── PHPMailer.php
├── SMTP.php
└── Exception.php
```

**Option B — Composer:**

```bash
cd paar
composer require phpmailer/phpmailer
```

### 6. Gmail App Password

Gmail blocks standard-password SMTP. Generate an App Password at https://myaccount.google.com/apppasswords (requires 2FA). Use the 16-character result as `MAIL_PASSWORD`.

### 7. First login

Open `http://localhost/paar/` in your browser, sign in as admin, and either add a patient manually or approve a self-registered one under **Pending Approvals**.

---

## Configuring the Cron Job

The reminder engine is designed to run **once per hour**.

### Linux / macOS

```bash
crontab -e
```

```cron
0 * * * * /usr/bin/php /var/www/html/paar/cron.php >/dev/null 2>&1
```

### macOS XAMPP

```cron
0 * * * * /Applications/XAMPP/xamppfiles/bin/php /Applications/XAMPP/xamppfiles/htdocs/paar/cron.php >/dev/null 2>&1
```

### Windows (Task Scheduler)

1. **Create Basic Task** — trigger: Daily, repeat every 1 hour for 1 day
2. Action: Start a program
   - Program: `C:\xampp\php\php.exe`
   - Arguments: `C:\xampp\htdocs\paar\cron.php`

### Manual run (development)

```bash
php /path/to/paar/cron.php
```

Or visit `http://localhost/paar/cron.php` directly. Output is also appended to `logs/reminders.log`.

### Securing the endpoint (production)

To restrict `cron.php` to CLI only:

```php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
```

Or require a token in the query string — see the comment block at the bottom of `cron.php`.

---

## Security

| Control | Implementation |
|---|---|
| Bcrypt password hashing | `register.php`, `add_patient.php`, `security.php` |
| `password_verify()` on login | `login.php` |
| Minimum 8-character passwords | `MIN_PASSWORD_LENGTH` constant, enforced client and server |
| Brute-force lockout (5 attempts / 15 min sliding window) | `login.php`, `security.php` |
| Forgot / reset password (single-use, time-bound, hashed tokens) | `forgot_password.php`, `reset_password.php` |
| Audit log | `security.php`, `admin/audit_log.php` |
| PDO prepared statements | Every database query |
| `htmlspecialchars()` output encoding via `e()` helper | `auth_check.php` |
| `session_regenerate_id(true)` on login | `login.php` |
| Hardened session cookie (HttpOnly, SameSite=Lax, Secure on HTTPS) | `auth_check.php` |
| Role-based access control | `require_role()` on every protected page |
| CSRF token on every form | `csrf_field()` / `verify_csrf()` |
| Input validation (email, date, phone, enums) | All POST handlers |
| Secrets isolated in git-ignored `config.local.php` | `config.php` loader |
| Cron HTTP endpoint protected by `CRON_TOKEN` | `cron.php` |

### Production hardening checklist

- Confirm `DEBUG` resolves to `false` on the production host
- Place `config.local.php` outside the docroot if your host allows it
- Use HTTPS (Let's Encrypt or host-managed cert)
- Rotate the seeded admin password
- Use a dedicated MySQL user with DML privileges only (see step 3 above)

---

## End-to-End Smoke Test

1. Import `paar_db.sql`
2. Log in as `admin@paar.local` / `Admin@123`
3. Add a patient using your own email address
4. Assign a once-daily medication starting today
5. Schedule an appointment for tomorrow
6. Log out, log in as the patient
7. Confirm the dashboard shows today's dose with a **Confirm taken** button
8. Run the cron engine:
   ```bash
   php /path/to/paar/cron.php
   ```
9. Check `logs/reminders.log` for queued, sent, and missed counts
10. Verify new entries in the patient's Notifications inbox and an email in their inbox

---

## Troubleshooting

**"Database connection failed"** — verify `DB_*` values in `config.php` and confirm MySQL is running: `mysql -u <DB_USER> -p paar_db`

**"CSRF validation failed"** — the session expired or a stale form was submitted. Re-open the page and try again.

**No emails sending** — check `logs/reminders.log` for `Mail SKIPPED` (PHPMailer not installed) or `Mail ERROR` (SMTP failure). For Gmail, confirm you are using an App Password, not your account password.

**Charts not rendering** — open the browser console. The Chart.js CDN must be reachable. There is no offline fallback.

**Inter font not loading** — the Google Fonts CDN must be reachable. The system falls back to system sans-serif gracefully.

---

## Author

**Philip Ndege** — Final-Year Computer Science, Kabarak University, Kenya, 2026.

Built as a Research Project II submission (COMP 422). Adapted freely for academic and clinical pilot use.
