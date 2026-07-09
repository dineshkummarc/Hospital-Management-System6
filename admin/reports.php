<?php
require_once '../config.php';
requireAuth('admin');
$db = getDB();

// Monthly appointments last 12 months
$monthly = $db->query("
    SELECT DATE_FORMAT(appointment_date,'%b %Y') AS lbl,
           DATE_FORMAT(appointment_date,'%Y-%m') AS ym,
           COUNT(*) AS total,
           SUM(status='completed') AS completed,
           SUM(status='cancelled') AS cancelled
    FROM appointments
    WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY ym ORDER BY ym ASC
")->fetchAll();

// Revenue last 12 months
$revenue = $db->query("
    SELECT DATE_FORMAT(created_at,'%b %Y') AS lbl,
           DATE_FORMAT(created_at,'%Y-%m') AS ym,
           COALESCE(SUM(total_amount),0) AS total,
           COALESCE(SUM(CASE WHEN payment_status='paid' THEN total_amount ELSE 0 END),0) AS collected
    FROM billing
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY ym ORDER BY ym ASC
")->fetchAll();

// Doctor performance
$docPerf = $db->query("
    SELECT du.name, dep.name AS dept,
           COUNT(a.id) AS total_appts,
           SUM(a.status='completed') AS completed,
           COALESCE(SUM(b.total_amount),0) AS revenue
    FROM doctors d
    JOIN users du ON d.user_id=du.id
    JOIN departments dep ON d.department_id=dep.id
    LEFT JOIN appointments a ON a.doctor_id=d.id
    LEFT JOIN billing b ON b.appointment_id=a.id AND b.payment_status='paid'
    GROUP BY d.id ORDER BY total_appts DESC LIMIT 10
")->fetchAll();

// Dept distribution
$deptDist = $db->query("
    SELECT dep.name, COUNT(a.id) AS cnt
    FROM appointments a
    JOIN departments dep ON a.department_id=dep.id
    GROUP BY dep.id ORDER BY cnt DESC LIMIT 8
")->fetchAll();

// Summary stats
$totalPatients = $db->query("SELECT COUNT(*) FROM patients")->fetchColumn();
$totalDoctors  = $db->query("SELECT COUNT(*) FROM doctors WHERE status='active'")->fetchColumn();
$totalAppts    = $db->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
$totalRevenue  = $db->query("SELECT COALESCE(SUM(total_amount),0) FROM billing WHERE payment_status='paid'")->fetchColumn();
$thisMonthAppts = $db->query("SELECT COUNT(*) FROM appointments WHERE MONTH(appointment_date)=MONTH(CURDATE()) AND YEAR(appointment_date)=YEAR(CURDATE())")->fetchColumn();
$thisMonthRev   = $db->query("SELECT COALESCE(SUM(total_amount),0) FROM billing WHERE payment_status='paid' AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetchColumn();

$pageTitle = 'Reports & Analytics'; $activeMenu = 'reports';
include '../includes/layout_header.php';
?>

<!-- Summary Row -->
<div class="row g-4 mb-4">
  <?php foreach([
    ['Total Patients',   $totalPatients,   'bi-people',         'blue'],
    ['Active Doctors',   $totalDoctors,    'bi-person-badge',   'green'],
    ['Total Appointments',$totalAppts,     'bi-calendar-check', 'teal'],
    ['Total Revenue',    '₹'.number_format($totalRevenue,0), 'bi-currency-rupee', 'purple'],
    ['This Month Appts', $thisMonthAppts,  'bi-calendar-day',   'orange'],
    ['This Month Revenue','₹'.number_format($thisMonthRev,0),'bi-graph-up','blue'],
  ] as [$lbl,$val,$icon,$col]): ?>
  <div class="col-xl-2 col-lg-4 col-md-4 col-6">
    <div class="stat-card <?= $col ?>">
      <div class="stat-icon <?= $col ?>"><i class="bi <?= $icon ?>"></i></div>
      <div class="stat-value" <?= is_numeric($val) ? "data-count=\"$val\"" : "" ?>><?= e($val) ?></div>
      <div class="stat-label"><?= e($lbl) ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Charts Row 1 -->
<div class="row g-4 mb-4">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">
        <h5><i class="bi bi-bar-chart-line me-2 text-primary"></i>Monthly Appointments (Last 12 Months)</h5>
      </div>
      <div class="card-body"><canvas id="monthlyChart" height="260"></canvas></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">
        <h5><i class="bi bi-pie-chart me-2 text-secondary"></i>By Department</h5>
      </div>
      <div class="card-body"><canvas id="deptChart" height="260"></canvas></div>
    </div>
  </div>
</div>

<!-- Charts Row 2 -->
<div class="row g-4 mb-4">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">
        <h5><i class="bi bi-graph-up me-2 text-success"></i>Revenue Analysis (Last 12 Months)</h5>
      </div>
      <div class="card-body"><canvas id="revenueChart" height="260"></canvas></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">
        <h5><i class="bi bi-check2-circle me-2 text-info"></i>Appointment Status</h5>
      </div>
      <div class="card-body"><canvas id="statusDonut" height="260"></canvas></div>
    </div>
  </div>
</div>

<!-- Doctor Performance Table -->
<div class="card">
  <div class="card-header">
    <h5><i class="bi bi-trophy me-2 text-warning"></i>Doctor Performance</h5>
    <button onclick="printDiv('perf-table')" class="btn-outline-custom" style="padding:6px 14px;font-size:.8rem">
      <i class="bi bi-printer me-1"></i>Print
    </button>
  </div>
  <div class="table-responsive" id="perf-table">
    <table class="data-table">
      <thead><tr>
        <th>Rank</th><th>Doctor</th><th>Department</th>
        <th>Total Appointments</th><th>Completed</th><th>Completion Rate</th><th>Revenue</th>
      </tr></thead>
      <tbody>
      <?php if(empty($docPerf)): ?>
      <tr><td colspan="7"><div class="empty-state"><i class="bi bi-person-badge"></i><p>No data yet.</p></div></td></tr>
      <?php else: foreach($docPerf as $i=>$doc): ?>
      <?php $rate = $doc['total_appts'] > 0 ? round(($doc['completed']/$doc['total_appts'])*100) : 0; ?>
      <tr>
        <td>
          <?php if($i===0): ?><span style="font-size:1.2rem">🥇</span>
          <?php elseif($i===1): ?><span style="font-size:1.2rem">🥈</span>
          <?php elseif($i===2): ?><span style="font-size:1.2rem">🥉</span>
          <?php else: ?><span style="color:#94A3B8">#<?= $i+1 ?></span><?php endif; ?>
        </td>
        <td><strong><?= e($doc['name']) ?></strong></td>
        <td style="font-size:.82rem;color:#64748B"><?= e($doc['dept']) ?></td>
        <td style="text-align:center;font-weight:700"><?= $doc['total_appts'] ?></td>
        <td style="text-align:center"><?= $doc['completed'] ?></td>
        <td>
          <div style="display:flex;align-items:center;gap:8px">
            <div style="flex:1;background:#F1F5F9;border-radius:4px;height:6px">
              <div style="width:<?= $rate ?>%;background:var(--success);height:6px;border-radius:4px"></div>
            </div>
            <span style="font-size:.78rem;font-weight:600;width:36px"><?= $rate ?>%</span>
          </div>
        </td>
        <td style="font-weight:700;color:var(--success)">₹<?= number_format($doc['revenue'],0) ?></td>
      </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
$mLabels  = json_encode(array_column($monthly,'lbl'));
$mTotal   = json_encode(array_column($monthly,'total'));
$mComp    = json_encode(array_column($monthly,'completed'));
$rLabels  = json_encode(array_column($revenue,'lbl'));
$rTotal   = json_encode(array_column($revenue,'total'));
$rColl    = json_encode(array_column($revenue,'collected'));
$dLabels  = json_encode(array_column($deptDist,'name'));
$dCounts  = json_encode(array_column($deptDist,'cnt'));

// Status counts
$sc = $db->query("SELECT status, COUNT(*) c FROM appointments GROUP BY status")->fetchAll();
$statusMap = ['pending'=>0,'approved'=>0,'completed'=>0,'rejected'=>0,'cancelled'=>0];
foreach($sc as $r) if(isset($statusMap[$r['status']])) $statusMap[$r['status']]=$r['c'];
$statusVals = json_encode(array_values($statusMap));

$extraJS = <<<JS
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
Chart.defaults.font.family = "'Inter', sans-serif";
const colors = ['#2563EB','#14B8A6','#6366F1','#F59E0B','#EF4444','#10B981','#3B82F6','#8B5CF6'];

new Chart(document.getElementById('monthlyChart'),{
  type:'bar',
  data:{
    labels: $mLabels.length?$mLabels:['No data'],
    datasets:[
      {label:'Total',data:$mTotal,backgroundColor:'rgba(37,99,235,.15)',borderColor:'#2563EB',borderWidth:2,borderRadius:6},
      {label:'Completed',data:$mComp,backgroundColor:'rgba(16,185,129,.15)',borderColor:'#10B981',borderWidth:2,borderRadius:6}
    ]
  },
  options:{responsive:true,plugins:{legend:{position:'top'}},scales:{y:{beginAtZero:true,grid:{color:'#F1F5F9'}},x:{grid:{display:false}}}}
});

new Chart(document.getElementById('revenueChart'),{
  type:'line',
  data:{
    labels:$rLabels.length?$rLabels:['No data'],
    datasets:[
      {label:'Billed',data:$rTotal,borderColor:'#6366F1',backgroundColor:'rgba(99,102,241,.1)',tension:.4,fill:true},
      {label:'Collected',data:$rColl,borderColor:'#10B981',backgroundColor:'rgba(16,185,129,.1)',tension:.4,fill:true}
    ]
  },
  options:{responsive:true,plugins:{legend:{position:'top'}},scales:{y:{beginAtZero:true,grid:{color:'#F1F5F9'}},x:{grid:{display:false}}}}
});

new Chart(document.getElementById('deptChart'),{
  type:'doughnut',
  data:{
    labels:$dLabels.length?$dLabels:['No data'],
    datasets:[{data:$dCounts.length?$dCounts:[1],backgroundColor:colors,borderWidth:2,borderColor:'#fff'}]
  },
  options:{responsive:true,cutout:'60%',plugins:{legend:{position:'bottom',labels:{font:{size:10},padding:8}}}}
});

new Chart(document.getElementById('statusDonut'),{
  type:'doughnut',
  data:{
    labels:['Pending','Approved','Completed','Rejected','Cancelled'],
    datasets:[{data:$statusVals,backgroundColor:['#FEF9C3','#DCFCE7','#DBEAFE','#FEE2E2','#F1F5F9'],borderColor:['#EAB308','#22C55E','#3B82F6','#EF4444','#CBD5E1'],borderWidth:2}]
  },
  options:{responsive:true,cutout:'65%',plugins:{legend:{position:'bottom',labels:{font:{size:10},padding:8}}}}
});
</script>
JS;
include '../includes/layout_footer.php';
?>
