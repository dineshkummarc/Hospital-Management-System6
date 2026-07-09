<?php
require_once '../config.php';
requireAuth('patient');
$db = getDB();

$ptStmt = $db->prepare("SELECT id FROM patients WHERE user_id=?");
$ptStmt->execute([$_SESSION['user_id']]);
$patient = $ptStmt->fetch();
$ptId    = $patient['id'] ?? 0;

$prescriptions = $db->prepare("
    SELECT pr.*, du.name AS doctor_name, d.specialization,
           a.appointment_date, dep.name AS dept
    FROM prescriptions pr
    JOIN doctors d ON pr.doctor_id = d.id
    JOIN users du ON d.user_id = du.id
    JOIN appointments a ON pr.appointment_id = a.id
    JOIN departments dep ON a.department_id = dep.id
    WHERE pr.patient_id = ?
    ORDER BY pr.created_at DESC
");
$prescriptions->execute([$ptId]);
$prescriptions = $prescriptions->fetchAll();

$pageTitle = 'My Prescriptions'; $activeMenu = 'prescriptions';
include '../includes/layout_header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h4 style="font-family:var(--font-display);font-weight:800;margin:0">My Prescriptions</h4>
    <p style="color:#64748B;font-size:.875rem;margin:0"><?= count($prescriptions) ?> prescriptions issued</p>
  </div>
</div>

<?php if (empty($prescriptions)): ?>
<div class="card">
  <div class="empty-state" style="padding:80px 20px">
    <i class="bi bi-file-medical" style="font-size:3.5rem;color:#CBD5E1"></i>
    <p style="font-size:1rem;margin-top:12px;color:#94A3B8">No prescriptions yet.<br>They will appear here after your doctor consultation.</p>
    <a href="book_appointment.php" class="btn-primary-custom" style="margin-top:16px;display:inline-flex">
      <i class="bi bi-calendar-plus"></i> Book Appointment
    </a>
  </div>
</div>
<?php else: ?>
<div class="row g-4">
  <?php foreach($prescriptions as $pr):
    $medicines = [];
    try { $medicines = json_decode($pr['medicines'] ?? '[]', true) ?: []; } catch(Exception $e){}
  ?>
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-body">
        <!-- Header -->
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:16px">
          <div style="display:flex;align-items:center;gap:12px">
            <div class="avatar-placeholder" style="width:48px;height:48px;font-size:1rem;flex-shrink:0">
              <?= strtoupper(substr($pr['doctor_name'],0,2)) ?>
            </div>
            <div>
              <div style="font-weight:700"><?= e($pr['doctor_name']) ?></div>
              <div style="font-size:.78rem;color:var(--primary);font-weight:500"><?= e($pr['specialization']) ?></div>
              <div style="font-size:.75rem;color:#94A3B8"><?= e($pr['dept']) ?></div>
            </div>
          </div>
          <div style="text-align:right;font-size:.78rem;color:#94A3B8">
            <?= date('d M Y', strtotime($pr['created_at'])) ?>
          </div>
        </div>

        <!-- Diagnosis -->
        <div style="background:linear-gradient(135deg,var(--primary-light),#EEF2FF);border-radius:10px;padding:12px 14px;margin-bottom:14px">
          <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748B;margin-bottom:4px">Diagnosis</div>
          <div style="font-weight:600;font-size:.9rem;color:#1E293B"><?= e($pr['diagnosis']) ?></div>
        </div>

        <!-- Medicines -->
        <?php if (!empty($medicines)): ?>
        <div style="margin-bottom:14px">
          <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748B;margin-bottom:8px">Medicines</div>
          <div style="display:flex;flex-direction:column;gap:6px">
            <?php foreach($medicines as $m): ?>
            <div style="display:flex;align-items:center;gap:10px;padding:8px 12px;background:#F8FAFC;border-radius:8px;border:1px solid #F1F5F9">
              <i class="bi bi-capsule" style="color:var(--secondary);font-size:.9rem;flex-shrink:0"></i>
              <div style="flex:1">
                <span style="font-weight:600;font-size:.82rem"><?= e($m['name'] ?? '') ?></span>
                <?php if(!empty($m['dosage'])): ?><span style="font-size:.75rem;color:#64748B;margin-left:6px"><?= e($m['dosage']) ?></span><?php endif; ?>
              </div>
              <?php if(!empty($m['duration'])): ?>
              <span style="font-size:.72rem;color:#94A3B8;white-space:nowrap"><?= e($m['duration']) ?></span>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Advice -->
        <?php if ($pr['advice']): ?>
        <div style="padding:10px 12px;border-left:3px solid var(--secondary);background:#F0FDFA;border-radius:0 8px 8px 0;margin-bottom:14px;font-size:.82rem;color:#0F766E">
          <i class="bi bi-info-circle me-1"></i><?= e($pr['advice']) ?>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div style="display:flex;align-items:center;justify-content:space-between;padding-top:12px;border-top:1px solid #F1F5F9">
          <?php if ($pr['follow_up_date']): ?>
          <div style="font-size:.78rem;color:#F59E0B;font-weight:600">
            <i class="bi bi-calendar-event me-1"></i>Follow-up: <?= date('d M Y',strtotime($pr['follow_up_date'])) ?>
          </div>
          <?php else: ?>
          <div></div>
          <?php endif; ?>
          <a href="print_prescription.php?id=<?= $pr['id'] ?>" target="_blank"
             class="btn-sm-action" title="Print / Download" style="width:auto;padding:6px 14px;display:inline-flex;align-items:center;gap:6px;font-size:.8rem;font-weight:600">
            <i class="bi bi-printer"></i> Print
          </a>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include '../includes/layout_footer.php'; ?>
