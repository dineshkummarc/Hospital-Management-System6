<?php
/**
 * Smart Hospital — Email / Mail Configuration
 * ─────────────────────────────────────────────
 * This file defines mail-related constants and provides
 * a helper to load live SMTP settings from the database.
 *
 * The actual sending logic lives in includes/Mailer.php
 */

// ── Paths ─────────────────────────────────────────────────────
define('PHPMAILER_AUTOLOAD', APP_ROOT . '/vendor/phpmailer/autoload.php');
define('MAIL_LOG_PATH',      APP_ROOT . '/logs/email_errors.log');
define('MAIL_LOG_ENABLED',   true);

// ── Fallback defaults (used if DB has no row yet) ─────────────
define('MAIL_DEFAULT_HOST',        'smtp.gmail.com');
define('MAIL_DEFAULT_PORT',        587);
define('MAIL_DEFAULT_ENCRYPTION',  'tls');
define('MAIL_DEFAULT_USERNAME',    '');
define('MAIL_DEFAULT_PASSWORD',    '');
define('MAIL_DEFAULT_SENDER_NAME', APP_NAME);
define('MAIL_DEFAULT_SENDER_EMAIL','noreply@smarthospital.com');

// ── Feature flags ─────────────────────────────────────────────
define('MAIL_ENABLED',      true);   // Set false to disable all outbound mail
define('MAIL_DEBUG_MODE',   false);  // Set true to log SMTP conversation

// ── Database schema additions (run once in phpMyAdmin / MySQL) ─
/*
SQL to add to hospital.sql:

CREATE TABLE IF NOT EXISTS email_settings (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    smtp_host        VARCHAR(255)  NOT NULL DEFAULT 'smtp.gmail.com',
    smtp_port        INT           NOT NULL DEFAULT 587,
    smtp_username    VARCHAR(255)  NOT NULL DEFAULT '',
    smtp_password    VARCHAR(255)  NOT NULL DEFAULT '',
    smtp_encryption  VARCHAR(20)   NOT NULL DEFAULT 'tls',
    sender_name      VARCHAR(255)  NOT NULL DEFAULT 'Smart Hospital',
    sender_email     VARCHAR(255)  NOT NULL DEFAULT 'noreply@smarthospital.com',
    is_active        TINYINT(1)    NOT NULL DEFAULT 1,
    updated_at       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO email_settings (id, smtp_host, smtp_port, smtp_encryption, sender_name, sender_email)
VALUES (1, 'smtp.gmail.com', 587, 'tls', 'Smart Hospital', 'noreply@smarthospital.com');
*/

/**
 * Load SMTP settings from database, with fallback to constants.
 *
 * @return array
 */
function getMailConfig(): array
{
    static $cfg = null;
    if ($cfg !== null) return $cfg;

    try {
        $db   = getDB();
        $stmt = $db->query("SELECT * FROM email_settings WHERE is_active=1 ORDER BY id ASC LIMIT 1");
        $row  = $stmt ? $stmt->fetch() : null;
    } catch (\Exception $e) {
        $row = null;
    }

    $cfg = [
        'host'         => $row['smtp_host']       ?? MAIL_DEFAULT_HOST,
        'port'         => (int)($row['smtp_port'] ?? MAIL_DEFAULT_PORT),
        'username'     => $row['smtp_username']   ?? MAIL_DEFAULT_USERNAME,
        'password'     => $row['smtp_password']   ?? MAIL_DEFAULT_PASSWORD,
        'encryption'   => $row['smtp_encryption'] ?? MAIL_DEFAULT_ENCRYPTION,
        'sender_name'  => $row['sender_name']     ?? MAIL_DEFAULT_SENDER_NAME,
        'sender_email' => $row['sender_email']    ?? MAIL_DEFAULT_SENDER_EMAIL,
    ];
    return $cfg;
}
