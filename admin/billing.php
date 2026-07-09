<?php
require_once '../config.php';
requireAuth('admin');
$db = getDB();

// AJAX: update payment status
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action'])
) {
    if ($_POST['action'] === 'update_payment') {
        $id = (int)$_POST['id']; $status = $_POST['payment_status'];
        $valid = ['pending','paid','partial','cancelled'];
        if (in_array($status, $valid)) {
            $db->prepare("UPDATE billing SET payment_status=? WHERE id=?")->execute([$status,$id]);
            // ── Fire payment-confirmed notifications if status is 'paid' ──
            if ($status === 'paid') notifyPaymentCompleted($id);
            jsonResponse(true, 'Payment status updated.', ['status'=>$status]);
        }
        jsonResponse(false, 'Invalid status.');
    }
    if ($_POST['action'] === 'add_bill') {
        $apptId  = (int)$_POST['appointment_id'];
        $ptId    = (int)$_POST['patient_id'];
        $consult = (float)$_POST['consultation_fee'];
        $meds    = (float)$_POST['medicine_charge'];
        $room    = (float)$_POST['room_charge'];
        $test    = (float)$_POST['test_charge'];
        $other   = (float)$_POST['other_charge'];
        $disc    = (float)$_POST['discount'];
        $tax     = (float)$_POST['tax'];
        $method  = $_POST['payment_method'] ?? 'cash';
        $notes   = trim($_POST['notes'] ?? '');
        $subtotal = $consult + $meds + $room + $test + $other;
        $total    = max(0, $subtotal - $disc + ($subtotal * $tax / 100));
        $invoice  = generateInvoice();
        $db->prepare("INSERT INTO billing (appointment_id,patient_id,consultation_fee,medicine_charge,room_charge,test_charge,other_charge,discount,tax,total_amount,payment_method,invoice_number,notes)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
           ->execute([$apptId,$ptId,$consult,$meds,$room,$test,$other,$disc,$tax,$total,$method,$invoice,$notes]);
        $newBillId = (int)$db->lastInsertId();
        // ── Fire email + in-app notifications ─────────────────
        notifyBillingCreated($newBillId);
        jsonResponse(true, 'Bill created.', ['invoice'=>$invoice,'total'=>$total]);
    }
}

// Filters
$status = $_GET['status'] ?? '';
$where  = ['1=1']; $params = [];
if ($status) { $where[] = 'b.payment_status=?'; $params[] = $status; }

$stmt = $db->prepare("
    SELECT b.*, u.name AS patient_name, a.appointment_date
    FROM billing b
    JOIN patients p ON b.patient_id=p.id
    JOIN users u ON p.user_id=u.id
    LEFT JOIN appointments a ON b.appointment_id=a.id
    WHERE ".implode(' AND ',$where)."
    ORDER BY b.created_at DESC
");
$stmt->execute($params);
$bills = $stmt->fetchAll();

// For new bill form
$completedAppts = $db->query("
    SELECT a.id, u.name AS patient_name, p.id AS patient_id, a.appointment_date
    FROM appointments a
    JOIN patients p ON a.patient_id=p.id
    JOIN users u ON p.user_id=u.id
    WHERE a.status='completed'
    AND a.id NOT IN (SELECT appointment_id FROM billing)
    ORDER BY a.appointment_date DESC
    LIMIT 50
")->fetchAll();

$totalRevenue = $db->query("SELECT COALESCE(SUM(total_amount),0) FROM billing WHERE payment_status='paid'")->fetchColumn();
$pendingAmt   = $db->query("SELECT COALESCE(SUM(total_amount),0) FROM billing WHERE payment_status='pending'")->fetchColumn();

$pageTitle = 'Billing'; $activeMenu = 'billing';
include '../includes/layout_header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
  <div>
    <h4 style="font-family:var(--font-display);font-weight:800;margin:0">Billing Management</h4>
    <p style="color:#64748B;font-size:.875rem;margin:0"><?= count($bills) ?> invoices</p>
  </div>
  <button class="btn-primary-custom" onclick="openModal('add-bill-modal')">
    <i class="bi bi-plus-circle"></i> Create Invoice
  </button>
</div>

<!-- Summary Cards -->
<div class="row g-4 mb-4">
  <div class="col-md-4">
    <div class="stat-card green">
      <div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
      <div class="stat-value">₹<?= number_format($totalRevenue) ?></div>
      <div class="stat-label">Total Revenue Collected</div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="stat-card orange">
      <div class="stat-icon orange"><i class="bi bi-clock-history"></i></div>
      <div class="stat-value">₹<?= number_format($pendingAmt) ?></div>
      <div class="stat-label">Pending Payments</div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="stat-card blue">
      <div class="stat-icon blue"><i class="bi bi-receipt"></i></div>
      <div class="stat-value" data-count="<?= count($bills) ?>">0</div>
      <div class="stat-label">Total Invoices</div>
    </div>
  </div>
</div>

<!-- Filter -->
<div class="card mb-4">
  <div class="card-body" style="padding:14px 20px">
    <form method="GET" class="d-flex gap-3 align-items-end flex-wrap">
      <div>
        <label class="form-label" style="font-size:.8rem">Payment Status</label>
        <select name="status" class="form-control-custom" style="width:180px">
          <option value="">All</option>
          <?php foreach(['pending','paid','partial','cancelled'] as $s): ?>
          <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn-primary-custom"><i class="bi bi-filter"></i> Filter</button>
      <a href="billing.php" class="btn-outline-custom">Clear</a>
    </form>
  </div>
</div>

<!-- Table -->
<div class="card">
  <div class="table-responsive">
    <table class="data-table">
      <thead><tr>
        <th>Invoice</th><th>Patient</th><th>Date</th><th>Consultation</th>
        <th>Medicines</th><th>Total</th><th>Method</th><th>Status</th><th>Actions</th>
      </tr></thead>
      <tbody>
      <?php if (empty($bills)): ?>
      <tr><td colspan="9"><div class="empty-state"><i class="bi bi-receipt"></i><p>No bills generated yet.</p></div></td></tr>
      <?php else: foreach($bills as $bill): ?>
      <tr>
        <td style="font-weight:600;font-size:.82rem;color:var(--primary)"><?= e($bill['invoice_number']) ?></td>
        <td><?= e($bill['patient_name']) ?></td>
        <td style="font-size:.82rem"><?= date('d M Y', strtotime($bill['created_at'])) ?></td>
        <td>₹<?= number_format($bill['consultation_fee'],2) ?></td>
        <td>₹<?= number_format($bill['medicine_charge'],2) ?></td>
        <td style="font-weight:700">₹<?= number_format($bill['total_amount'],2) ?></td>
        <td><span style="font-size:.8rem;text-transform:capitalize"><?= e($bill['payment_method']) ?></span></td>
        <td>
          <select class="form-control-custom" style="padding:4px 10px;font-size:.78rem;width:120px"
                  onchange="updatePayment(<?= $bill['id'] ?>, this.value)">
            <?php foreach(['pending','paid','partial','cancelled'] as $s): ?>
            <option value="<?= $s ?>" <?= $bill['payment_status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
        </td>
        <td>
          <a href="print_bill.php?id=<?= $bill['id'] ?>" target="_blank" class="btn-sm-action" title="Print"><i class="bi bi-printer"></i></a>
        </td>
      </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Create Bill Modal -->
<div class="modal-custom" id="add-bill-modal">
  <div class="modal-box" style="max-width:600px">
    <div class="modal-header-custom">
      <h5><i class="bi bi-receipt me-2 text-primary"></i>Create Invoice</h5>
      <button class="modal-close" onclick="closeModal('add-bill-modal')">&times;</button>
    </div>
    <div class="modal-body-custom">
      <div class="form-group">
        <label class="form-label">Appointment (Completed) *</label>
        <select id="appt-select" class="form-control-custom" onchange="fillPatient(this)">
          <option value="">Select Appointment</option>
          <?php foreach($completedAppts as $a): ?>
          <option value="<?= $a['id'] ?>" data-patient="<?= $a['patient_id'] ?>">
            #<?= $a['id'] ?> — <?= e($a['patient_name']) ?> (<?= date('d M Y',strtotime($a['appointment_date'])) ?>)
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <input type="hidden" id="bill-patient-id">
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Consultation Fee (₹)</label><input type="number" id="b-consult" class="form-control-custom" value="500" min="0" step="50" oninput="calcTotal()"></div>
        <div class="col-md-6"><label class="form-label">Medicine Charge (₹)</label><input type="number" id="b-meds" class="form-control-custom" value="0" min="0" oninput="calcTotal()"></div>
        <div class="col-md-6"><label class="form-label">Room Charge (₹)</label><input type="number" id="b-room" class="form-control-custom" value="0" min="0" oninput="calcTotal()"></div>
        <div class="col-md-6"><label class="form-label">Lab/Test Charge (₹)</label><input type="number" id="b-test" class="form-control-custom" value="0" min="0" oninput="calcTotal()"></div>
        <div class="col-md-4"><label class="form-label">Other (₹)</label><input type="number" id="b-other" class="form-control-custom" value="0" min="0" oninput="calcTotal()"></div>
        <div class="col-md-4"><label class="form-label">Discount (₹)</label><input type="number" id="b-disc" class="form-control-custom" value="0" min="0" oninput="calcTotal()"></div>
        <div class="col-md-4"><label class="form-label">Tax (%)</label><input type="number" id="b-tax" class="form-control-custom" value="0" min="0" max="30" oninput="calcTotal()"></div>
        <div class="col-md-6">
          <label class="form-label">Payment Method</label>
          <select id="b-method" class="form-control-custom">
            <?php foreach(['cash','card','upi','insurance','online'] as $m): ?>
            <option value="<?= $m ?>"><?= ucfirst($m) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Total Amount</label>
          <div id="total-display" style="padding:10px 14px;background:var(--primary-light);border-radius:10px;font-size:1.1rem;font-weight:800;color:var(--primary)">₹500.00</div>
        </div>
        <div class="col-12"><label class="form-label">Notes</label><textarea id="b-notes" class="form-control-custom" rows="2"></textarea></div>
      </div>
    </div>
    <div class="modal-footer-custom">
      <button class="btn-outline-custom" onclick="closeModal('add-bill-modal')">Cancel</button>
      <button class="btn-primary-custom" onclick="createBill(this)"><i class="bi bi-receipt"></i> Generate Invoice</button>
    </div>
  </div>
</div>

<script>
function fillPatient(sel) {
  const opt = sel.options[sel.selectedIndex];
  document.getElementById('bill-patient-id').value = opt.dataset.patient || '';
}
function calcTotal() {
  const c = parseFloat(document.getElementById('b-consult').value)||0;
  const m = parseFloat(document.getElementById('b-meds').value)||0;
  const r = parseFloat(document.getElementById('b-room').value)||0;
  const t = parseFloat(document.getElementById('b-test').value)||0;
  const o = parseFloat(document.getElementById('b-other').value)||0;
  const d = parseFloat(document.getElementById('b-disc').value)||0;
  const tax = parseFloat(document.getElementById('b-tax').value)||0;
  const sub = c+m+r+t+o;
  const total = Math.max(0, sub - d + (sub * tax/100));
  document.getElementById('total-display').textContent = '₹' + total.toFixed(2);
}
async function updatePayment(id, status) {
  const res = await ajaxPost('billing.php', { action:'update_payment', id, payment_status:status });
  showToast(res.success ? 'Updated' : 'Error', res.message, res.success ? 'success' : 'error');
}
async function createBill(btn) {
  const apptId = document.getElementById('appt-select').value;
  const ptId   = document.getElementById('bill-patient-id').value;
  if (!apptId || !ptId) { showToast('Error','Select an appointment.','error'); return; }
  const data = {
    action:'add_bill', appointment_id:apptId, patient_id:ptId,
    consultation_fee: document.getElementById('b-consult').value,
    medicine_charge:  document.getElementById('b-meds').value,
    room_charge:      document.getElementById('b-room').value,
    test_charge:      document.getElementById('b-test').value,
    other_charge:     document.getElementById('b-other').value,
    discount:         document.getElementById('b-disc').value,
    tax:              document.getElementById('b-tax').value,
    payment_method:   document.getElementById('b-method').value,
    notes:            document.getElementById('b-notes').value,
  };
  const res = await ajaxPost('billing.php', data, btn);
  if (res.success) { showToast('Created', `Invoice ${res.invoice} generated (₹${res.total}).`, 'success'); closeModal('add-bill-modal'); setTimeout(()=>location.reload(),1000); }
  else showToast('Error', res.message, 'error');
}
</script>
<?php include '../includes/layout_footer.php'; ?>
