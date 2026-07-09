<?php
require_once '../config.php';
requireAuth('patient');
$db = getDB();

$ptStmt = $db->prepare("SELECT * FROM patients WHERE user_id=?"); $ptStmt->execute([$_SESSION['user_id']]); $patient = $ptStmt->fetch();
$ptId   = $patient['id'] ?? 0;

// Stats
$upcoming   = $db->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id=? AND appointment_date>=CURDATE() AND status IN ('pending','approved')"); $upcoming->execute([$ptId]);
$totalAppts = $db->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id=?"); $totalAppts->execute([$ptId]);
$rxCount    = $db->prepare("SELECT COUNT(*) FROM prescriptions WHERE patient_id=?"); $rxCount->execute([$ptId]);
$billsPending = $db->prepare("SELECT COUNT(*) FROM billing WHERE patient_id=? AND payment_status='pending'"); $billsPending->execute([$ptId]);

// Recent appointments
$appts = $db->prepare("
    SELECT a.*, du.name AS doctor_name, dep.name AS dept
    FROM appointments a
    JOIN doctors d ON a.doctor_id=d.id
    JOIN users du ON d.user_id=du.id
    JOIN departments dep ON a.department_id=dep.id
    WHERE a.patient_id=? ORDER BY a.appointment_date DESC LIMIT 5
");
$appts->execute([$ptId]); $appts = $appts->fetchAll();

// Recent prescriptions
$rxList = $db->prepare("
    SELECT pr.*, du.name AS doctor_name
    FROM prescriptions pr
    JOIN doctors d ON pr.doctor_id=d.id
    JOIN users du ON d.user_id=du.id
    WHERE pr.patient_id=? ORDER BY pr.created_at DESC LIMIT 3
");
$rxList->execute([$ptId]); $rxList = $rxList->fetchAll();

// Notifications
$notifs = $db->prepare("SELECT * FROM notifications WHERE user_id=? AND is_read=0 ORDER BY created_at DESC LIMIT 5");
$notifs->execute([$_SESSION['user_id']]); $notifs = $notifs->fetchAll();

$pageTitle = 'Patient Dashboard'; $activeMenu = 'dashboard';
include '../includes/layout_header.php';
?>

<!-- Welcome Banner -->
<div class="profile-card mb-4">
  <div style="display:flex;align-items:center;gap:20px;position:relative;z-index:1;flex-wrap:wrap">
    <div class="avatar-placeholder" style="width:68px;height:68px;font-size:1.5rem;flex-shrink:0;background:rgba(255,255,255,.2)">
      <?= strtoupper(substr($_SESSION['name'],0,2)) ?>
    </div>
    <div>
      <h4 style="font-weight:800;margin:0">Hello, <?= e(explode(' ',$_SESSION['name'])[0]) ?>!</h4>
      <p style="opacity:.8;margin:4px 0 0;font-size:.875rem">Your health is our priority. Stay on top of your appointments.</p>
    </div>
    <a href="book_appointment.php" class="btn-primary-custom" style="margin-left:auto;background:rgba(255,255,255,.2);border:1.5px solid rgba(255,255,255,.4);backdrop-filter:blur(6px)">
      <i class="bi bi-calendar-plus"></i> Book Appointment
    </a>
  </div>
</div>

<!-- Stats -->
<div class="row g-4 mb-4">
  <?php foreach([
    ['Upcoming Appointments', $upcoming->fetchColumn(),    'bi-calendar-check', 'blue'],
    ['Total Appointments',    $totalAppts->fetchColumn(),  'bi-calendar3',      'teal'],
    ['Prescriptions',         $rxCount->fetchColumn(),     'bi-file-medical',   'green'],
    ['Pending Bills',         $billsPending->fetchColumn(),'bi-receipt',        'orange'],
  ] as [$lbl,$val,$icon,$col]): ?>
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
  <!-- Recent Appointments -->
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">
        <h5><i class="bi bi-calendar-check me-2 text-primary"></i>Recent Appointments</h5>
        <a href="appointments.php" style="font-size:.82rem;color:var(--primary);text-decoration:none;font-weight:600">View All →</a>
      </div>
      <div class="table-responsive">
        <table class="data-table">
          <thead><tr><th>Doctor</th><th>Department</th><th>Date</th><th>Time</th><th>Status</th></tr></thead>
          <tbody>
          <?php if (empty($appts)): ?>
          <tr><td colspan="5"><div class="empty-state" style="padding:30px">
            <i class="bi bi-calendar-plus"></i>
            <p>No appointments yet. <a href="book_appointment.php" style="color:var(--primary)">Book your first one!</a></p>
          </div></td></tr>
          <?php else: foreach($appts as $a): ?>
          <tr>
            <td><strong><?= e($a['doctor_name']) ?></strong></td>
            <td style="font-size:.82rem;color:#64748B"><?= e($a['dept']) ?></td>
            <td style="font-size:.82rem"><?= date('d M Y',strtotime($a['appointment_date'])) ?></td>
            <td style="font-size:.82rem"><?= date('h:i A',strtotime($a['appointment_time'])) ?></td>
            <td><span class="badge-pill <?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span></td>
          </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Right Column -->
  <div class="col-lg-4 d-flex flex-column gap-4">
    <!-- Recent Prescriptions -->
    <div class="card">
      <div class="card-header">
        <h5><i class="bi bi-file-medical me-2 text-success"></i>Prescriptions</h5>
        <a href="prescriptions.php" style="font-size:.82rem;color:var(--primary);text-decoration:none;font-weight:600">View All →</a>
      </div>
      <div class="card-body" style="padding:0">
        <?php if (empty($rxList)): ?>
        <div class="empty-state" style="padding:24px"><i class="bi bi-file-medical"></i><p>No prescriptions yet.</p></div>
        <?php else: foreach($rxList as $rx): ?>
        <div style="padding:14px 20px;border-bottom:1px solid #F1F5F9">
          <div style="font-weight:600;font-size:.875rem;margin-bottom:2px"><?= e($rx['doctor_name']) ?></div>
          <div style="font-size:.78rem;color:#64748B;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($rx['diagnosis']) ?></div>
          <div style="font-size:.72rem;color:#94A3B8;margin-top:4px"><?= date('d M Y',strtotime($rx['created_at'])) ?></div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- Notifications -->
    <?php if (!empty($notifs)): ?>
    <div class="card">
      <div class="card-header"><h5><i class="bi bi-bell me-2 text-warning"></i>Notifications</h5></div>
      <div class="card-body" style="padding:0">
        <?php foreach($notifs as $n): ?>
        <div style="padding:12px 20px;border-bottom:1px solid #F1F5F9">
          <div style="font-weight:600;font-size:.82rem"><?= e($n['title']) ?></div>
          <div style="font-size:.78rem;color:#64748B"><?= e($n['message']) ?></div>
          <div style="font-size:.72rem;color:#94A3B8;margin-top:3px"><?= timeAgo($n['created_at']) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="card">
      <div class="card-header"><h5><i class="bi bi-lightning me-2 text-accent"></i>Quick Actions</h5></div>
      <div class="card-body" style="display:flex;flex-direction:column;gap:10px">
        <?php foreach([
          ['book_appointment.php','bi-calendar-plus','Book Appointment','blue'],
          ['medical_records.php', 'bi-folder-heart', 'Upload Record',   'green'],
          ['billing.php',         'bi-receipt',      'View Bills',      'orange'],
          ['profile.php',         'bi-person-circle','Edit Profile',    'purple'],
        ] as [$href,$icon,$label,$col]): ?>
        <a href="<?= $href ?>" style="display:flex;align-items:center;gap:10px;padding:12px 14px;border-radius:10px;background:var(--bg);text-decoration:none;color:#0F172A;transition:all .2s;border:1.5px solid #F1F5F9" onmouseover="this.style.background='var(--primary-light)';this.style.borderColor='var(--primary)'" onmouseout="this.style.background='var(--bg)';this.style.borderColor='#F1F5F9'">
          <div style="width:36px;height:36px;border-radius:8px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;color:var(--primary)"><i class="bi <?= $icon ?>"></i></div>
          <span style="font-weight:600;font-size:.875rem"><?= $label ?></span>
          <i class="bi bi-chevron-right" style="margin-left:auto;color:#94A3B8;font-size:.8rem"></i>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
<?php include '../includes/layout_footer.php'; ?>
