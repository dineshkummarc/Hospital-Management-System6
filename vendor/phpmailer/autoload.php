<?php
/**
 * PHPMailer Autoloader
 *
 * ──────────────────────────────────────────────────────────────
 * IMPORTANT: This project ships with STUB PHPMailer classes.
 * For production/real SMTP sending, install the full library:
 *
 *   Option A (Composer — recommended):
 *     cd hospital_management && composer require phpmailer/phpmailer
 *
 *   Option B (Manual):
 *     1. Download from https://github.com/PHPMailer/PHPMailer/releases
 *     2. Replace files in vendor/phpmailer/src/ with the real ones:
 *        - PHPMailer.php
 *        - SMTP.php
 *        - Exception.php
 * ──────────────────────────────────────────────────────────────
 */

spl_autoload_register(function (string $class): void {
    // Map PHPMailer namespace to our vendor directory
    $prefix = 'PHPMailer\\PHPMailer\\';
    $baseDir = __DIR__ . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});
