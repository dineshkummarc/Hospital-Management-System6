<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
require_once '../config.php';
requireAuth();
ob_clean();
header('Content-Type: application/json');

$id = (int)($_POST['id'] ?? 0);
if (!$id) { echo json_encode(['success'=>false]); exit; }

$db = getDB();
$db->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")
   ->execute([$id, $_SESSION['user_id']]);

echo json_encode(['success'=>true]);
