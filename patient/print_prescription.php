<?php
require_once '../config.php';
requireAuth();
$db  = getDB();
$id  = (int)($_GET['id'] ?? 0);

// Fetch prescription – patients can only see their own; doctors/admins see all
$ptId = null;
if ($_SESSION['role'] === 'patient') {
    $pt = $db->prepare("SELECT id FROM patients WHERE user_id=?"); $pt->execute([$_SESSION['user_id']]); $pt=$pt->fetch();
    $ptId = $pt['id'] ?? 0;
}

$q = "SELECT pr.*, pu.name AS patient_name, pu.email AS patient_email, pu.phone AS patient_phone,
             du.name AS doctor_name, d.specialization, d.qualification, d.license_number,
             dep.name AS dept, a.appointment_date
      FROM prescriptions pr
      JOIN patients p ON pr.patient_id=p.id JOIN users pu ON p.user_id=pu.id
      JOIN doctors d ON pr.doctor_id=d.id JOIN users du ON d.user_id=du.id
      JOIN departments dep ON d.department_id=dep.id
      JOIN appointments a ON pr.appointment_id=a.id
      WHERE pr.id=?";
if ($ptId) $q .= " AND pr.patient_id=$ptId";

$stmt = $db->prepare($q); $stmt->execute([$id]);
$rx = $stmt->fetch();
if (!$rx) { echo '<p style="text-align:center;padding:60px;color:#EF4444">Prescription not found or access denied.</p>'; exit; }

$medicines = [];
try { $medicines = json_decode($rx['medicines'] ?? '[]', true) ?: []; } catch(Exception $e){}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Prescription — Smart Hospital</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:#f8fafc;color:#1e293b;font-size:14px}
.page{background:#fff;max-width:780px;margin:30px auto;padding:44px;box-shadow:0 4px 24px rgba(0,0,0,.08);border-radius:16px}
.header{display:flex;justify-content:space-between;align-items:flex-start;padding-bottom:24px;margin-bottom:28px;border-bottom:2px solid #2563eb}
.logo-name{font-size:1.4rem;font-weight:800;color:#0f172a}
.logo-sub{font-size:.78rem;color:#64748b;margin-top:2px}
.rx-title{text-align:right}
.rx-title h2{font-size:1.5rem;font-weight:800;color:#2563eb}
.rx-symbol{font-size:3rem;color:#2563eb;line-height:1;margin-bottom:4px}
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:28px;padding:20px;background:#f8fafc;border-radius:12px}
.info-label{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#94a3b8;margin-bottom:4px}
.info-value{font-size:.9rem;font-weight:600;color:#0f172a}
.section-title{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#94a3b8;margin-bottom:12px;padding-bottom:6px;border-bottom:1px solid #f1f5f9}
.diagnosis-box{background:#eff6ff;border-left:4px solid #2563eb;padding:14px 16px;border-radius:0 10px 10px 0;margin-bottom:24px}
.diagnosis-text{font-size:1rem;font-weight:700;color:#1e40af}
table{width:100%;border-collapse:collapse;margin-bottom:24px}
thead tr{background:#f8fafc}
thead th{padding:10px 14px;text-align:left;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#475569;border-bottom:2px solid #e2e8f0}
tbody td{padding:10px 14px;border-bottom:1px solid #f1f5f9;font-size:.875rem}
tbody tr:last-child td{border:none}
.advice-box{background:#f0fdfa;border:1px solid #99f6e4;border-radius:10px;padding:14px 16px;margin-bottom:24px;font-size:.875rem;color:#0f766e;line-height:1.6}
.followup-box{background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:12px 16px;display:inline-flex;align-items:center;gap:8px;font-size:.875rem;font-weight:600;color:#92400e;margin-bottom:28px}
.footer{margin-top:40px;padding-top:24px;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:flex-end}
.sig-line{width:180px;border-top:1.5px solid #334155;padding-top:8px;text-align:center;font-size:.78rem;color:#475569}
.watermark{font-size:.72rem;color:#94a3b8;text-align:center;margin-top:20px}
.print-btn{display:block;width:130px;margin:20px auto;padding:11px;background:#2563eb;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:.875rem;font-weight:600;text-align:center}
@media print{.print-btn{display:none}body{background:#fff}.page{box-shadow:none;margin:0;border-radius:0;padding:32px}}
</style>
</head>
<body>
<button class="print-btn" onclick="window.print()">🖨 Print / Save PDF</button>
<div class="page">
  <!-- Header -->
  <div class="header">
    <div>
      <div class="logo-name">🏥 Smart Hospital</div>
      <div class="logo-sub">123 Hospital Road, Raipur, CG 492001</div>
      <div class="logo-sub">Phone: +91 98765 43210 | www.smarthospital.com</div>
    </div>
    <div class="rx-title">
      <div class="rx-symbol">℞</div>
      <h2>PRESCRIPTION</h2>
      <div style="font-size:.78rem;color:#64748b;margin-top:4px">Date: <?= date('d M Y', strtotime($rx['created_at'])) ?></div>
    </div>
  </div>

  <!-- Patient & Doctor Info -->
  <div class="info-grid">
    <div>
      <div class="info-label">Patient Name</div>
      <div class="info-value"><?= e($rx['patient_name']) ?></div>
    </div>
    <div>
      <div class="info-label">Doctor</div>
      <div class="info-value"><?= e($rx['doctor_name']) ?></div>
    </div>
    <div>
      <div class="info-label">Contact</div>
      <div class="info-value"><?= e($rx['patient_phone'] ?? $rx['patient_email']) ?></div>
    </div>
    <div>
      <div class="info-label">Specialization</div>
      <div class="info-value"><?= e($rx['specialization']) ?> · <?= e($rx['dept']) ?></div>
    </div>
    <div>
      <div class="info-label">Appointment Date</div>
      <div class="info-value"><?= date('d M Y', strtotime($rx['appointment_date'])) ?></div>
    </div>
    <?php if($rx['license_number']): ?>
    <div>
      <div class="info-label">License No.</div>
      <div class="info-value"><?= e($rx['license_number']) ?></div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Diagnosis -->
  <div class="section-title">Diagnosis</div>
  <div class="diagnosis-box">
    <div class="diagnosis-text"><?= e($rx['diagnosis']) ?></div>
  </div>

  <!-- Medicines -->
  <?php if (!empty($medicines)): ?>
  <div class="section-title">Prescribed Medicines</div>
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Medicine Name</th>
        <th>Dosage</th>
        <th>Duration</th>
        <th>Instructions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($medicines as $i=>$m): ?>
      <tr>
        <td style="color:#94a3b8"><?= $i+1 ?></td>
        <td style="font-weight:600"><?= e($m['name'] ?? '') ?></td>
        <td><?= e($m['dosage'] ?? '—') ?></td>
        <td><?= e($m['duration'] ?? '—') ?></td>
        <td style="color:#475569"><?= e($m['instructions'] ?? '—') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <!-- Advice -->
  <?php if ($rx['advice']): ?>
  <div class="section-title">Medical Advice</div>
  <div class="advice-box"><?= nl2br(e($rx['advice'])) ?></div>
  <?php endif; ?>

  <!-- Follow Up -->
  <?php if ($rx['follow_up_date']): ?>
  <div class="followup-box">
    📅 Follow-up Appointment: <strong><?= date('d M Y', strtotime($rx['follow_up_date'])) ?></strong>
  </div>
  <?php endif; ?>

  <!-- Footer / Signature -->
  <div class="footer">
    <div>
      <div style="font-size:.78rem;color:#94a3b8;margin-bottom:20px">This prescription is valid for 30 days from the date of issue.</div>
      <div class="sig-line">
        <?= e($rx['doctor_name']) ?><br>
        <span style="color:#94a3b8"><?= e($rx['specialization']) ?></span>
      </div>
    </div>
    <div style="text-align:right;font-size:.75rem;color:#94a3b8">
      <div>Smart Hospital Management System</div>
      <div>Generated: <?= date('d M Y, h:i A') ?></div>
    </div>
  </div>

  <div class="watermark">This is a computer-generated prescription. For any queries, contact us at info@smarthospital.com</div>
</div>
</body>
</html>
