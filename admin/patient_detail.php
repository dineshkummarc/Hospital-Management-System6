<?php
require_once '../config.php';
requireAuth('admin');
$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT p.*, u.name, u.email, u.phone, u.status, u.created_at
    FROM patients p JOIN users u ON p.user_id=u.id WHERE p.id=?
");
$stmt->execute([$id]);
$patient = $stmt->fetch();
if (!$patient) { redirect(APP_URL.'/admin/manage_patients.php'); }

$appointments = $db->prepare("
    SELECT a.*, du.name AS doctor_name, dep.name AS dept
    FROM appointments a
    JOIN doctors d ON a.doctor_id=d.id
    JOIN users du ON d.user_id=du.id
    JOIN departments dep ON a.department_id=dep.id
    WHERE a.patient_id=? ORDER BY a.appointment_date DESC
");
$appointments->execute([$id]);
$appointments = $appointments->fetchAll();

$prescriptions = $db->prepare("
    SELECT pr.*, du.name AS doctor_name
    FROM prescriptions pr
    JOIN doctors d ON pr.doctor_id=d.id
    JOIN users du ON d.user_id=du.id
    WHERE pr.patient_id=? ORDER BY pr.created_at DESC
");
$prescriptions->execute([$id]);
$prescriptions = $prescriptions->fetchAll();

$bills = $db->prepare("SELECT b.* FROM billing b WHERE b.patient_id=? ORDER BY b.created_at DESC");
$bills->execute([$id]);
$bills = $bills->fetchAll();

$pageTitle = 'Patient: '.e($patient['name']); $activeMenu = 'patients';
include '../includes/layout_header.php';
?>

<a href="manage_patients.php" style="display:inline-flex;align-items:center;gap:6px;color:#64748B;text-decoration:none;font-size:.875rem;margin-bottom:20px">
  <i class="bi bi-arrow-left"></i> Back to Patients
</a>

<div class="row g-4">
  <!-- Profile Card -->
  <div class="col-lg-4">
    <div class="profile-card mb-4">
      <div class="avatar-placeholder" style="width:72px;height:72px;font-size:1.6rem;margin-bottom:14px">
        <?= strtoupper(substr($patient['name'],0,2)) ?>
      </div>
      <h4 style="font-weight:800;margin-bottom:4px"><?= e($patient['name']) ?></h4>
      <p style="opacity:.8;font-size:.875rem;margin-bottom:16px"><?= e($patient['email']) ?></p>
      <span class="badge-pill <?= $patient['status'] ?>" style="display:inline-flex"><?= ucfirst($patient['status']) ?></span>
    </div>
    <div class="card">
      <div class="card-header"><h5>Patient Details</h5></div>
      <div class="card-body" style="display:flex;flex-direction:column;gap:12px">
        <?php foreach([
          ['bi-telephone','Phone',$patient['phone']??'—'],
          ['bi-calendar3','DOB',$patient['date_of_birth']?date('d M Y',strtotime($patient['date_of_birth'])):'—'],
          ['bi-gender-ambiguous','Gender',ucfirst($patient['gender']??'—')],
          ['bi-droplet-fill','Blood Group',$patient['blood_group']??'—'],
          ['bi-heart','Allergies',$patient['allergies']??'None'],
          ['bi-calendar-plus','Registered',date('d M Y',strtotime($patient['created_at']))],
        ] as [$icon,$label,$val]): ?>
        <div style="display:flex;align-items:flex-start;gap:10px">
          <i class="bi <?= $icon ?>" style="color:#94A3B8;margin-top:2px;width:16px"></i>
          <div>
            <div style="font-size:.72rem;color:#94A3B8;font-weight:600"><?= $label ?></div>
            <div style="font-size:.875rem;font-weight:500"><?= e($val) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <!-- Appointments -->
    <div class="card mb-4">
      <div class="card-header"><h5><i class="bi bi-calendar-check me-2 text-primary"></i>Appointments (<?= count($appointments) ?>)</h5></div>
      <div class="table-responsive">
        <table class="data-table">
          <thead><tr><th>Doctor</th><th>Dept</th><th>Date</th><th>Status</th></tr></thead>
          <tbody>
          <?php if(empty($appointments)): ?>
          <tr><td colspan="4" style="text-align:center;padding:20px;color:#94A3B8">No appointments</td></tr>
          <?php else: foreach($appointments as $a): ?>
          <tr>
            <td><?= e($a['doctor_name']) ?></td>
            <td style="font-size:.8rem;color:#64748B"><?= e($a['dept']) ?></td>
            <td style="font-size:.82rem"><?= date('d M Y',strtotime($a['appointment_date'])) ?></td>
            <td><span class="badge-pill <?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span></td>
          </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Prescriptions -->
    <div class="card mb-4">
      <div class="card-header"><h5><i class="bi bi-file-medical me-2 text-success"></i>Prescriptions (<?= count($prescriptions) ?>)</h5></div>
      <div class="table-responsive">
        <table class="data-table">
          <thead><tr><th>Doctor</th><th>Diagnosis</th><th>Date</th><th>Follow-up</th></tr></thead>
          <tbody>
          <?php if(empty($prescriptions)): ?>
          <tr><td colspan="4" style="text-align:center;padding:20px;color:#94A3B8">No prescriptions</td></tr>
          <?php else: foreach($prescriptions as $pr): ?>
          <tr>
            <td><?= e($pr['doctor_name']) ?></td>
            <td style="font-size:.82rem;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($pr['diagnosis']) ?></td>
            <td style="font-size:.82rem"><?= date('d M Y',strtotime($pr['created_at'])) ?></td>
            <td style="font-size:.82rem"><?= $pr['follow_up_date']?date('d M Y',strtotime($pr['follow_up_date'])):'—' ?></td>
          </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Bills -->
    <div class="card">
      <div class="card-header"><h5><i class="bi bi-receipt me-2 text-warning"></i>Bills (<?= count($bills) ?>)</h5></div>
      <div class="table-responsive">
        <table class="data-table">
          <thead><tr><th>Invoice</th><th>Amount</th><th>Method</th><th>Status</th><th></th></tr></thead>
          <tbody>
          <?php if(empty($bills)): ?>
          <tr><td colspan="5" style="text-align:center;padding:20px;color:#94A3B8">No bills</td></tr>
          <?php else: foreach($bills as $b): ?>
          <tr>
            <td style="font-weight:600;font-size:.82rem;color:var(--primary)"><?= e($b['invoice_number']) ?></td>
            <td style="font-weight:700">₹<?= number_format($b['total_amount'],2) ?></td>
            <td style="font-size:.8rem;text-transform:capitalize"><?= e($b['payment_method']) ?></td>
            <td><span class="badge-pill <?= $b['payment_status'] ?>"><?= ucfirst($b['payment_status']) ?></span></td>
            <td><a href="print_bill.php?id=<?= $b['id'] ?>" target="_blank" class="btn-sm-action" title="Print"><i class="bi bi-printer"></i></a></td>
          </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php include '../includes/layout_footer.php'; ?>
