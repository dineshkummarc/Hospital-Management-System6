<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
require_once '../config.php';
requireAuth();
ob_clean();
header('Content-Type: application/json');

$deptId = (int)($_GET['dept_id'] ?? 0);
if (!$deptId) { echo json_encode([]); exit; }

$db   = getDB();
$stmt = $db->prepare("
    SELECT d.id, u.name, d.specialization, d.qualification,
           d.experience_years, d.consultation_fee, d.available_days,
           d.available_from, d.available_to
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    WHERE d.department_id = ? AND d.status = 'active'
    ORDER BY u.name ASC
");
$stmt->execute([$deptId]);
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($doctors);
