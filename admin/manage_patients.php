<?php
require_once '../config.php';
requireAuth('admin');

$db = getDB();
$msg = $msgType = '';

// AJAX actions
if (!empty($_POST['action']) && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $action = $_POST['action'];
    $id     = (int)$_POST['id'];
    if ($action === 'delete') {
        $db->prepare("DELETE FROM users WHERE id = (SELECT user_id FROM patients WHERE id = ?)")->execute([$id]);
        jsonResponse(true, 'Patient deleted.');
    }
    if ($action === 'toggle') {
        $status = $_POST['status'] === 'active' ? 'inactive' : 'active';
        $db->prepare("UPDATE users SET status=? WHERE id=(SELECT user_id FROM patients WHERE id=?)")->execute([$status,$id]);
        jsonResponse(true, "Status set to $status.", ['new_status'=>$status]);
    }
}

// Filters
$search = trim($_GET['search'] ?? '');
$gender = $_GET['gender'] ?? '';
$blood  = $_GET['blood_group'] ?? '';

$where  = ['1=1'];
$params = [];
if ($search) { $where[] = '(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)'; $s="%$search%"; $params=[$s,$s,$s]; }
if ($gender)  { $where[] = 'p.gender=?';       $params[] = $gender; }
if ($blood)   { $where[] = 'p.blood_group=?';  $params[] = $blood; }

$sql = "SELECT p.id, p.date_of_birth, p.gender, p.blood_group, p.emergency_contact,
               u.name, u.email, u.phone, u.status, u.created_at,
               (SELECT COUNT(*) FROM appointments WHERE patient_id=p.id) AS appt_count
        FROM patients p JOIN users u ON p.user_id=u.id
        WHERE ".implode(' AND ',$where)." ORDER BY u.name ASC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$patients = $stmt->fetchAll();

$pageTitle  = 'Manage Patients';
$activeMenu = 'patients';
include '../includes/layout_header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
  <div>
    <h4 style="font-family:var(--font-display);font-weight:800;margin:0">Patients Management</h4>
    <p style="color:#64748B;font-size:.875rem;margin:0"><?= count($patients) ?> patients registered</p>
  </div>
</div>

<!-- Filters -->
<div class="card mb-4">
  <div class="card-body" style="padding:16px 20px">
    <form method="GET" class="row g-3 align-items-end">
      <div class="col-md-4">
        <label class="form-label" style="font-size:.8rem">Search</label>
        <input type="search" name="search" class="form-control-custom" value="<?= e($search) ?>" placeholder="Name, email, phone…">
      </div>
      <div class="col-md-2">
        <label class="form-label" style="font-size:.8rem">Gender</label>
        <select name="gender" class="form-control-custom">
          <option value="">All</option>
          <option value="male"   <?= $gender==='male'?'selected':'' ?>>Male</option>
          <option value="female" <?= $gender==='female'?'selected':'' ?>>Female</option>
          <option value="other"  <?= $gender==='other'?'selected':'' ?>>Other</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label" style="font-size:.8rem">Blood Group</label>
        <select name="blood_group" class="form-control-custom">
          <option value="">All</option>
          <?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
          <option value="<?= $bg ?>" <?= $blood===$bg?'selected':'' ?>><?= $bg ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn-primary-custom w-100"><i class="bi bi-filter"></i> Filter</button>
      </div>
      <div class="col-md-1">
        <a href="manage_patients.php" class="btn-outline-custom" style="display:block;text-align:center;padding:9px">Clear</a>
      </div>
    </form>
  </div>
</div>

<!-- Table -->
<div class="card">
  <div class="table-responsive">
    <table class="data-table" id="patients-table">
      <thead><tr>
        <th>Patient</th><th>Contact</th><th>DOB / Gender</th><th>Blood</th>
        <th>Appointments</th><th>Joined</th><th>Status</th><th>Actions</th>
      </tr></thead>
      <tbody>
      <?php if (empty($patients)): ?>
      <tr><td colspan="8"><div class="empty-state"><i class="bi bi-people"></i><p>No patients found.</p></div></td></tr>
      <?php else: foreach($patients as $pt): ?>
      <tr id="pt-row-<?= $pt['id'] ?>">
        <td>
          <div style="display:flex;align-items:center;gap:10px">
            <div class="avatar-placeholder" style="width:40px;height:40px;font-size:.85rem;flex-shrink:0">
              <?= strtoupper(substr($pt['name'],0,2)) ?>
            </div>
            <div>
              <div style="font-weight:600;font-size:.875rem"><?= e($pt['name']) ?></div>
              <div style="font-size:.75rem;color:#94A3B8"><?= e($pt['email']) ?></div>
            </div>
          </div>
        </td>
        <td style="font-size:.82rem"><?= e($pt['phone'] ?? '—') ?></td>
        <td style="font-size:.82rem">
          <?= $pt['date_of_birth'] ? date('d M Y', strtotime($pt['date_of_birth'])) : '—' ?>
          <?php if($pt['gender']): ?><br><span style="color:#94A3B8;text-transform:capitalize"><?= e($pt['gender']) ?></span><?php endif; ?>
        </td>
        <td>
          <?php if($pt['blood_group']): ?>
          <span style="background:#FEE2E2;color:#DC2626;font-size:.75rem;font-weight:700;padding:3px 10px;border-radius:6px"><?= e($pt['blood_group']) ?></span>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td style="text-align:center">
          <span style="font-weight:700;color:var(--primary)"><?= $pt['appt_count'] ?></span>
        </td>
        <td style="font-size:.8rem;color:#64748B"><?= date('d M Y', strtotime($pt['created_at'])) ?></td>
        <td>
          <span class="badge-pill <?= $pt['status'] ?>" id="pt-badge-<?= $pt['id'] ?>"><?= ucfirst($pt['status']) ?></span>
        </td>
        <td>
          <div style="display:flex;gap:5px">
            <a href="patient_detail.php?id=<?= $pt['id'] ?>" class="btn-sm-action" title="View"><i class="bi bi-eye"></i></a>
            <button class="btn-sm-action success" title="Toggle Status" onclick="togglePt(<?= $pt['id'] ?>, '<?= $pt['status'] ?>')"><i class="bi bi-toggle-on"></i></button>
            <button class="btn-sm-action danger" title="Delete" onclick="deletePt(<?= $pt['id'] ?>, '<?= e($pt['name']) ?>')"><i class="bi bi-trash"></i></button>
          </div>
        </td>
      </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
async function deletePt(id, name) {
  confirmAction(`Delete patient <strong>${name}</strong>? All their records will be removed.`, async () => {
    const res = await ajaxPost('manage_patients.php', { action:'delete', id });
    if (res.success) { document.getElementById('pt-row-'+id)?.remove(); showToast('Deleted', res.message, 'success'); }
  });
}
async function togglePt(id, status) {
  const res = await ajaxPost('manage_patients.php', { action:'toggle', id, status });
  if (res.success) {
    const badge = document.getElementById('pt-badge-'+id);
    badge.textContent = res.new_status.charAt(0).toUpperCase() + res.new_status.slice(1);
    badge.className = 'badge-pill ' + res.new_status;
    showToast('Updated', res.message, 'info');
  }
}
</script>
<?php include '../includes/layout_footer.php'; ?>
