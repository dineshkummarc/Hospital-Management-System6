<?php
/**
 * ajax/send_test_email.php
 * ─────────────────────────────────────────────────────────────
 * Always returns JSON. Never leaks PHP warnings as HTML.
 * Called by admin/email_settings.php via fetch()
 */

// ── 1. Silence PHP and buffer output FIRST ────────────────────
error_reporting(0);
ini_set('display_errors', '0');
ob_start();

// ── 2. Bootstrap ──────────────────────────────────────────────
require_once dirname(__DIR__) . '/config.php';

// ── 3. Discard anything config/session may have emitted ───────
ob_clean();

// ── 4. Force JSON content type ────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
// Prevent caching
header('Cache-Control: no-store');

// ─────────────────────────────────────────────────────────────
// Helper: send JSON and exit
// ─────────────────────────────────────────────────────────────
function respond(bool $ok, string $msg, array $extra = []): void
{
    ob_clean();
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
    exit;
}

// ── 5. Method check ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'POST method required.');
}

// ── 6. Auth check ─────────────────────────────────────────────
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    respond(false, 'Unauthorized. Please log in as admin.');
}

// ── 7. CSRF check ─────────────────────────────────────────────
$receivedToken = trim($_POST['csrf_token'] ?? '');
$sessionToken  = $_SESSION['csrf_token']   ?? '';

if (empty($sessionToken)) {
    respond(false, 'Session expired. Please refresh the page and try again.');
}
if (!hash_equals($sessionToken, $receivedToken)) {
    respond(false, 'Security token mismatch. Refresh the page and try again.');
}

// ── 8. Validate recipient ─────────────────────────────────────
$testTo = trim($_POST['test_to'] ?? '');
if (empty($testTo)) {
    respond(false, 'Please enter a recipient email address.');
}
if (!filter_var($testTo, FILTER_VALIDATE_EMAIL)) {
    respond(false, "Invalid email address: " . htmlspecialchars($testTo, ENT_QUOTES));
}

// ── 9. Load mail config ───────────────────────────────────────
$mailConfigPath = APP_ROOT . '/config/mail_config.php';
if (!file_exists($mailConfigPath)) {
    respond(false, 'mail_config.php not found. Check file: config/mail_config.php');
}
require_once $mailConfigPath;
$cfg = getMailConfig();

// ── 10. Check credentials are saved ──────────────────────────
$missing = [];
if (empty($cfg['host']))     $missing[] = 'SMTP Host';
if (empty($cfg['username'])) $missing[] = 'SMTP Username';
if (empty($cfg['password'])) $missing[] = 'SMTP Password';

if (!empty($missing)) {
    respond(false,
        'Missing required settings: ' . implode(', ', $missing) . '. '
        . 'Fill in all SMTP fields and click "Save Settings" first.',
        ['fields_missing' => $missing]
    );
}

// ── 11. Load Mailer ───────────────────────────────────────────
$mailerPath = APP_ROOT . '/includes/Mailer.php';
if (!file_exists($mailerPath)) {
    respond(false, 'Mailer.php not found. Check file: includes/Mailer.php');
}
require_once $mailerPath;

// ── 12. Build and send ────────────────────────────────────────
$subject   = '✅ Smart Hospital — SMTP Configuration Test';
$html      = mailTemplateTest($testTo);
$errorText = sendEmailDiagnostic($testTo, $subject, $html);

if ($errorText === '') {
    // ── SUCCESS ───────────────────────────────────────────────
    respond(true,
        "Test email sent to <strong>" . htmlspecialchars($testTo, ENT_QUOTES) . "</strong>! "
        . "Check your inbox (and spam folder).",
        [
            'config' => [
                'host' => $cfg['host'],
                'port' => $cfg['port'],
                'enc'  => strtoupper($cfg['encryption'] ?? 'tls'),
            ],
        ]
    );
}

// ── 13. FAILURE — build diagnostic ───────────────────────────
$hint = _buildHint($errorText, $cfg);

// Last log line
$lastLog = '';
$logFile = APP_ROOT . '/logs/email_errors.log';
if (file_exists($logFile) && filesize($logFile) > 0) {
    $lines   = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $lastLog = $lines ? end($lines) : '';
}

respond(false, $errorText, [
    'hint'     => $hint,
    'last_log' => $lastLog,
    'config'   => [
        'host' => $cfg['host'],
        'port' => $cfg['port'],
        'enc'  => strtoupper($cfg['encryption'] ?? 'tls'),
        'user' => $cfg['username'],
    ],
]);

// ─────────────────────────────────────────────────────────────
// Helper: pick human-readable fix hint
// ─────────────────────────────────────────────────────────────
function _buildHint(string $error, array $cfg): string
{
    $e = strtolower($error);

    if (str_contains($e, 'stub') || str_contains($e, 'mail() returned false')) {
        return 'The project is using the PHPMailer stub. '
             . 'For real SMTP sending, install the full library: '
             . 'run <code>composer require phpmailer/phpmailer</code> '
             . 'inside the hospital_management folder, then try again.';
    }
    if (str_contains($e, '535') || str_contains($e, 'authentication') || str_contains($e, 'username and password')) {
        $extra = ($cfg['host'] === 'smtp.gmail.com')
            ? ' For Gmail: use an App Password (NOT your Gmail password). '
            . 'Enable 2FA → myaccount.google.com/apppasswords → generate one.'
            : ' Check your SMTP username and password are correct.';
        return 'Authentication failed.' . $extra;
    }
    if (str_contains($e, 'could not connect') || str_contains($e, 'connection refused')
        || str_contains($e, 'connection timed out') || str_contains($e, 'network is unreachable')) {
        return "Cannot connect to {$cfg['host']}:{$cfg['port']}. "
             . "Check: (1) SMTP host and port are correct, "
             . "(2) your firewall/ISP doesn't block port {$cfg['port']}, "
             . "(3) try port 465 with SSL encryption instead.";
    }
    if (str_contains($e, 'tls') || str_contains($e, 'ssl') || str_contains($e, 'certificate')) {
        return "Encryption error. Try switching between TLS (port 587) and SSL (port 465). "
             . "For local development, SSL certificate verification is already disabled.";
    }
    if (str_contains($e, '550') || str_contains($e, '553') || str_contains($e, 'relay')) {
        return "The server rejected the recipient or sender address. "
             . "Make sure the Sender Email in settings matches your SMTP account.";
    }
    if (str_contains($e, 'starttls') || str_contains($e, 'must issue')) {
        return "The server requires STARTTLS. Set Encryption to TLS and Port to 587.";
    }
    return 'Check that your SMTP host, port, username and password are all correct, '
         . 'then try again. See logs/email_errors.log for details.';
}
