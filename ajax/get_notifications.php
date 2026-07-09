<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
require_once '../config.php';
requireAuth();
ob_clean();
header('Content-Type: application/json');

$db = getDB();
$stmt = $db->prepare("
    SELECT id, title, message, type, is_read, created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id']]);
$notifications = $stmt->fetchAll();

// Add time_ago to each
foreach ($notifications as &$n) {
    $n['time_ago'] = timeAgo($n['created_at']);
}

$unread_count = 0;

foreach ($notifications as $n) {
    if (empty($n['is_read'])) {
        $unread_count++;
    }
}

echo json_encode([
    'success'       => true,
    'notifications' => $notifications,
    'unread_count'  => $unread_count
]);
