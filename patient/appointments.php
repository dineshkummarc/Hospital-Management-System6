<?php
require_once '../config.php';
requireAuth('patient');
$db = getDB();

$ptStmt = $db->prepare("SELECT id FROM patients WHERE user_id=?"); $ptStmt->execute([$_SESSION['user_id']]); $patient=$ptStmt->fetch();
$ptId   = $patient['id'] ?? 0;

// AJAX: cancel
if (!empty($_POST['action']) && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    if ($_POST['action']==='cancel') {
        $id = (int)$_POST['id'];
        $check = $db->prepare("SELECT id FROM appointments WHERE id=? AND patient_id=? AND status IN ('pending','approved')");
        $check->execute([$id,$ptId]);
        if ($check->fetch()) {
            $db->prepare("UPDATE appointments SET status='cancelled', updated_at=NOW() WHERE id=?")->execute([$id]);
            jsonResponse(true, 'Appointment cancelled.');
        }
        jsonResponse(false, 'Cannot cancel this appointment.');
    }
}

$statusFilter = $_GET['status'] ?? '';
$where  = ['a.patient_id=?']; $params = [$ptId];
if ($statusFilter) { $where[] = 'a.status=?'; $params[] = $statusFilter; }

$stmt = $db->prepare("
    SELECT a.*, du.name AS doctor_name, d.specialization, d.consultation_fee, dep.name AS dept
    FROM appointments a
    JOIN doctors d ON a.doctor_id=d.id
    JOIN users du ON d.user_id=du.id
    JOIN departments dep ON a.department_id=dep.id
    WHERE ".implode(' AND ',$where)."
    ORDER BY a.appointment_date DESC
");
$stmt->execute($params); $appointments = $stmt->fetchAll();

$pageTitle = 'My Appointments'; $activeMenu = 'appointments';
include '../includes/layout_header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
  <div>
    <h4 style="font-family:var(--font-display);font-weight:800;margin:0">My Appointments</h4>
    <p style="color:#64748B;font-size:.875rem;margin:0"><?= count($appointments) ?> appointments</p>
  </div>
  <a href="book_appointment.php" class="btn-primary-custom"><i class="bi bi-calendar-plus"></i> Book New</a>
</div>

<!-- Filter Pills -->
<div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
  <?php foreach([''=> 'All','pending'=>'Pending','approved'=>'Approved','completed'=>'Completed','cancelled'=>'Cancelled'] as $val=>$lbl): ?>
  <a href="?status=<?= $val ?>" style="padding:6px 16px;border-radius:20px;border:1.5px solid;text-decoration:none;font-size:.82rem;font-weight:600;transition:all .2s;
    <?= $statusFilter===$val ? 'background:var(--primary);border-color:var(--primary);color:#fff' : 'background:#fff;border-color:#E2E8F0;color:#64748B' ?>">
    <?= $lbl ?>
  </a>
  <?php endforeach; ?>
</div>

<?php if (empty($appointments)): ?>
<div class="card"><div class="empty-state" style="padding:60px">
  <i class="bi bi-calendar-x"></i>
  <p>No appointments found. <a href="book_appointment.php" style="color:var(--primary)">Book one now!</a></p>
</div></div>
<?php else: ?>
<div class="row g-4">
<?php foreach($appointments as $a):
  $isPast   = strtotime($a['appointment_date']) < strtotime('today');
  $canCancel = in_array($a['status'],['pending','approved']) && !$isPast;
?>
<div class="col-lg-6" id="appt-card-<?= $a['id'] ?>">
  <div class="card">
    <div class="card-body">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:16px">
        <div style="display:flex;align-items:center;gap:12px">
          <div class="avatar-placeholder" style="width:48px;height:48px;font-size:1rem;flex-shrink:0">
            <?= strtoupper(substr($a['doctor_name'],0,2)) ?>
          </div>
          <div>
            <div style="font-weight:700"><?= e($a['doctor_name']) ?></div>
            <div style="font-size:.78rem;color:var(--primary);font-weight:500"><?= e($a['specialization']) ?></div>
            <div style="font-size:.75rem;color:#94A3B8"><?= e($a['dept']) ?></div>
          </div>
        </div>
        <span class="badge-pill <?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span>
      </div>
      <div class="divider" style="margin:12px 0"></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:.82rem;margin-bottom:14px">
        <div><i class="bi bi-calendar3 me-2" style="color:#94A3B8"></i><strong><?= date('d M Y',strtotime($a['appointment_date'])) ?></strong></div>
        <div><i class="bi bi-clock me-2" style="color:#94A3B8"></i><?= date('h:i A',strtotime($a['appointment_time'])) ?></div>
        <div><i class="bi bi-hash me-2" style="color:#94A3B8"></i>Token #<?= $a['token_number']??'—' ?></div>
        <div><i class="bi bi-currency-rupee me-2" style="color:#94A3B8"></i>₹<?= number_format($a['consultation_fee'],0) ?></div>
      </div>
      <?php if ($a['reason']): ?>
      <div style="background:#F8FAFC;border-radius:8px;padding:10px 12px;font-size:.8rem;color:#475569;margin-bottom:14px">
        <i class="bi bi-chat-text me-1"></i><?= e($a['reason']) ?>
      </div>
      <?php endif; ?>
      <?php if ($canCancel): ?>
      <button class="btn-sm-action danger" style="width:auto;padding:6px 14px;font-size:.8rem;font-weight:600" onclick="cancelAppt(<?= $a['id'] ?>)">
        <i class="bi bi-x-circle me-1"></i>Cancel
      </button>
      <?php elseif ($a['status']==='completed'): ?>
      <a href="prescriptions.php" style="display:inline-flex;align-items:center;gap:6px;color:var(--success);font-size:.8rem;font-weight:600;text-decoration:none">
        <i class="bi bi-file-medical"></i> View Prescription
      </a>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<script>
async function cancelAppt(id) {
  confirmAction('Are you sure you want to cancel this appointment?', async () => {
    const res = await ajaxPost('appointments.php', { action:'cancel', id });
    if (res.success) {
      const card = document.getElementById('appt-card-'+id);
      card.querySelector('.badge-pill').textContent = 'Cancelled';
      card.querySelector('.badge-pill').className   = 'badge-pill cancelled';
      card.querySelector('[onclick]')?.remove();
      showToast('Cancelled', res.message, 'info');
    } else showToast('Error', res.message, 'error');
  });
}
</script>
<?php include '../includes/layout_footer.php'; ?>
