<?php
require_once '../config.php';
requireAuth('patient');
$db = getDB();

$ptStmt = $db->prepare("SELECT id FROM patients WHERE user_id=?");
$ptStmt->execute([$_SESSION['user_id']]);
$patient = $ptStmt->fetch();
$ptId    = $patient['id'] ?? 0;

$statusFilter = $_GET['status'] ?? '';
$where  = ['b.patient_id=?']; $params = [$ptId];
if ($statusFilter) { $where[] = 'b.payment_status=?'; $params[] = $statusFilter; }

$stmt = $db->prepare("
    SELECT b.*, du.name AS doctor_name, dep.name AS dept, a.appointment_date
    FROM billing b
    JOIN appointments a ON b.appointment_id = a.id
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users du ON d.user_id = du.id
    JOIN departments dep ON a.department_id = dep.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY b.created_at DESC
");
$stmt->execute($params);
$bills = $stmt->fetchAll();

// Summary
$totalPaid    = $db->prepare("SELECT COALESCE(SUM(total_amount),0) FROM billing WHERE patient_id=? AND payment_status='paid'"); $totalPaid->execute([$ptId]);
$totalPending = $db->prepare("SELECT COALESCE(SUM(total_amount),0) FROM billing WHERE patient_id=? AND payment_status='pending'"); $totalPending->execute([$ptId]);

$pageTitle = 'My Bills'; $activeMenu = 'billing';
include '../includes/layout_header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h4 style="font-family:var(--font-display);font-weight:800;margin:0">My Bills</h4>
    <p style="color:#64748B;font-size:.875rem;margin:0"><?= count($bills) ?> invoices</p>
  </div>
</div>

<!-- Summary -->
<div class="row g-4 mb-4">
  <div class="col-md-4">
    <div class="stat-card green">
      <div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
      <div class="stat-value">₹<?= number_format($totalPaid->fetchColumn(), 0) ?></div>
      <div class="stat-label">Total Paid</div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="stat-card orange">
      <div class="stat-icon orange"><i class="bi bi-clock-history"></i></div>
      <div class="stat-value">₹<?= number_format($totalPending->fetchColumn(), 0) ?></div>
      <div class="stat-label">Pending Amount</div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="stat-card blue">
      <div class="stat-icon blue"><i class="bi bi-receipt"></i></div>
      <div class="stat-value" data-count="<?= count($bills) ?>"><?= count($bills) ?></div>
      <div class="stat-label">Total Invoices</div>
    </div>
  </div>
</div>

<!-- Filter -->
<div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
  <?php foreach([''=> 'All','pending'=>'Pending','paid'=>'Paid','partial'=>'Partial','cancelled'=>'Cancelled'] as $val=>$lbl): ?>
  <a href="?status=<?= $val ?>" style="padding:6px 16px;border-radius:20px;border:1.5px solid;text-decoration:none;font-size:.82rem;font-weight:600;transition:all .2s;
    <?= $statusFilter===$val ? 'background:var(--primary);border-color:var(--primary);color:#fff' : 'background:#fff;border-color:#E2E8F0;color:#64748B' ?>">
    <?= $lbl ?>
  </a>
  <?php endforeach; ?>
</div>

<?php if (empty($bills)): ?>
<div class="card">
  <div class="empty-state" style="padding:80px 20px">
    <i class="bi bi-receipt" style="font-size:3.5rem;color:#CBD5E1"></i>
    <p style="margin-top:12px;color:#94A3B8">No bills found.</p>
  </div>
</div>
<?php else: ?>
<div class="row g-4">
  <?php foreach($bills as $bill): ?>
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-body">
        <!-- Invoice Header -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
          <div>
            <div style="font-size:.7rem;color:#94A3B8;font-weight:600;text-transform:uppercase;letter-spacing:.05em">Invoice</div>
            <div style="font-weight:800;color:var(--primary);font-size:1rem"><?= e($bill['invoice_number']) ?></div>
          </div>
          <span class="badge-pill <?= $bill['payment_status'] ?>"><?= ucfirst($bill['payment_status']) ?></span>
        </div>

        <!-- Doctor / Dept -->
        <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:#F8FAFC;border-radius:10px;margin-bottom:14px">
          <div class="avatar-placeholder" style="width:38px;height:38px;font-size:.85rem;flex-shrink:0">
            <?= strtoupper(substr($bill['doctor_name'],0,2)) ?>
          </div>
          <div>
            <div style="font-weight:600;font-size:.875rem"><?= e($bill['doctor_name']) ?></div>
            <div style="font-size:.75rem;color:#64748B"><?= e($bill['dept']) ?> · <?= date('d M Y',strtotime($bill['appointment_date'])) ?></div>
          </div>
        </div>

        <!-- Charge Breakdown -->
        <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:14px;font-size:.82rem">
          <?php
          $charges = ['Consultation'=>$bill['consultation_fee'],'Medicines'=>$bill['medicine_charge'],'Room'=>$bill['room_charge'],'Tests'=>$bill['test_charge'],'Other'=>$bill['other_charge']];
          foreach($charges as $lbl=>$amt): if($amt > 0): ?>
          <div style="display:flex;justify-content:space-between;color:#475569">
            <span><?= $lbl ?></span><span>₹<?= number_format($amt,2) ?></span>
          </div>
          <?php endif; endforeach; ?>
          <?php if($bill['discount'] > 0): ?>
          <div style="display:flex;justify-content:space-between;color:#DC2626"><span>Discount</span><span>-₹<?= number_format($bill['discount'],2) ?></span></div>
          <?php endif; ?>
        </div>

        <!-- Total -->
        <div style="display:flex;justify-content:space-between;align-items:center;padding:12px;background:<?= $bill['payment_status']==='paid'?'#DCFCE7':'#FFFBEB' ?>;border-radius:10px;margin-bottom:14px">
          <span style="font-weight:700">Total Amount</span>
          <span style="font-size:1.15rem;font-weight:800;color:<?= $bill['payment_status']==='paid'?'#166534':'#92400E' ?>">₹<?= number_format($bill['total_amount'],2) ?></span>
        </div>

        <!-- Actions -->
        <div style="display:flex;gap:10px;align-items:center">
          <a href="../admin/print_bill.php?id=<?= $bill['id'] ?>" target="_blank"
             class="btn-primary-custom" style="flex:1;justify-content:center;padding:9px">
            <i class="bi bi-printer"></i> View Invoice
          </a>
          <span style="font-size:.75rem;color:#94A3B8;text-transform:capitalize"><?= e($bill['payment_method']) ?></span>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include '../includes/layout_footer.php'; ?>
