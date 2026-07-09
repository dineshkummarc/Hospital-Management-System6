<?php
require_once '../config.php';
requireAuth('admin');

$db = getDB();

// Dashboard Statistics
$stats = [];
try {
    $stats['patients']     = $db->query("SELECT COUNT(*) FROM patients")->fetchColumn();
    $stats['doctors']      = $db->query("SELECT COUNT(*) FROM doctors WHERE status='active'")->fetchColumn();
    $stats['appointments'] = $db->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
    $stats['revenue']      = $db->query("SELECT COALESCE(SUM(total_amount),0) FROM billing WHERE payment_status='paid'")->fetchColumn();
    $stats['today']        = $db->query("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE()")->fetchColumn();
    $stats['pending']      = $db->query("SELECT COUNT(*) FROM appointments WHERE status='pending'")->fetchColumn();

    // Recent appointments
    $recentAppts = $db->query("
        SELECT a.*, u.name AS patient_name, du.name AS doctor_name, dep.name AS dept
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN users u ON p.user_id = u.id
        JOIN doctors d ON a.doctor_id = d.id
        JOIN users du ON d.user_id = du.id
        JOIN departments dep ON a.department_id = dep.id
        ORDER BY a.created_at DESC LIMIT 10
    ")->fetchAll();

    // Monthly chart data (last 6 months)
    $chartData = $db->query("
        SELECT DATE_FORMAT(appointment_date,'%b %Y') AS month,
               COUNT(*) AS total
        FROM appointments
        WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(appointment_date,'%Y-%m')
        ORDER BY appointment_date ASC
    ")->fetchAll();

    // Revenue chart
    $revenueData = $db->query("
        SELECT DATE_FORMAT(created_at,'%b %Y') AS month,
               COALESCE(SUM(total_amount),0) AS amount
        FROM billing
        WHERE payment_status='paid' AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at,'%Y-%m')
        ORDER BY created_at ASC
    ")->fetchAll();

    // Recent patients
    $recentPatients = $db->query("
        SELECT u.name, u.email, u.phone, p.blood_group, p.gender, u.created_at
        FROM patients p JOIN users u ON p.user_id = u.id
        ORDER BY u.created_at DESC LIMIT 5
    ")->fetchAll();
} catch (PDOException $e) {
    $stats = array_fill_keys(['patients','doctors','appointments','revenue','today','pending'], 0);
    $recentAppts = $recentPatients = $chartData = $revenueData = [];
}

$pageTitle  = 'Admin Dashboard';
$activeMenu = 'dashboard';
include '../includes/layout_header.php';
?>

<!-- Stats Row -->
<div class="row g-4 mb-4">
  <?php
  $cards = [
    ['Total Patients',      $stats['patients'],     'bi-people',         'blue',   '+12% this month'],
    ['Active Doctors',      $stats['doctors'],      'bi-person-badge',   'green',  'All departments'],
    ['Total Appointments',  $stats['appointments'], 'bi-calendar-check', 'teal',   'All time'],
    ['Total Revenue',       '₹'.number_format($stats['revenue']), 'bi-currency-rupee', 'purple', 'From consultations'],
    ['Today\'s Appointments', $stats['today'],      'bi-calendar-day',   'orange', 'Scheduled today'],
  ];
  foreach($cards as [$label, $value, $icon, $color, $sub]):
  ?>
  <div class="col-xl-2half col-lg-4 col-md-6" style="--bs-gutter-x:1rem">
    <div class="stat-card <?= $color ?>">
      <div class="stat-icon <?= $color ?>"><i class="bi <?= $icon ?>"></i></div>
      <div class="stat-value" <?= is_numeric($value) ? "data-count=\"$value\"" : "" ?>><?= e($value) ?></div>
      <div class="stat-label"><?= e($label) ?></div>
      <div class="stat-change up"><i class="bi bi-arrow-up-short"></i><?= e($sub) ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Charts Row -->
<div class="row g-4 mb-4">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">
        <h5><i class="bi bi-bar-chart-line me-2 text-primary"></i>Monthly Appointments</h5>
        <span style="font-size:.78rem;color:#94A3B8">Last 6 months</span>
      </div>
      <div class="card-body">
        <canvas id="appointmentChart" height="280"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">
        <h5><i class="bi bi-pie-chart me-2 text-success"></i>Appointment Status</h5>
      </div>
      <div class="card-body">
        <canvas id="statusChart" height="280"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- Tables Row -->
<div class="row g-4">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">
        <h5><i class="bi bi-calendar-check me-2 text-primary"></i>Recent Appointments</h5>
        <a href="appointments.php" style="font-size:.82rem;color:var(--primary);text-decoration:none;font-weight:600">View All →</a>
      </div>
      <div class="table-responsive">
        <table class="data-table" id="appt-table">
          <thead><tr>
            <th>#</th><th>Patient</th><th>Doctor</th><th>Department</th><th>Date</th><th>Status</th>
          </tr></thead>
          <tbody>
          <?php if (empty($recentAppts)): ?>
          <tr><td colspan="6" class="text-center py-4" style="color:#94A3B8">No appointments yet</td></tr>
          <?php else: foreach($recentAppts as $i => $a): ?>
          <tr>
            <td style="color:#94A3B8;font-size:.8rem">#<?= $a['id'] ?></td>
            <td><?= e($a['patient_name']) ?></td>
            <td><?= e($a['doctor_name']) ?></td>
            <td><span style="font-size:.8rem;color:#64748B"><?= e($a['dept']) ?></span></td>
            <td style="font-size:.82rem"><?= date('d M Y', strtotime($a['appointment_date'])) ?></td>
            <td><span class="badge-pill <?= e($a['status']) ?>"><?= ucfirst($a['status']) ?></span></td>
          </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header">
        <h5><i class="bi bi-people me-2 text-success"></i>New Patients</h5>
        <a href="manage_patients.php" style="font-size:.82rem;color:var(--primary);text-decoration:none;font-weight:600">View All →</a>
      </div>
      <div class="card-body" style="padding:0">
        <?php if (empty($recentPatients)): ?>
        <div class="empty-state"><i class="bi bi-people"></i><p>No patients yet</p></div>
        <?php else: foreach($recentPatients as $p): ?>
        <div style="display:flex;align-items:center;gap:12px;padding:14px 20px;border-bottom:1px solid #F1F5F9">
          <div class="avatar-placeholder" style="width:40px;height:40px;font-size:.9rem;flex-shrink:0">
            <?= strtoupper(substr($p['name'],0,2)) ?>
          </div>
          <div style="flex:1;min-width:0">
            <div style="font-weight:600;font-size:.875rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($p['name']) ?></div>
            <div style="font-size:.75rem;color:#94A3B8"><?= e($p['email']) ?></div>
          </div>
          <?php if ($p['blood_group']): ?>
          <span style="background:#FEE2E2;color:#DC2626;font-size:.72rem;font-weight:700;padding:2px 8px;border-radius:6px"><?= e($p['blood_group']) ?></span>
          <?php endif; ?>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>
</div>

<?php
$chartLabels   = json_encode(array_column($chartData, 'month'));
$chartValues   = json_encode(array_column($chartData, 'total'));
$revLabels     = json_encode(array_column($revenueData, 'month'));
$revValues     = json_encode(array_column($revenueData, 'amount'));

$statusCounts = [0,0,0,0];
try {
    $sc = $db->query("SELECT status, COUNT(*) c FROM appointments GROUP BY status")->fetchAll();
    $map = ['pending'=>0,'approved'=>1,'completed'=>2,'rejected'=>3];
    foreach($sc as $row) if (isset($map[$row['status']])) $statusCounts[$map[$row['status']]] = $row['c'];
} catch(Exception $e) {}
$statusCountsJSON = json_encode($statusCounts);

$extraJS = <<<JS
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const primary = '#2563EB', secondary = '#14B8A6', accent = '#6366F1';
// Appointment bar chart
new Chart(document.getElementById('appointmentChart'), {
  type: 'bar',
  data: {
    labels: $chartLabels.length ? $chartLabels : ['Jan','Feb','Mar','Apr','May','Jun'],
    datasets: [{
      label: 'Appointments',
      data:  $chartValues.length ? $chartValues : [12,19,8,25,22,30],
      backgroundColor: 'rgba(37,99,235,0.12)',
      borderColor: primary,
      borderWidth: 2,
      borderRadius: 8,
      borderSkipped: false,
    }]
  },
  options: { responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,grid:{color:'#F1F5F9'}},x:{grid:{display:false}}} }
});
// Status doughnut
new Chart(document.getElementById('statusChart'), {
  type: 'doughnut',
  data: {
    labels: ['Pending','Approved','Completed','Rejected'],
    datasets: [{ data: {$statusCountsJSON}, backgroundColor: ['#FEF9C3','#DCFCE7','#DBEAFE','#FEE2E2'], borderColor: ['#EAB308','#22C55E','#3B82F6','#EF4444'], borderWidth: 2 }]}, 
  options: { responsive:true, cutout:'70%', plugins:{legend:{position:'bottom',labels:{font:{size:11},padding:12}}} }
});
</script>
JS;
include '../includes/layout_footer.php';
?>
