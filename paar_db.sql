-- =====================================================================
-- PAAR (Patient Adherence and Appointment Reminder) System
-- Database Schema
-- Target: MySQL 5.7+ / 8.0+
-- Charset: utf8mb4 (full Unicode support, including emoji and Swahili)
-- =====================================================================

-- ---------------------------------------------------------------------
-- Create database
-- ---------------------------------------------------------------------


-- ---------------------------------------------------------------------
-- Drop tables in reverse FK dependency order so the script is re-runnable
-- during development. Comment out in production.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS reminders;
DROP TABLE IF EXISTS adherence;
DROP TABLE IF EXISTS appointments;
DROP TABLE IF EXISTS medications;
DROP TABLE IF EXISTS patients;
DROP TABLE IF EXISTS users;

-- ---------------------------------------------------------------------
-- USERS table
-- Stores both administrators and patients. The `role` column distinguishes
-- the two. Self-registered patients start with status='pending' and must
-- be approved by an administrator before they can log in.
-- ---------------------------------------------------------------------
CREATE TABLE users (
    user_id     INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    email       VARCHAR(100) UNIQUE NOT NULL,
    password    VARCHAR(255) NOT NULL,                   -- bcrypt hash
    role        ENUM('admin', 'patient') NOT NULL,
    status      ENUM('active', 'pending', 'suspended') DEFAULT 'pending',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_users_role   (role),
    INDEX idx_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- PATIENTS table
-- Extended profile attached one-to-one to a row in `users` (where role='patient').
-- ---------------------------------------------------------------------
CREATE TABLE patients (
    patient_id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id             INT NOT NULL,
    date_of_birth       DATE,
    gender              ENUM('male', 'female', 'other'),
    phone               VARCHAR(20),
    address             TEXT,
    emergency_contact   VARCHAR(100),
    emergency_phone     VARCHAR(20),
    UNIQUE KEY uq_patients_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- MEDICATIONS table
-- A medication assigned to a patient by an administrator.
-- ---------------------------------------------------------------------
CREATE TABLE medications (
    medication_id    INT AUTO_INCREMENT PRIMARY KEY,
    patient_id       INT NOT NULL,
    medication_name  VARCHAR(150) NOT NULL,
    dosage           VARCHAR(100) NOT NULL,
    frequency        ENUM('once_daily','twice_daily','three_times_daily','weekly') NOT NULL,
    start_date       DATE NOT NULL,
    end_date         DATE NOT NULL,
    notes            TEXT,
    assigned_by      INT NOT NULL,                       -- user_id of assigning admin
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_meds_patient (patient_id),
    INDEX idx_meds_dates   (start_date, end_date),
    FOREIGN KEY (patient_id)  REFERENCES patients(patient_id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- APPOINTMENTS table
-- ---------------------------------------------------------------------
CREATE TABLE appointments (
    appointment_id   INT AUTO_INCREMENT PRIMARY KEY,
    patient_id       INT NOT NULL,
    appointment_date DATETIME NOT NULL,
    doctor_name      VARCHAR(100) NOT NULL,
    department       VARCHAR(100) NOT NULL,
    reason           TEXT,
    status           ENUM('scheduled','completed','missed','cancelled') DEFAULT 'scheduled',
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_appt_patient (patient_id),
    INDEX idx_appt_date    (appointment_date),
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- ADHERENCE table
-- One row per scheduled dose. Status moves from 'pending' -> 'taken' or 'missed'.
-- ---------------------------------------------------------------------
CREATE TABLE adherence (
    adherence_id        INT AUTO_INCREMENT PRIMARY KEY,
    medication_id       INT NOT NULL,
    patient_id          INT NOT NULL,
    slot_idx            TINYINT NULL,            -- index into slots_for_medication()
    confirmation_time   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status              ENUM('taken','missed','pending') DEFAULT 'pending',
    notes               TEXT,
    INDEX idx_adh_patient  (patient_id),
    INDEX idx_adh_med      (medication_id),
    INDEX idx_adh_status   (status),
    INDEX idx_adh_slot     (medication_id, patient_id, slot_idx),
    INDEX idx_adh_pt_time  (patient_id, confirmation_time),  -- covers date-range queries
    FOREIGN KEY (medication_id) REFERENCES medications(medication_id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id)    REFERENCES patients(patient_id)       ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- REMINDERS table
-- A queue of reminder events to be processed by cron.php.
-- reference_id points to either medications.medication_id or appointments.appointment_id
-- depending on the value of reminder_type.
-- ---------------------------------------------------------------------
CREATE TABLE reminders (
    reminder_id     INT AUTO_INCREMENT PRIMARY KEY,
    patient_id      INT NOT NULL,
    reference_id    INT NOT NULL,
    reminder_type   ENUM('medication','appointment') NOT NULL,
    reminder_time   DATETIME NOT NULL,
    sent_status     ENUM('pending','sent','failed') DEFAULT 'pending',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rem_status (sent_status),
    INDEX idx_rem_time   (reminder_time),
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- NOTIFICATIONS table (in-app inbox)
-- ---------------------------------------------------------------------
CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    message         TEXT NOT NULL,
    is_read         TINYINT(1) DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notif_user (user_id, is_read),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- LOGIN_ATTEMPTS — feeds the brute-force lockout in login.php
-- ---------------------------------------------------------------------
CREATE TABLE login_attempts (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    ip            VARCHAR(45)  NOT NULL,
    email         VARCHAR(190) NOT NULL,
    success       TINYINT(1)   NOT NULL DEFAULT 0,
    attempted_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_la_ip_time    (ip, attempted_at),
    INDEX idx_la_email_time (email, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- PASSWORD_RESETS — single-use tokens for forgot-password flow
-- ---------------------------------------------------------------------
CREATE TABLE password_resets (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT          NOT NULL,
    token_hash  CHAR(64)     NOT NULL,
    expires_at  DATETIME     NOT NULL,
    used_at     DATETIME     NULL,
    ip          VARCHAR(45)  NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pr_token (token_hash),
    INDEX idx_pr_user (user_id, used_at),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- AUDIT_LOG — append-only trail of who did what, when, from where
-- ---------------------------------------------------------------------
CREATE TABLE audit_log (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    actor_user_id   INT          NULL,
    actor_role      VARCHAR(20)  NULL,
    action          VARCHAR(64)  NOT NULL,
    entity          VARCHAR(64)  NULL,
    entity_id       INT          NULL,
    meta_json       TEXT         NULL,
    ip              VARCHAR(45)  NULL,
    user_agent      VARCHAR(255) NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_actor   (actor_user_id, created_at),
    INDEX idx_audit_action  (action, created_at),
    INDEX idx_audit_entity  (entity, entity_id),
    INDEX idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- SEED DATA
-- ---------------------------------------------------------------------
-- Default administrator account.
-- Email:    admin@paar.local
-- Password: Admin@123
-- The hash below was generated with PHP password_hash('Admin@123', PASSWORD_BCRYPT).
-- CHANGE THIS PASSWORD IMMEDIATELY AFTER FIRST LOGIN.
-- =====================================================================
INSERT INTO users (name, email, password, role, status) VALUES
('System Administrator',
 'admin@paar.local',
 '$2b$10$549OxZmA9KlXGS8CJTOe5O2iKJs/IcrD/xaHANHxQo.66yNaqk2Yq',
 'admin',
 'active');
-- The hash above was generated for the password 'Admin@123' using bcrypt cost 10.
-- PHP's password_verify() accepts both $2y$ and $2b$ bcrypt prefixes, so this works.
-- If you ever need to regenerate it, run:
--   php -r "echo password_hash('NewPassword', PASSWORD_BCRYPT);"
