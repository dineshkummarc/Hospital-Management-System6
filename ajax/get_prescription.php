<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
require_once '../config.php';
requireAuth();
ob_clean();
header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);
if (!$id) { echo json_encode(['success'=>false,'message'=>'Invalid ID']); exit; }

$db = getDB();

// Role-based access
$extra = '';
if ($_SESSION['role'] === 'patient') {
    $pt = $db->prepare("SELECT id FROM patients WHERE user_id=?"); $pt->execute([$_SESSION['user_id']]); $pt=$pt->fetch();
    if ($pt) $extra = " AND pr.patient_id=" . (int)$pt['id'];
} elseif ($_SESSION['role'] === 'doctor') {
    $doc = $db->prepare("SELECT id FROM doctors WHERE user_id=?"); $doc->execute([$_SESSION['user_id']]); $doc=$doc->fetch();
    if ($doc) $extra = " AND pr.doctor_id=" . (int)$doc['id'];
}

$stmt = $db->prepare("
    SELECT pr.*,
           pu.name AS patient_name,
           du.name AS doctor_name,
           DATE_FORMAT(pr.created_at,'%d %b %Y') AS created_at,
           DATE_FORMAT(pr.follow_up_date,'%d %b %Y') AS follow_up_date
    FROM prescriptions pr
    JOIN patients p ON pr.patient_id=p.id JOIN users pu ON p.user_id=pu.id
    JOIN doctors d ON pr.doctor_id=d.id JOIN users du ON d.user_id=du.id
    WHERE pr.id=? $extra
    LIMIT 1
");
$stmt->execute([$id]);
$rx = $stmt->fetch();

if (!$rx) {
    echo json_encode(['success'=>false,'message'=>'Not found or access denied.']);
    exit;
}

echo json_encode(['success'=>true, 'prescription'=>$rx]);
