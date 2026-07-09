<?php
require_once '../config.php';
requireAuth('doctor');
$db = getDB();

$docStmt = $db->prepare("SELECT id FROM doctors WHERE user_id=?");
$docStmt->execute([$_SESSION['user_id']]);
$doctor = $docStmt->fetch();
$docId  = $doctor['id'] ?? 0;

$search = trim($_GET['search'] ?? '');
$params = [$docId];
$and    = '';
if ($search) { $and = "AND (u.name LIKE ? OR u.email LIKE ?)"; $s="%$search%"; $params[]=$s; $params[]=$s; }

$patients = $db->prepare("
    SELECT DISTINCT p.id, u.name, u.email, u.phone, p.blood_group, p.gender, p.date_of_birth,
           COUNT(a.id) AS total_visits,
           MAX(a.appointment_date) AS last_visit
    FROM appointments a
    JOIN patients p ON a.patient_id=p.id
    JOIN users u ON p.user_id=u.id
    WHERE a.doctor_id=? $and
    GROUP BY p.id ORDER BY last_visit DESC
");
$patients->execute($params);
$patients = $patients->fetchAll();

$pageTitle = 'My Patients'; $activeMenu = 'patients';
include '../includes/layout_header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
  <div>
    <h4 style="font-family:var(--font-display);font-weight:800;margin:0">My Patients</h4>
    <p style="color:#64748B;font-size:.875rem;margin:0"><?= count($patients) ?> patients consulted</p>
  </div>
  <form method="GET" style="position:relative">
    <i class="bi bi-search" style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#94A3B8;font-size:.85rem"></i>
    <input type="search" name="search" class="form-control-custom" style="padding-left:34px;width:220px" placeholder="Search patients…" value="<?= e($search) ?>">
  </form>
</div>

<div class="row g-4">
<?php if (empty($patients)): ?>
<div class="col-12"><div class="empty-state card"><i class="bi bi-people"></i><p>No patients yet. They will appear after confirmed appointments.</p></div></div>
<?php else: foreach($patients as $pt): ?>
<div class="col-lg-4 col-md-6">
  <div class="card h-100">
    <div class="card-body">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
        <div class="avatar-placeholder" style="width:52px;height:52px;font-size:1.1rem;flex-shrink:0">
          <?= strtoupper(substr($pt['name'],0,2)) ?>
        </div>
        <div>
          <div style="font-weight:700"><?= e($pt['name']) ?></div>
          <div style="font-size:.78rem;color:#94A3B8"><?= e($pt['email']) ?></div>
        </div>
        <?php if($pt['blood_group']): ?>
        <span style="margin-left:auto;background:#FEE2E2;color:#DC2626;font-size:.72rem;font-weight:700;padding:3px 10px;border-radius:6px"><?= e($pt['blood_group']) ?></span>
        <?php endif; ?>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:.82rem;color:#475569">
        <div><i class="bi bi-telephone me-1 text-muted"></i><?= e($pt['phone']??'—') ?></div>
        <div><i class="bi bi-gender-ambiguous me-1 text-muted"></i><?= ucfirst($pt['gender']??'—') ?></div>
        <div><i class="bi bi-calendar3 me-1 text-muted"></i><?= $pt['date_of_birth']?date('d M Y',strtotime($pt['date_of_birth'])):'—' ?></div>
        <div><i class="bi bi-clock-history me-1 text-muted"></i><?= $pt['last_visit']?date('d M Y',strtotime($pt['last_visit'])):'—' ?></div>
      </div>
      <div class="divider" style="margin:14px 0"></div>
      <div style="display:flex;align-items:center;justify-content:space-between">
        <span style="font-size:.82rem;color:#64748B"><i class="bi bi-calendar-check me-1"></i><?= $pt['total_visits'] ?> visit<?= $pt['total_visits']!=1?'s':'' ?></span>
        <a href="prescriptions.php?search=<?= urlencode($pt['name']) ?>" class="btn-sm-action" title="Prescriptions">
          <i class="bi bi-file-medical"></i>
        </a>
      </div>
    </div>
  </div>
</div>
<?php endforeach; endif; ?>
</div>
<?php include '../includes/layout_footer.php'; ?>
