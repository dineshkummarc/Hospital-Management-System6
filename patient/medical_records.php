<?php
require_once '../config.php';
requireAuth('patient');
$db = getDB();

$ptStmt = $db->prepare("SELECT id FROM patients WHERE user_id=?");
$ptStmt->execute([$_SESSION['user_id']]);
$patient = $ptStmt->fetch();
$ptId    = $patient['id'] ?? 0;

$msg = $msgType = '';

// Handle upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['record_file'])) {
    $title   = trim($_POST['title'] ?? '');
    $type    = $_POST['record_type'] ?? 'other';
    $desc    = trim($_POST['description'] ?? '');
    $date    = $_POST['record_date'] ?? date('Y-m-d');
    $file    = $_FILES['record_file'];

    $allowedExts  = ['pdf','jpg','jpeg','png','doc','docx'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!$title) {
        $msg = 'Record title is required.'; $msgType = 'error';
    } elseif (!in_array($ext, $allowedExts)) {
        $msg = 'Invalid file type. Allowed: PDF, JPG, PNG, DOC.'; $msgType = 'error';
    } elseif ($file['size'] > MAX_FILE_SIZE) {
        $msg = 'File too large. Max 5MB allowed.'; $msgType = 'error';
    } else {
        $uploadDir = APP_ROOT . '/uploads/records/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $filename = uniqid('rec_') . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
            $db->prepare("INSERT INTO medical_records (patient_id,record_type,title,description,file_path,record_date) VALUES (?,?,?,?,?,?)")
               ->execute([$ptId, $type, $title, $desc, 'records/'.$filename, $date]);
            $msg = 'Record uploaded successfully.'; $msgType = 'success';
        } else {
            $msg = 'Upload failed. Please try again.'; $msgType = 'error';
        }
    }
}

// AJAX delete
if (!empty($_POST['action']) && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    if ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        $rec = $db->prepare("SELECT file_path FROM medical_records WHERE id=? AND patient_id=?");
        $rec->execute([$id, $ptId]);
        if ($row = $rec->fetch()) {
            $fp = APP_ROOT . '/uploads/' . $row['file_path'];
            if (file_exists($fp)) @unlink($fp);
            $db->prepare("DELETE FROM medical_records WHERE id=?")->execute([$id]);
            jsonResponse(true, 'Record deleted.');
        }
        jsonResponse(false, 'Not found.');
    }
}

$records = $db->prepare("SELECT * FROM medical_records WHERE patient_id=? ORDER BY record_date DESC");
$records->execute([$ptId]);
$records = $records->fetchAll();

$typeLabels = ['lab_report'=>'Lab Report','xray'=>'X-Ray','scan'=>'Scan','prescription'=>'Prescription','discharge_summary'=>'Discharge Summary','other'=>'Other'];
$typeIcons  = ['lab_report'=>'bi-clipboard2-pulse','xray'=>'bi-radioactive','scan'=>'bi-camera','prescription'=>'bi-file-medical','discharge_summary'=>'bi-file-text','other'=>'bi-folder'];

$pageTitle = 'Medical Records'; $activeMenu = 'records';
include '../includes/layout_header.php';
?>

<?php if ($msg): ?>
<div style="background:<?= $msgType==='success'?'#DCFCE7':'#FEE2E2' ?>;border:1px solid <?= $msgType==='success'?'#BBF7D0':'#FECACA' ?>;color:<?= $msgType==='success'?'#166534':'#991B1B' ?>;padding:12px 16px;border-radius:10px;margin-bottom:20px;font-size:.875rem;display:flex;align-items:center;gap:8px">
  <i class="bi bi-<?= $msgType==='success'?'check-circle':'x-circle' ?>-fill"></i> <?= e($msg) ?>
</div>
<?php endif; ?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
  <div>
    <h4 style="font-family:var(--font-display);font-weight:800;margin:0">Medical Records</h4>
    <p style="color:#64748B;font-size:.875rem;margin:0"><?= count($records) ?> records stored</p>
  </div>
  <button class="btn-primary-custom" onclick="openModal('upload-modal')">
    <i class="bi bi-cloud-upload"></i> Upload Record
  </button>
</div>

<?php if (empty($records)): ?>
<div class="card">
  <div class="empty-state" style="padding:80px 20px">
    <i class="bi bi-folder-heart" style="font-size:3.5rem;color:#CBD5E1"></i>
    <p style="margin-top:12px;color:#94A3B8">No medical records uploaded yet.<br>Keep all your health documents in one place.</p>
    <button onclick="openModal('upload-modal')" class="btn-primary-custom" style="margin-top:16px">
      <i class="bi bi-cloud-upload"></i> Upload First Record
    </button>
  </div>
</div>
<?php else: ?>
<div class="row g-4">
  <?php foreach($records as $rec): ?>
  <div class="col-lg-4 col-md-6" id="rec-<?= $rec['id'] ?>">
    <div class="card h-100">
      <div class="card-body">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:14px">
          <div style="width:48px;height:48px;border-radius:12px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;font-size:1.3rem;color:var(--primary);flex-shrink:0">
            <i class="bi <?= $typeIcons[$rec['record_type']] ?? 'bi-folder' ?>"></i>
          </div>
          <button class="btn-sm-action danger" onclick="deleteRecord(<?= $rec['id'] ?>)" title="Delete">
            <i class="bi bi-trash"></i>
          </button>
        </div>
        <div style="font-weight:700;margin-bottom:4px"><?= e($rec['title']) ?></div>
        <div style="font-size:.78rem;color:var(--primary);font-weight:500;margin-bottom:8px">
          <?= $typeLabels[$rec['record_type']] ?? 'Other' ?>
        </div>
        <?php if ($rec['description']): ?>
        <p style="font-size:.8rem;color:#64748B;margin-bottom:10px;line-height:1.5"><?= e($rec['description']) ?></p>
        <?php endif; ?>
        <div style="font-size:.78rem;color:#94A3B8;margin-bottom:14px">
          <i class="bi bi-calendar3 me-1"></i><?= date('d M Y', strtotime($rec['record_date'])) ?>
        </div>
        <?php if ($rec['file_path']): ?>
        <a href="<?= APP_URL ?>/uploads/<?= e($rec['file_path']) ?>" target="_blank"
           style="display:inline-flex;align-items:center;gap:6px;color:var(--primary);font-size:.82rem;font-weight:600;text-decoration:none;padding:7px 14px;border:1.5px solid var(--primary);border-radius:8px;transition:all .2s"
           onmouseover="this.style.background='var(--primary-light)'" onmouseout="this.style.background='transparent'">
          <i class="bi bi-download"></i> View / Download
        </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Upload Modal -->
<div class="modal-custom" id="upload-modal">
  <div class="modal-box" style="max-width:500px">
    <div class="modal-header-custom">
      <h5><i class="bi bi-cloud-upload me-2 text-primary"></i>Upload Medical Record</h5>
      <button class="modal-close" onclick="closeModal('upload-modal')">&times;</button>
    </div>
    <div class="modal-body-custom">
      <form method="POST" enctype="multipart/form-data" id="upload-form">
        <div class="form-group">
          <label class="form-label">Record Title *</label>
          <input type="text" name="title" class="form-control-custom" placeholder="e.g. Blood Test Report" required>
        </div>
        <div class="form-group">
          <label class="form-label">Record Type</label>
          <select name="record_type" class="form-control-custom">
            <?php foreach($typeLabels as $val=>$lbl): ?>
            <option value="<?= $val ?>"><?= $lbl ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Record Date</label>
          <input type="date" name="record_date" class="form-control-custom" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control-custom" rows="2" placeholder="Optional notes about this record…"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Upload File * <span style="color:#94A3B8;font-weight:400">(PDF, JPG, PNG, DOC — max 5MB)</span></label>
          <div id="drop-zone" style="border:2px dashed #E2E8F0;border-radius:12px;padding:28px;text-align:center;cursor:pointer;transition:all .2s"
               onclick="document.getElementById('file-input').click()"
               ondragover="event.preventDefault();this.style.borderColor='var(--primary)';this.style.background='var(--primary-light)'"
               ondragleave="this.style.borderColor='#E2E8F0';this.style.background=''"
               ondrop="handleDrop(event)">
            <i class="bi bi-cloud-arrow-up" style="font-size:2rem;color:#94A3B8;display:block;margin-bottom:8px"></i>
            <div style="font-size:.875rem;color:#64748B" id="drop-text">Click or drag & drop file here</div>
          </div>
          <input type="file" id="file-input" name="record_file" class="d-none" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required
                 onchange="updateDropZone(this.files[0])">
        </div>
        <div class="modal-footer-custom" style="padding:0;margin-top:4px">
          <button type="button" class="btn-outline-custom" onclick="closeModal('upload-modal')">Cancel</button>
          <button type="submit" class="btn-primary-custom"><i class="bi bi-cloud-upload"></i> Upload</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function updateDropZone(file) {
  if (file) document.getElementById('drop-text').textContent = '✓ ' + file.name;
}
function handleDrop(e) {
  e.preventDefault();
  e.currentTarget.style.borderColor = '#E2E8F0';
  e.currentTarget.style.background  = '';
  const file = e.dataTransfer.files[0];
  if (file) {
    const dt = new DataTransfer();
    dt.items.add(file);
    document.getElementById('file-input').files = dt.files;
    updateDropZone(file);
  }
}
async function deleteRecord(id) {
  confirmAction('Delete this medical record? This cannot be undone.', async () => {
    const res = await ajaxPost('medical_records.php', { action:'delete', id });
    if (res.success) { document.getElementById('rec-'+id)?.remove(); showToast('Deleted', res.message, 'success'); }
    else showToast('Error', res.message, 'error');
  });
}
</script>
<?php include '../includes/layout_footer.php'; ?>
