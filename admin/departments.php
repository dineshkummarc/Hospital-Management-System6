<?php
require_once '../config.php';
requireAuth('admin');
$db = getDB();

// AJAX
if (!empty($_POST['action']) && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $action = $_POST['action'];
    if ($action === 'add') {
        $name = trim($_POST['name']); $desc = trim($_POST['description'] ?? ''); $icon = trim($_POST['icon'] ?? 'hospital');
        if (!$name) jsonResponse(false, 'Name is required.');
        $db->prepare("INSERT INTO departments (name, description, icon) VALUES (?,?,?)")->execute([$name,$desc,$icon]);
        $id = $db->lastInsertId();
        jsonResponse(true, 'Department added.', ['id'=>$id]);
    }
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $cnt = $db->prepare("SELECT COUNT(*) FROM doctors WHERE department_id=?"); $cnt->execute([$id]);
        if ($cnt->fetchColumn() > 0) jsonResponse(false, 'Cannot delete: doctors are assigned to this department.');
        $db->prepare("DELETE FROM departments WHERE id=?")->execute([$id]);
        jsonResponse(true, 'Department deleted.');
    }
    if ($action === 'toggle') {
        $id = (int)$_POST['id'];
        $status = $_POST['status'] === 'active' ? 'inactive' : 'active';
        $db->prepare("UPDATE departments SET status=? WHERE id=?")->execute([$status,$id]);
        jsonResponse(true, "Status: $status.", ['new_status'=>$status]);
    }
}

$departments = $db->query("
    SELECT d.*, COUNT(doc.id) AS doctor_count
    FROM departments d
    LEFT JOIN doctors doc ON d.id = doc.department_id
    GROUP BY d.id ORDER BY d.name
")->fetchAll();

$pageTitle = 'Departments'; $activeMenu = 'departments';
include '../includes/layout_header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
  <div>
    <h4 style="font-family:var(--font-display);font-weight:800;margin:0">Departments</h4>
    <p style="color:#64748B;font-size:.875rem;margin:0"><?= count($departments) ?> departments</p>
  </div>
  <button class="btn-primary-custom" onclick="openModal('add-dept-modal')">
    <i class="bi bi-plus-circle"></i> Add Department
  </button>
</div>

<div class="row g-4">
<?php
$icons = ['heart-pulse','activity','clipboard2-pulse','emoji-smile','droplet','eye','gender-female','ear','hospital','camera','lungs','bandaid'];
foreach($departments as $dept):
?>
<div class="col-lg-4 col-md-6" id="dept-<?= $dept['id'] ?>">
  <div class="card h-100">
    <div class="card-body">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:16px">
        <div style="width:52px;height:52px;border-radius:14px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;font-size:1.4rem;color:var(--primary)">
          <i class="bi bi-<?= e($dept['icon'] ?? 'hospital') ?>"></i>
        </div>
        <span class="badge-pill <?= $dept['status'] ?>" id="dept-badge-<?= $dept['id'] ?>"><?= ucfirst($dept['status']) ?></span>
      </div>
      <h5 style="font-weight:700;margin-bottom:6px"><?= e($dept['name']) ?></h5>
      <p style="font-size:.82rem;color:#64748B;margin-bottom:16px;min-height:40px"><?= e($dept['description'] ?? '') ?></p>
      <div style="display:flex;align-items:center;justify-content:space-between">
        <span style="font-size:.82rem;color:#475569"><i class="bi bi-person-badge me-1"></i><?= $dept['doctor_count'] ?> Doctors</span>
        <div style="display:flex;gap:5px">
          <button class="btn-sm-action success" onclick="toggleDept(<?= $dept['id'] ?>,'<?= $dept['status'] ?>')" title="Toggle"><i class="bi bi-toggle-on"></i></button>
          <button class="btn-sm-action danger" onclick="deleteDept(<?= $dept['id'] ?>,'<?= e($dept['name']) ?>')" title="Delete"><i class="bi bi-trash"></i></button>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>

<!-- Add Modal -->
<div class="modal-custom" id="add-dept-modal">
  <div class="modal-box" style="max-width:480px">
    <div class="modal-header-custom">
      <h5><i class="bi bi-building me-2 text-primary"></i>Add Department</h5>
      <button class="modal-close" onclick="closeModal('add-dept-modal')">&times;</button>
    </div>
    <div class="modal-body-custom">
      <div class="form-group">
        <label class="form-label">Department Name *</label>
        <input type="text" id="dept-name" class="form-control-custom" placeholder="e.g. Cardiology">
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea id="dept-desc" class="form-control-custom" rows="3"></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Icon (Bootstrap Icons name)</label>
        <input type="text" id="dept-icon" class="form-control-custom" value="hospital" placeholder="e.g. heart-pulse">
        <div style="font-size:.75rem;color:#94A3B8;margin-top:4px">See: icons.getbootstrap.com</div>
      </div>
    </div>
    <div class="modal-footer-custom">
      <button class="btn-outline-custom" onclick="closeModal('add-dept-modal')">Cancel</button>
      <button class="btn-primary-custom" onclick="addDept(this)"><i class="bi bi-check2"></i> Add Department</button>
    </div>
  </div>
</div>

<script>
async function addDept(btn) {
  const name = document.getElementById('dept-name').value.trim();
  const desc = document.getElementById('dept-desc').value.trim();
  const icon = document.getElementById('dept-icon').value.trim() || 'hospital';
  if (!name) { showToast('Error','Name is required.','error'); return; }
  const res = await ajaxPost('departments.php', { action:'add', name, description:desc, icon }, btn);
  if (res.success) {
    showToast('Added', res.message, 'success');
    closeModal('add-dept-modal');
    setTimeout(() => location.reload(), 800);
  } else {
    showToast('Error', res.message, 'error');
  }
}
async function deleteDept(id, name) {
  confirmAction(`Delete department <strong>${name}</strong>?`, async () => {
    const res = await ajaxPost('departments.php', { action:'delete', id });
    if (res.success) { document.getElementById('dept-'+id)?.remove(); showToast('Deleted', res.message, 'success'); }
    else showToast('Error', res.message, 'error');
  });
}
async function toggleDept(id, status) {
  const res = await ajaxPost('departments.php', { action:'toggle', id, status });
  if (res.success) {
    const b = document.getElementById('dept-badge-'+id);
    b.textContent = res.new_status.charAt(0).toUpperCase() + res.new_status.slice(1);
    b.className = 'badge-pill ' + res.new_status;
    showToast('Updated', res.message, 'info');
  }
}
</script>
<?php include '../includes/layout_footer.php'; ?>
