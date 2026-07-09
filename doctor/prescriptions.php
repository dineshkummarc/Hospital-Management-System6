<?php
require_once '../config.php';
requireAuth('doctor');
$db = getDB();

$docStmt = $db->prepare("SELECT id FROM doctors WHERE user_id=?");
$docStmt->execute([$_SESSION['user_id']]);
$doctor = $docStmt->fetch();
$docId  = $doctor['id'] ?? 0;

// Save prescription via AJAX
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action'])
) {
    if ($_POST['action'] === 'save') {
        $apptId    = (int)$_POST['appointment_id'];
        $patientId = (int)$_POST['patient_id'];
        $diagnosis = trim($_POST['diagnosis'] ?? '');
        $advice    = trim($_POST['advice'] ?? '');
        $followUp  = $_POST['follow_up_date'] ?: null;
        $medicines = $_POST['medicines'] ?? '[]';

        if (!$diagnosis) jsonResponse(false, 'Diagnosis is required.');

        // Verify appointment belongs to this doctor
        $check = $db->prepare("SELECT id FROM appointments WHERE id=? AND doctor_id=?");
        $check->execute([$apptId, $docId]);
        if (!$check->fetch()) jsonResponse(false, 'Unauthorized.');

        // Check if prescription already exists
        $existing = $db->prepare("SELECT id FROM prescriptions WHERE appointment_id=?");
        $existing->execute([$apptId]);
        if ($existing->fetch()) {
            $db->prepare("UPDATE prescriptions SET diagnosis=?,medicines=?,advice=?,follow_up_date=?,updated_at=NOW() WHERE appointment_id=?")
               ->execute([$diagnosis, $medicines, $advice, $followUp, $apptId]);
            // ── Notify on update too ───────────────────────────
            $rxRow = $db->prepare("SELECT id FROM prescriptions WHERE appointment_id=?");
            $rxRow->execute([$apptId]);
            if ($rx = $rxRow->fetch()) notifyPrescriptionCreated($rx['id']);
            jsonResponse(true, 'Prescription updated.');
        } else {
            $db->prepare("INSERT INTO prescriptions (appointment_id,patient_id,doctor_id,diagnosis,medicines,advice,follow_up_date) VALUES (?,?,?,?,?,?,?)")
               ->execute([$apptId, $patientId, $docId, $diagnosis, $medicines, $advice, $followUp]);
            $newRxId = (int)$db->lastInsertId();
            // ── Mark appointment completed ─────────────────────
            $db->prepare("UPDATE appointments SET status='completed', updated_at=NOW() WHERE id=?")->execute([$apptId]);
            // ── Fire prescription + appointment notifications ──
            notifyPrescriptionCreated($newRxId);
            notifyAppointmentStatusChange($apptId, 'completed');
            jsonResponse(true, 'Prescription saved and appointment marked complete.');
        }
    }
}

// If appt_id in URL, pre-fill form
$apptId    = (int)($_GET['appt_id'] ?? 0);
$patientId = (int)($_GET['patient_id'] ?? 0);
$apptInfo  = null;
$existingRx = null;
if ($apptId) {
    $s = $db->prepare("SELECT a.*, u.name AS patient_name, u.phone, p.blood_group, p.date_of_birth FROM appointments a JOIN patients p ON a.patient_id=p.id JOIN users u ON p.user_id=u.id WHERE a.id=? AND a.doctor_id=?");
    $s->execute([$apptId, $docId]);
    $apptInfo = $s->fetch();

    $rx = $db->prepare("SELECT * FROM prescriptions WHERE appointment_id=?");
    $rx->execute([$apptId]);
    $existingRx = $rx->fetch();
}

// All prescriptions by this doctor
$prescriptions = $db->query("
    SELECT pr.*, u.name AS patient_name, a.appointment_date
    FROM prescriptions pr
    JOIN patients p ON pr.patient_id=p.id
    JOIN users u ON p.user_id=u.id
    JOIN appointments a ON pr.appointment_id=a.id
    WHERE pr.doctor_id=$docId
    ORDER BY pr.created_at DESC
")->fetchAll();

$pageTitle = 'Prescriptions'; $activeMenu = 'prescriptions';
include '../includes/layout_header.php';
?>

<div class="row g-4">
  <!-- Write Prescription -->
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header">
        <h5><i class="bi bi-pencil-square me-2 text-primary"></i>Write Prescription</h5>
      </div>
      <div class="card-body">
        <?php if (!$apptId): ?>
        <!-- Select appointment first -->
        <div style="margin-bottom:20px">
          <label class="form-label">Select Appointment</label>
          <?php
          $openAppts = $db->query("
              SELECT a.id, u.name AS patient_name, a.appointment_date, a.patient_id
              FROM appointments a
              JOIN patients p ON a.patient_id=p.id
              JOIN users u ON p.user_id=u.id
              WHERE a.doctor_id=$docId AND a.status IN ('approved','completed')
              ORDER BY a.appointment_date DESC LIMIT 30
          ")->fetchAll();
          ?>
          <select class="form-control-custom" id="appt-select-form" onchange="location.href='prescriptions.php?appt_id='+this.value.split('|')[0]+'&patient_id='+this.value.split('|')[1]">
            <option value="">— Select appointment —</option>
            <?php foreach($openAppts as $a): ?>
            <option value="<?= $a['id'] ?>|<?= $a['patient_id'] ?>">
              <?= e($a['patient_name']) ?> — <?= date('d M Y',strtotime($a['appointment_date'])) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php else: ?>
        <!-- Patient Info -->
        <?php if ($apptInfo): ?>
        <div style="background:var(--primary-light);border-radius:12px;padding:14px;margin-bottom:20px;display:flex;align-items:center;gap:12px">
          <div class="avatar-placeholder" style="width:44px;height:44px;font-size:1rem;flex-shrink:0">
            <?= strtoupper(substr($apptInfo['patient_name'],0,2)) ?>
          </div>
          <div>
            <div style="font-weight:700"><?= e($apptInfo['patient_name']) ?></div>
            <div style="font-size:.78rem;color:#64748B">
              <?= e($apptInfo['phone']??'') ?>
              <?= $apptInfo['blood_group']?" · <strong style='color:#DC2626'>".e($apptInfo['blood_group'])."</strong>":'' ?>
            </div>
          </div>
          <div style="margin-left:auto;font-size:.78rem;color:#94A3B8"><?= date('d M Y',strtotime($apptInfo['appointment_date'])) ?></div>
        </div>
        <?php endif; ?>

        <form id="rx-form">
          <input type="hidden" id="rx-appt-id" value="<?= $apptId ?>">
          <input type="hidden" id="rx-patient-id" value="<?= $patientId ?>">

          <div class="form-group">
            <label class="form-label">Diagnosis *</label>
            <textarea id="rx-diagnosis" class="form-control-custom" rows="2" placeholder="Enter diagnosis…" required><?= e($existingRx['diagnosis']??'') ?></textarea>
          </div>

          <!-- Medicines Section -->
          <div class="form-group">
            <label class="form-label" style="display:flex;justify-content:space-between">
              Medicines
              <button type="button" onclick="addMedicine()" style="background:none;border:none;cursor:pointer;color:var(--primary);font-size:.8rem;font-weight:600;padding:0">
                <i class="bi bi-plus-circle"></i> Add Medicine
              </button>
            </label>
            <div id="medicines-list">
              <!-- Medicines added dynamically -->
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Advice / Instructions</label>
            <textarea id="rx-advice" class="form-control-custom" rows="2" placeholder="Dietary advice, rest, etc."><?= e($existingRx['advice']??'') ?></textarea>
          </div>

          <div class="form-group">
            <label class="form-label">Follow-up Date</label>
            <input type="date" id="rx-followup" class="form-control-custom" min="<?= date('Y-m-d') ?>" value="<?= e($existingRx['follow_up_date']??'') ?>">
          </div>

          <button type="button" class="btn-primary-custom w-100" onclick="savePrescription(this)" style="justify-content:center">
            <i class="bi bi-check2"></i> Save Prescription
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Prescriptions List -->
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header">
        <h5><i class="bi bi-file-medical me-2 text-success"></i>My Prescriptions</h5>
      </div>
      <div class="table-responsive">
        <table class="data-table">
          <thead><tr><th>Patient</th><th>Diagnosis</th><th>Date</th><th>Follow-up</th><th>Actions</th></tr></thead>
          <tbody>
          <?php if (empty($prescriptions)): ?>
          <tr><td colspan="5"><div class="empty-state"><i class="bi bi-file-medical"></i><p>No prescriptions yet.</p></div></td></tr>
          <?php else: foreach($prescriptions as $pr): ?>
          <tr>
            <td><strong><?= e($pr['patient_name']) ?></strong></td>
            <td style="font-size:.82rem;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($pr['diagnosis']) ?></td>
            <td style="font-size:.8rem"><?= date('d M Y',strtotime($pr['created_at'])) ?></td>
            <td style="font-size:.8rem"><?= $pr['follow_up_date']?date('d M Y',strtotime($pr['follow_up_date'])):'—' ?></td>
            <td>
              <div style="display:flex;gap:5px">
                <a href="prescriptions.php?appt_id=<?= $pr['appointment_id'] ?>&patient_id=<?= $pr['patient_id'] ?>" class="btn-sm-action" title="Edit"><i class="bi bi-pencil"></i></a>
                <a href="print_prescription.php?id=<?= $pr['id'] ?>" target="_blank" class="btn-sm-action" title="Print"><i class="bi bi-printer"></i></a>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Pre-fill medicines if editing -->
<?php if ($existingRx): ?>
<script>
const existingMeds = <?= $existingRx['medicines'] ?: '[]' ?>;
</script>
<?php else: ?>
<script>const existingMeds = [];</script>
<?php endif; ?>

<script>
let medicineCount = 0;

function addMedicine(med = {}) {
  medicineCount++;
  const id = 'med-' + medicineCount;
  const html = `
    <div id="${id}" style="border:1.5px solid #E2E8F0;border-radius:10px;padding:12px;margin-bottom:10px">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px">
        <input type="text" class="form-control-custom" placeholder="Medicine name" value="${med.name||''}" style="padding:8px 12px;font-size:.82rem" oninput="syncMeds()">
        <input type="text" class="form-control-custom" placeholder="Dosage (e.g. 500mg)" value="${med.dosage||''}" style="padding:8px 12px;font-size:.82rem" oninput="syncMeds()">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr auto;gap:8px;align-items:center">
        <input type="text" class="form-control-custom" placeholder="Duration (e.g. 5 days)" value="${med.duration||''}" style="padding:8px 12px;font-size:.82rem" oninput="syncMeds()">
        <input type="text" class="form-control-custom" placeholder="Instructions" value="${med.instructions||''}" style="padding:8px 12px;font-size:.82rem" oninput="syncMeds()">
        <button type="button" onclick="document.getElementById('${id}').remove();syncMeds()" style="background:#FEE2E2;border:none;border-radius:8px;width:34px;height:34px;cursor:pointer;color:#DC2626;font-size:.9rem">✕</button>
      </div>
    </div>
  `;
  document.getElementById('medicines-list').insertAdjacentHTML('beforeend', html);
}

function syncMeds() { /* medicines read on save */ }

function getMedicines() {
  const meds = [];
  document.querySelectorAll('#medicines-list > div').forEach(div => {
    const inputs = div.querySelectorAll('input');
    if (inputs[0].value.trim()) {
      meds.push({ name:inputs[0].value.trim(), dosage:inputs[1].value.trim(), duration:inputs[2].value.trim(), instructions:inputs[3].value.trim() });
    }
  });
  return meds;
}

// Load existing medicines
document.addEventListener('DOMContentLoaded', () => {
  existingMeds.forEach(m => addMedicine(m));
  if (existingMeds.length === 0) addMedicine();
});

async function savePrescription(btn) {
  const diagnosis = document.getElementById('rx-diagnosis')?.value?.trim();
  if (!diagnosis) { showToast('Error','Diagnosis is required.','error'); return; }
  const data = {
    action: 'save',
    appointment_id: document.getElementById('rx-appt-id').value,
    patient_id:     document.getElementById('rx-patient-id').value,
    diagnosis,
    medicines:      JSON.stringify(getMedicines()),
    advice:         document.getElementById('rx-advice').value,
    follow_up_date: document.getElementById('rx-followup').value,
  };
  const res = await ajaxPost('prescriptions.php', data, btn);
  if (res.success) { showToast('Saved', res.message, 'success'); setTimeout(()=>location.href='prescriptions.php', 1000); }
  else showToast('Error', res.message, 'error');
}
</script>
<?php include '../includes/layout_footer.php'; ?>
