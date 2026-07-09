-- ================================================================
-- Smart Hospital Management System
-- Email Notification System — Database Schema Addition
-- ================================================================
-- Run this file in phpMyAdmin OR append to hospital.sql
-- It is safe to run multiple times (uses IF NOT EXISTS / IGNORE)
-- ================================================================

USE hospital_management;

-- ── email_settings table ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS email_settings (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    smtp_host        VARCHAR(255)  NOT NULL DEFAULT 'smtp.gmail.com',
    smtp_port        INT           NOT NULL DEFAULT 587,
    smtp_username    VARCHAR(255)  NOT NULL DEFAULT '',
    smtp_password    VARCHAR(255)  NOT NULL DEFAULT '',
    smtp_encryption  VARCHAR(20)   NOT NULL DEFAULT 'tls'
                         COMMENT 'tls | ssl | none',
    sender_name      VARCHAR(255)  NOT NULL DEFAULT 'Smart Hospital',
    sender_email     VARCHAR(255)  NOT NULL DEFAULT 'noreply@smarthospital.com',
    is_active        TINYINT(1)    NOT NULL DEFAULT 1,
    created_at       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
                         ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Seed one default row (id=1) ───────────────────────────────
INSERT IGNORE INTO email_settings
    (id, smtp_host, smtp_port, smtp_encryption, sender_name, sender_email)
VALUES
    (1, 'smtp.gmail.com', 587, 'tls', 'Smart Hospital', 'noreply@smarthospital.com');

-- ── Ensure notifications table has all required columns ───────
-- (already created by hospital.sql but add safety ALTERs)
ALTER TABLE notifications
    MODIFY COLUMN type ENUM('appointment','billing','prescription','system','alert')
    NOT NULL DEFAULT 'system';

-- ── email_logs table (optional detailed send log) ─────────────
CREATE TABLE IF NOT EXISTS email_logs (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    recipient     VARCHAR(255) NOT NULL,
    subject       VARCHAR(500) NOT NULL,
    event_type    VARCHAR(100) NOT NULL DEFAULT 'system',
    status        ENUM('sent','failed') NOT NULL DEFAULT 'sent',
    error_message TEXT,
    sent_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_recipient (recipient),
    INDEX idx_event     (event_type),
    INDEX idx_status    (status),
    INDEX idx_sent_at   (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
