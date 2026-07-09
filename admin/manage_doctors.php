<?php
require_once '../config.php';
requireAuth('admin');

$db = getDB();
$msg = $msgType = '';

if (isset($_GET['action']) && $_GET['action'] === 'approve') {

    $id = (int)$_GET['id'];

    $stmt = $db->prepare("
        UPDATE doctors
        SET status = 'active'
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    header("Location: manage_doctors.php");
    exit;
}

// Handle AJAX
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action'])
) {
    $action = $_POST['action'];
  

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $db->prepare("DELETE FROM users WHERE id = (SELECT user_id FROM doctors WHERE id = ?)")->execute([$id]);
        jsonResponse(true, 'Doctor deleted successfully.');
    }

    if ($action === 'toggle_status') {
        $id     = (int)$_POST['id'];
        $status = $_POST['status'] === 'active' ? 'inactive' : 'active';
        $db->prepare("UPDATE doctors SET status = ? WHERE id = ?")->execute([$status, $id]);
        jsonResponse(true, "Status updated to $status.", ['new_status' => $status]);
    }
}

// Add Doctor (form submit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['add_doctor'])) {
    try {
        $name        = trim($_POST['name']);
        $email       = trim($_POST['email']);
        $phone       = trim($_POST['phone'] ?? '');
        $password    = $_POST['password'] ?? 'Doctor@123';
        $dept_id     = (int)$_POST['department_id'];
        $spec        = trim($_POST['specialization']);
        $qual        = trim($_POST['qualification'] ?? '');
        $exp         = (int)($_POST['experience_years'] ?? 0);
        $fee         = (float)($_POST['consultation_fee'] ?? 0);
        $bio         = trim($_POST['bio'] ?? '');
        $license     = trim($_POST['license_number'] ?? '');

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => PASSWORD_COST]);
        $db->beginTransaction();
        $db->prepare("INSERT INTO users (name,email,password,role,phone) VALUES (?,?,?,'doctor',?)")
           ->execute([$name,$email,$hash,$phone]);
        $uid = $db->lastInsertId();
        $stmt = $db->prepare("
INSERT INTO doctors
(
    user_id,
    department_id,
    specialization,
    qualification,
    experience_years,
    consultation_fee,
    bio,
    license_number,
    available_days,
    available_from,
    available_to,
    status
)
VALUES
(?,?,?,?,?,?,?,?,?,?,?,?)
");

$result = $stmt->execute([
    $uid,
    $dept_id,
    $spec,
    $qual,
    $exp,
    $fee,
    $bio,
    $license,
    'Mon,Tue,Wed,Thu,Fri',
    '09:00:00',
    '17:00:00',
    'active'
]);

if (!$result) {
    die(print_r($stmt->errorInfo(), true));
}
        $db->commit();
        $msg = 'Doctor added successfully!'; $msgType = 'success';
    } catch (PDOException $e) {
        if ($db->inTransaction()) $db->rollBack();
        $msg = 'Error: ' . $e->getMessage(); $msgType = 'error';
    }
}

// Fetch all doctors
$doctors = $db->query("
    SELECT d.*, u.name, u.email, u.phone, u.status AS user_status, dep.name AS dept_name
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    JOIN departments dep ON d.department_id = dep.id
    ORDER BY u.name ASC
")->fetchAll();

$departments = $db->query("SELECT * FROM departments WHERE status='active' ORDER BY name")->fetchAll();

$pageTitle  = 'Manage Doctors';
$activeMenu = 'doctors';
include '../includes/layout_header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType === 'success' ? 'success' : 'danger' ?> d-flex align-items-center gap-2 rounded-3 mb-3" style="font-size:.875rem">
  <i class="bi bi-<?= $msgType === 'success' ? 'check-circle' : 'x-circle' ?>-fill"></i> <?= e($msg) ?>
</div>
<?php endif; ?>

<!-- Header bar -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
  <div>
    <h4 style="font-family:var(--font-display);font-weight:800;margin:0">Doctors Management</h4>
    <p style="color:#64748B;font-size:.875rem;margin:0"><?= count($doctors) ?> doctors registered</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <div style="position:relative">
      <i class="bi bi-search" style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#94A3B8;font-size:.85rem"></i>
      <input type="search" id="search-input" class="form-control-custom" style="padding-left:34px;width:220px" placeholder="Search doctors…">
    </div>
    <button class="btn-primary-custom" onclick="openModal('add-doctor-modal')">
      <i class="bi bi-person-plus"></i> Add Doctor
    </button>
  </div>
</div>

<!-- Doctors Table -->
<div class="card">
  <div class="table-responsive">
    <table class="data-table" id="doctors-table">
      <thead><tr>
        <th>Doctor</th><th>Department</th><th>Specialization</th><th>Experience</th><th>Fee</th><th>Status</th><th>Actions</th>
      </tr></thead>
      <tbody>
      <?php if (empty($doctors)): ?>
      <tr><td colspan="7"><div class="empty-state"><i class="bi bi-person-badge"></i><p>No doctors added yet.</p></div></td></tr>
      <?php else: foreach($doctors as $doc): ?>
      <tr id="doctor-row-<?= $doc['id'] ?>">
        <td>
          <div style="display:flex;align-items:center;gap:12px">
            <div class="avatar-placeholder" style="width:42px;height:42px;font-size:.9rem;flex-shrink:0">
              <?= strtoupper(substr($doc['name'],0,2)) ?>
            </div>
            <div>
              <div style="font-weight:600"><?= e($doc['name']) ?></div>
              <div style="font-size:.75rem;color:#94A3B8"><?= e($doc['email']) ?></div>
            </div>
          </div>
        </td>
        <td><?= e($doc['dept_name']) ?></td>
        <td><?= e($doc['specialization'] ?? '—') ?></td>
        <td><?= $doc['experience_years'] ?> yrs</td>
        <td>₹<?= number_format($doc['consultation_fee'],0) ?></td>
        <td>
          <span class="badge-pill <?= $doc['status'] ?>" id="status-badge-<?= $doc['id'] ?>"><?= ucfirst($doc['status']) ?></span>
        </td>
        <td>
          <div style="display:flex;gap:6px">
            <?php if($doc['status'] === 'pending'): ?>
<a href="?action=approve&id=<?= $doc['id'] ?>"
   class="btn-sm-action success"
   title="Approve">
   <i class="bi bi-check-lg"></i>
</a>
<?php endif; ?>
            <button
id="toggle-btn-<?= $doc['id'] ?>"
class="btn-sm-action success"
title="Toggle Status"
onclick="toggleStatus(<?= $doc['id'] ?>)">
              <i class="bi bi-toggle-on"></i>
            </button>
            
            <button class="btn-sm-action danger" title="Delete" onclick="deleteDoctor(<?= $doc['id'] ?>, '<?= e($doc['name']) ?>')">
              <i class="bi bi-trash"></i>
            </button>
          </div>
        </td>
      </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Doctor Modal -->
<div class="modal-custom" id="add-doctor-modal">
  <div class="modal-box" style="max-width:620px">
    <div class="modal-header-custom">
      <h5><i class="bi bi-person-plus me-2 text-primary"></i>Add New Doctor</h5>
      <button class="modal-close" onclick="closeModal('add-doctor-modal')">&times;</button>
    </div>
    <div class="modal-body-custom">
      <form method="POST">
        <input type="hidden" name="add_doctor" value="1">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Full Name *</label><input type="text" name="name" class="form-control-custom" required></div>
          <div class="col-md-6"><label class="form-label">Email *</label><input type="email" name="email" class="form-control-custom" required></div>
          <div class="col-md-6"><label class="form-label">Phone</label><input type="tel" name="phone" class="form-control-custom"></div>
          <div class="col-md-6"><label class="form-label">Password</label><input type="password" name="password" class="form-control-custom" placeholder="Default: Doctor@123"></div>
          <div class="col-md-6">
            <label class="form-label">Department *</label>
            <select name="department_id" class="form-control-custom" required>
              <option value="">Select Department</option>
              <?php foreach($departments as $dep): ?>
              <option value="<?= $dep['id'] ?>"><?= e($dep['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6"><label class="form-label">Specialization *</label><input type="text" name="specialization" class="form-control-custom" required></div>
          <div class="col-12"><label class="form-label">Qualification</label><input type="text" name="qualification" class="form-control-custom" placeholder="MBBS, MD, etc."></div>
          <div class="col-md-6"><label class="form-label">Experience (years)</label><input type="number" name="experience_years" class="form-control-custom" min="0" value="0"></div>
          <div class="col-md-6"><label class="form-label">Consultation Fee (₹)</label><input type="number" name="consultation_fee" class="form-control-custom" min="0" step="50" value="500"></div>
          <div class="col-md-6"><label class="form-label">License Number</label><input type="text" name="license_number" class="form-control-custom"></div>
          <div class="col-12"><label class="form-label">Bio</label><textarea name="bio" class="form-control-custom" rows="3" placeholder="Brief professional description…"></textarea></div>
        </div>
        <div class="modal-footer-custom" style="padding:20px 0 0">
          <button type="button" class="btn-outline-custom" onclick="closeModal('add-doctor-modal')">Cancel</button>
          <button type="submit" class="btn-primary-custom"><i class="bi bi-check2"></i> Add Doctor</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
initTableSearch('search-input', 'doctors-table');


async function deleteDoctor(id, name) {
  confirmAction(`Are you sure you want to delete <strong>${name}</strong>? This action cannot be undone.`, async () => {
    const res = await ajaxPost('manage_doctors.php', { action: 'delete', id });
    if (res.success) {
      document.getElementById('doctor-row-' + id)?.remove();
      showToast('Deleted', res.message, 'success');
    } else {
      showToast('Error', res.message, 'error');
    }
  });
}

async function toggleStatus(id) {

    const badge = document.getElementById('status-badge-' + id);

    const currentStatus =
        badge.textContent.trim().toLowerCase();

    const res = await ajaxPost(
        'manage_doctors.php',
        {
            action: 'toggle_status',
            id: id,
            status: currentStatus
        }
    );

    if (res.success) {

        badge.textContent =
            res.new_status.charAt(0).toUpperCase() +
            res.new_status.slice(1);

        badge.className =
            'badge-pill ' + res.new_status;

        showToast(
            'Updated',
            res.message,
            'success'
        );
    }
}


</script>
<?php include '../includes/layout_footer.php'; ?>
