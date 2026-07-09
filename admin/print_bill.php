<?php
require_once '../config.php';
requireAuth();
$db  = getDB();
$id  = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT b.*, u.name AS patient_name, u.email AS patient_email, u.phone AS patient_phone,
           p.blood_group, p.gender,
           du.name AS doctor_name, dep.name AS dept_name,
           a.appointment_date
    FROM billing b
    JOIN patients p ON b.patient_id=p.id
    JOIN users u ON p.user_id=u.id
    JOIN appointments a ON b.appointment_id=a.id
    JOIN doctors d ON a.doctor_id=d.id
    JOIN users du ON d.user_id=du.id
    JOIN departments dep ON a.department_id=dep.id
    WHERE b.id=?
");
$stmt->execute([$id]);
$bill = $stmt->fetch();
if (!$bill) { echo '<p style="text-align:center;padding:40px">Invoice not found.</p>'; exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Invoice <?= e($bill['invoice_number']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:#f8fafc;color:#1e293b;font-size:14px}
.page{background:#fff;max-width:800px;margin:30px auto;padding:48px;box-shadow:0 4px 20px rgba(0,0,0,.08);border-radius:16px}
.header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:40px;padding-bottom:28px;border-bottom:2px solid #f1f5f9}
.logo{display:flex;align-items:center;gap:12px}
.logo-icon{width:48px;height:48px;background:linear-gradient(135deg,#2563eb,#6366f1);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;color:#fff}
.logo-name{font-size:1.3rem;font-weight:800;color:#0f172a}
.logo-sub{font-size:.75rem;color:#64748b}
.invoice-label{text-align:right}
.invoice-label h2{font-size:1.8rem;font-weight:800;color:#2563eb;letter-spacing:-.02em}
.invoice-label p{font-size:.82rem;color:#64748b;margin-top:4px}
.badge-paid{display:inline-block;background:#dcfce7;color:#166534;padding:4px 14px;border-radius:20px;font-size:.75rem;font-weight:700;margin-top:8px}
.badge-pending{display:inline-block;background:#fef9c3;color:#854d0e;padding:4px 14px;border-radius:20px;font-size:.75rem;font-weight:700;margin-top:8px}
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:32px;margin-bottom:36px}
.info-block h4{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#94a3b8;margin-bottom:10px}
.info-block p{font-size:.875rem;margin-bottom:4px;color:#334155}
.info-block strong{color:#0f172a}
table{width:100%;border-collapse:collapse;margin-bottom:28px}
thead tr{background:#f8fafc}
thead th{padding:10px 14px;text-align:left;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#475569;border-bottom:2px solid #e2e8f0}
tbody td{padding:12px 14px;border-bottom:1px solid #f1f5f9;font-size:.875rem;color:#334155}
.totals{width:300px;margin-left:auto}
.totals-row{display:flex;justify-content:space-between;padding:8px 0;font-size:.875rem;border-bottom:1px solid #f8fafc}
.totals-row.total{font-size:1rem;font-weight:800;color:#0f172a;border-top:2px solid #e2e8f0;padding-top:12px;margin-top:4px;border-bottom:none}
.footer-note{text-align:center;padding-top:28px;border-top:1px solid #f1f5f9;font-size:.78rem;color:#94a3b8}
.print-btn{display:block;width:120px;margin:20px auto;padding:10px;background:#2563eb;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:.875rem;font-weight:600;text-align:center}
@media print{.print-btn{display:none}body{background:#fff}.page{box-shadow:none;margin:0;border-radius:0}}
</style>
</head>
<body>
<button class="print-btn" onclick="window.print()">🖨 Print</button>
<div class="page" id="invoice-print">
  <div class="header">
    <div class="logo">
      <div class="logo-icon">🏥</div>
      <div>
        <div class="logo-name">Smart Hospital</div>
        <div class="logo-sub">123 Hospital Road, Raipur, CG 492001</div>
        <div class="logo-sub">Phone: +91 98765 43210 | info@smarthospital.com</div>
      </div>
    </div>
    <div class="invoice-label">
      <h2>INVOICE</h2>
      <p><strong><?= e($bill['invoice_number']) ?></strong></p>
      <p>Date: <?= date('d M Y', strtotime($bill['created_at'])) ?></p>
      <?php if($bill['payment_status']==='paid'): ?>
        <span class="badge-paid">✓ PAID</span>
      <?php else: ?>
        <span class="badge-pending">⏳ <?= strtoupper($bill['payment_status']) ?></span>
      <?php endif; ?>
    </div>
  </div>

  <div class="info-grid">
    <div class="info-block">
      <h4>Bill To</h4>
      <p><strong><?= e($bill['patient_name']) ?></strong></p>
      <p><?= e($bill['patient_email']) ?></p>
      <p><?= e($bill['patient_phone'] ?? '') ?></p>
      <?php if($bill['blood_group']): ?><p>Blood Group: <?= e($bill['blood_group']) ?></p><?php endif; ?>
    </div>
    <div class="info-block">
      <h4>Appointment Details</h4>
      <p><strong>Doctor:</strong> <?= e($bill['doctor_name']) ?></p>
      <p><strong>Department:</strong> <?= e($bill['dept_name']) ?></p>
      <p><strong>Appt Date:</strong> <?= date('d M Y', strtotime($bill['appointment_date'])) ?></p>
      <p><strong>Payment:</strong> <?= ucfirst($bill['payment_method']) ?></p>
    </div>
  </div>

  <table>
    <thead>
      <tr><th>Description</th><th style="text-align:right">Amount (₹)</th></tr>
    </thead>
    <tbody>
      <?php
      $charges = [
        'Consultation Fee'  => $bill['consultation_fee'],
        'Medicine Charges'  => $bill['medicine_charge'],
        'Room Charges'      => $bill['room_charge'],
        'Lab / Test Charges'=> $bill['test_charge'],
        'Other Charges'     => $bill['other_charge'],
      ];
      foreach($charges as $lbl => $amt):
        if($amt > 0):
      ?>
      <tr><td><?= $lbl ?></td><td style="text-align:right">₹<?= number_format($amt,2) ?></td></tr>
      <?php endif; endforeach; ?>
    </tbody>
  </table>

  <div class="totals">
    <?php
    $subtotal = $bill['consultation_fee']+$bill['medicine_charge']+$bill['room_charge']+$bill['test_charge']+$bill['other_charge'];
    ?>
    <div class="totals-row"><span>Subtotal</span><span>₹<?= number_format($subtotal,2) ?></span></div>
    <?php if($bill['discount']>0): ?>
    <div class="totals-row" style="color:#dc2626"><span>Discount</span><span>-₹<?= number_format($bill['discount'],2) ?></span></div>
    <?php endif; ?>
    <?php if($bill['tax']>0): ?>
    <div class="totals-row"><span>Tax (<?= $bill['tax'] ?>%)</span><span>₹<?= number_format($subtotal*$bill['tax']/100,2) ?></span></div>
    <?php endif; ?>
    <div class="totals-row total"><span>Total Amount</span><span>₹<?= number_format($bill['total_amount'],2) ?></span></div>
  </div>

  <?php if($bill['notes']): ?>
  <div style="margin-top:24px;padding:14px;background:#f8fafc;border-radius:10px;font-size:.82rem;color:#475569">
    <strong>Notes:</strong> <?= e($bill['notes']) ?>
  </div>
  <?php endif; ?>

  <div class="footer-note">
    <p>Thank you for choosing Smart Hospital. We wish you good health!</p>
    <p style="margin-top:6px">This is a computer generated invoice. For queries contact billing@smarthospital.com</p>
  </div>
</div>
</body>
</html>
