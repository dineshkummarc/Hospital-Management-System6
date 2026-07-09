<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
/**
 * ajax/get_email_log.php
 * Returns the last N lines of the email error log (admin only).
 */
require_once '../config.php';
requireAuth('admin');
ob_clean();
header('Content-Type: application/json');

$logFile = APP_ROOT . '/logs/email_errors.log';
$lines   = [];
$total   = 0;

if (file_exists($logFile) && filesize($logFile) > 0) {
    $all   = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $total = count($all);
    $limit = min((int)($_GET['limit'] ?? 20), 100);
    $lines = array_slice(array_reverse($all), 0, $limit);
}

echo json_encode([
    'success' => true,
    'total'   => $total,
    'lines'   => $lines,
]);
