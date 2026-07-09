<?php
require_once '../config.php';
requireAuth('doctor');
$db = getDB();

// Get doctor record
$docStmt = $db->prepare("SELECT d.*, dep.name AS dept_name FROM doctors d JOIN departments dep ON d.department_id=dep.id WHERE d.user_id=?");
$docStmt->execute([$_SESSION['user_id']]);
$doctor = $docStmt->fetch();
$docId  = $doctor['id'] ?? 0;

// Stats
$todayPts   = $db->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id=? AND appointment_date=CURDATE() AND status IN ('approved','completed')"); $todayPts->execute([$docId]);
$pendingApts = $db->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id=? AND status='pending'"); $pendingApts->execute([$docId]);
$completedApts = $db->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id=? AND status='completed'"); $completedApts->execute([$docId]);
$totalPrescriptions = $db->prepare("SELECT COUNT(*) FROM prescriptions WHERE doctor_id=?"); $totalPrescriptions->execute([$docId]);

// Today's appointments
$todayAppts = $db->prepare("
    SELECT a.*, u.name AS patient_name, u.phone AS patient_phone, p.blood_group, p.gender
    FROM appointments a
    JOIN patients p ON a.patient_id=p.id
    JOIN users u ON p.user_id=u.id
    WHERE a.doctor_id=? AND a.appointment_date=CURDATE() AND a.status IN ('approved','pending')
    ORDER BY a.appointment_time ASC
");
$todayAppts->execute([$docId]);
$todayAppts = $todayAppts->fetchAll();

// Pending appointments
$pendingList = $db->prepare("
    SELECT a.*, u.name AS patient_name
    FROM appointments a
    JOIN patients p ON a.patient_id=p.id
    JOIN users u ON p.user_id=u.id
    WHERE a.doctor_id=? AND a.status='pending'
    ORDER BY a.appointment_date ASC LIMIT 5
");
$pendingList->execute([$docId]);
$pendingList = $pendingList->fetchAll();

$pageTitle = 'Doctor Dashboard'; $activeMenu = 'dashboard';
include '../includes/layout_header.php';
?>

<!-- Doctor Profile Banner -->
<div class="profile-card mb-4">
  <div style="display:flex;align-items:center;gap:20px;position:relative;z-index:1">
    <div class="avatar-placeholder" style="width:72px;height:72px;font-size:1.6rem;flex-shrink:0;background:rgba(255,255,255,.2)">
      <?= strtoupper(substr($_SESSION['name'],0,2)) ?>
    </div>
    <div>
      <h4 style="font-weight:800;margin:0">Welcome back, <?= e($_SESSION['name']) ?>!</h4>
      <p style="opacity:.8;margin:4px 0 0;font-size:.9rem"><?= e($doctor['specialization']??'') ?> · <?= e($doctor['dept_name']??'') ?></p>
    </div>
    <div style="margin-left:auto;text-align:right">
      <div style="font-size:.75rem;opacity:.7"><?= date('l, d M Y') ?></div>
      <div style="font-size:1.1rem;font-weight:700"><?= date('h:i A') ?></div>
    </div>
  </div>
</div>

<!-- Stats -->
<div class="row g-4 mb-4">
  <?php foreach([
    ["Today's Patients",    $todayPts->fetchColumn(),         'bi-people',         'blue'],
    ['Pending Appointments',$pendingApts->fetchColumn(),      'bi-clock',          'orange'],
    ['Completed',          $completedApts->fetchColumn(),     'bi-check-circle',   'green'],
    ['Prescriptions',      $totalPrescriptions->fetchColumn(),'bi-file-medical',   'purple'],
  ] as [$lbl,$val,$icon,$col]):
  ?>
  <div class="col-md-3 col-6">
    <div class="stat-card <?= $col ?>">
      <div class="stat-icon <?= $col ?>"><i class="bi <?= $icon ?>"></i></div>
      <div class="stat-value" data-count="<?= (int)$val ?>"><?= (int)$val ?></div>
      <div class="stat-label"><?= $lbl ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="row g-4">
  <!-- Today's Schedule -->
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">
        <h5><i class="bi bi-calendar-day me-2 text-primary"></i>Today's Schedule</h5>
        <a href="appointments.php" style="font-size:.82rem;color:var(--primary);text-decoration:none;font-weight:600">View All →</a>
      </div>
      <?php if (empty($todayAppts)): ?>
      <div class="empty-state"><i class="bi bi-calendar-x"></i><p>No appointments scheduled for today.</p></div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="data-table">
          <thead><tr><th>Time</th><th>Patient</th><th>Contact</th><th>Blood</th><th>Status</th><th>Action</th></tr></thead>
          <tbody>
          <?php foreach($todayAppts as $a): ?>
          <tr>
            <td style="font-weight:700;color:var(--primary)"><?= date('h:i A',strtotime($a['appointment_time'])) ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:8px">
                <div class="avatar-placeholder" style="width:34px;height:34px;font-size:.78rem;flex-shrink:0">
                  <?= strtoupper(substr($a['patient_name'],0,2)) ?>
                </div>
                <span style="font-weight:600"><?= e($a['patient_name']) ?></span>
              </div>
            </td>
            <td style="font-size:.8rem;color:#64748B"><?= e($a['patient_phone']??'—') ?></td>
            <td><?= $a['blood_group']?"<span style='background:#FEE2E2;color:#DC2626;font-size:.72rem;font-weight:700;padding:2px 8px;border-radius:6px'>".e($a['blood_group'])."</span>":'—' ?></td>
            <td><span class="badge-pill <?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span></td>
            <td>
              <a href="prescriptions.php?appt_id=<?= $a['id'] ?>&patient_id=<?= $a['patient_id'] ?>" class="btn-sm-action success" title="Write Prescription">
                <i class="bi bi-pencil-square"></i>
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Pending Requests -->
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">
        <h5><i class="bi bi-hourglass-split me-2 text-warning"></i>Pending Requests</h5>
      </div>
      <div class="card-body" style="padding:0">
        <?php if (empty($pendingList)): ?>
        <div class="empty-state" style="padding:30px"><i class="bi bi-check-all"></i><p>No pending requests.</p></div>
        <?php else: foreach($pendingList as $a): ?>
        <div style="display:flex;align-items:center;gap:12px;padding:14px 20px;border-bottom:1px solid #F1F5F9">
          <div class="avatar-placeholder" style="width:38px;height:38px;font-size:.85rem;flex-shrink:0">
            <?= strtoupper(substr($a['patient_name'],0,2)) ?>
          </div>
          <div style="flex:1;min-width:0">
            <div style="font-weight:600;font-size:.875rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($a['patient_name']) ?></div>
            <div style="font-size:.75rem;color:#94A3B8"><?= date('d M Y',strtotime($a['appointment_date'])) ?></div>
          </div>
          <div style="display:flex;gap:4px">
            <button class="btn-sm-action success" title="Approve" onclick="quickAction(<?= $a['id'] ?>,'approved')"><i class="bi bi-check-lg"></i></button>
            <button class="btn-sm-action danger" title="Reject" onclick="quickAction(<?= $a['id'] ?>,'rejected')"><i class="bi bi-x-lg"></i></button>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
async function quickAction(id, status) {
  const res = await ajaxPost('../admin/appointments.php', { action:'update_status', id, status });
  if (res.success) { showToast('Updated', `Appointment ${status}.`, 'success'); setTimeout(()=>location.reload(),800); }
  else showToast('Error', res.message, 'error');
}
</script>
<?php include '../includes/layout_footer.php'; ?>
