<?php
require_once '../config.php';
requireAuth('admin');
$db = getDB();

$search = trim($_GET['search'] ?? '');
$where  = ['1=1']; $params = [];
if ($search) {
    $where[] = '(pu.name LIKE ? OR du.name LIKE ? OR pr.diagnosis LIKE ?)';
    $s = "%$search%"; $params = [$s,$s,$s];
}

$stmt = $db->prepare("
    SELECT pr.*, pu.name AS patient_name, du.name AS doctor_name,
           a.appointment_date
    FROM prescriptions pr
    JOIN patients pt ON pr.patient_id = pt.id
    JOIN users pu ON pt.user_id = pu.id
    JOIN doctors d ON pr.doctor_id = d.id
    JOIN users du ON d.user_id = du.id
    JOIN appointments a ON pr.appointment_id = a.id
    WHERE ".implode(' AND ',$where)."
    ORDER BY pr.created_at DESC
");
$stmt->execute($params);
$prescriptions = $stmt->fetchAll();

$pageTitle = 'Prescriptions'; $activeMenu = 'prescriptions';
include '../includes/layout_header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
  <div>
    <h4 style="font-family:var(--font-display);font-weight:800;margin:0">All Prescriptions</h4>
    <p style="color:#64748B;font-size:.875rem;margin:0"><?= count($prescriptions) ?> prescriptions</p>
  </div>
  <div style="position:relative">
    <i class="bi bi-search" style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#94A3B8;font-size:.85rem"></i>
    <form method="GET">
      <input type="search" name="search" class="form-control-custom" style="padding-left:34px;width:240px"
             placeholder="Search patient, doctor…" value="<?= e($search) ?>">
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="data-table">
      <thead><tr>
        <th>#</th><th>Patient</th><th>Doctor</th><th>Diagnosis</th>
        <th>Appt Date</th><th>Follow-up</th><th>Actions</th>
      </tr></thead>
      <tbody>
      <?php if (empty($prescriptions)): ?>
      <tr><td colspan="7"><div class="empty-state"><i class="bi bi-file-medical"></i><p>No prescriptions found.</p></div></td></tr>
      <?php else: foreach($prescriptions as $pr): ?>
      <tr>
        <td style="color:#94A3B8;font-size:.8rem">#<?= $pr['id'] ?></td>
        <td><strong><?= e($pr['patient_name']) ?></strong></td>
        <td><?= e($pr['doctor_name']) ?></td>
        <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.85rem">
          <?= e($pr['diagnosis']) ?>
        </td>
        <td style="font-size:.82rem"><?= date('d M Y', strtotime($pr['appointment_date'])) ?></td>
        <td style="font-size:.82rem">
          <?= $pr['follow_up_date'] ? date('d M Y', strtotime($pr['follow_up_date'])) : '<span style="color:#94A3B8">—</span>' ?>
        </td>
        <td>
          <div style="display:flex;gap:5px">
            <button class="btn-sm-action" title="View" onclick="viewPrescription(<?= $pr['id'] ?>)">
              <i class="bi bi-eye"></i>
            </button>
            <a href="print_prescription.php?id=<?= $pr['id'] ?>" target="_blank" class="btn-sm-action" title="Print">
              <i class="bi bi-printer"></i>
            </a>
          </div>
        </td>
      </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- View Prescription Modal -->
<div class="modal-custom" id="view-rx-modal">
  <div class="modal-box" style="max-width:600px">
    <div class="modal-header-custom">
      <h5><i class="bi bi-file-medical me-2 text-primary"></i>Prescription Details</h5>
      <button class="modal-close" onclick="closeModal('view-rx-modal')">&times;</button>
    </div>
    <div class="modal-body-custom" id="rx-content">
      <div style="text-align:center;padding:30px;color:#94A3B8"><i class="bi bi-arrow-clockwise" style="font-size:1.5rem"></i></div>
    </div>
  </div>
</div>

<script>
async function viewPrescription(id) {
  openModal('view-rx-modal');
  document.getElementById('rx-content').innerHTML = '<div style="text-align:center;padding:30px;color:#94A3B8"><i class="bi bi-arrow-clockwise" style="font-size:1.5rem;animation:spin .8s linear infinite"></i></div>';
  const res = await fetch('../ajax/get_prescription.php?id=' + id);
  const data = await res.json();
  if (!data.success) { document.getElementById('rx-content').innerHTML = '<p style="color:red">Failed to load.</p>'; return; }
  const rx = data.prescription;
  let medicines = '';
  try {
    const meds = JSON.parse(rx.medicines || '[]');
    medicines = meds.map(m => `<tr><td>${m.name||''}</td><td>${m.dosage||''}</td><td>${m.duration||''}</td><td>${m.instructions||''}</td></tr>`).join('');
  } catch(e) { medicines = `<tr><td colspan="4" style="color:#94A3B8">No medicines listed</td></tr>`; }
  document.getElementById('rx-content').innerHTML = `
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px">
      <div><span style="font-size:.75rem;color:#94A3B8">PATIENT</span><div style="font-weight:700">${rx.patient_name}</div></div>
      <div><span style="font-size:.75rem;color:#94A3B8">DOCTOR</span><div style="font-weight:700">${rx.doctor_name}</div></div>
      <div><span style="font-size:.75rem;color:#94A3B8">DATE</span><div>${rx.created_at}</div></div>
      <div><span style="font-size:.75rem;color:#94A3B8">FOLLOW-UP</span><div>${rx.follow_up_date||'—'}</div></div>
    </div>
    <div style="margin-bottom:16px"><span style="font-size:.75rem;color:#94A3B8">DIAGNOSIS</span><div style="font-weight:600;margin-top:4px">${rx.diagnosis}</div></div>
    <div style="margin-bottom:16px">
      <span style="font-size:.75rem;color:#94A3B8">MEDICINES</span>
      <table style="width:100%;margin-top:8px;font-size:.85rem;border-collapse:collapse">
        <thead><tr style="background:#F8FAFC"><th style="padding:8px;text-align:left">Medicine</th><th style="padding:8px;text-align:left">Dosage</th><th style="padding:8px;text-align:left">Duration</th><th style="padding:8px;text-align:left">Instructions</th></tr></thead>
        <tbody>${medicines}</tbody>
      </table>
    </div>
    ${rx.advice ? `<div><span style="font-size:.75rem;color:#94A3B8">ADVICE</span><div style="margin-top:4px;font-size:.875rem;color:#475569">${rx.advice}</div></div>` : ''}
  `;
}
</script>
<?php include '../includes/layout_footer.php'; ?>
