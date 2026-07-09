<?php
require_once '../config.php';
requireAuth('admin');

$db = getDB();

// Handle AJAX status update
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action'])
) {
    $action = $_POST['action'];
    $id     = (int)$_POST['id'];

    if ($action === 'update_status') {
        $status = $_POST['status'];
        $validStatuses = ['pending','approved','rejected','completed','cancelled'];
        if (in_array($status, $validStatuses)) {
            $db->prepare("UPDATE appointments SET status = ?, updated_at = NOW() WHERE id = ?")->execute([$status, $id]);
            // ── Fire email + in-app notifications to all parties ──
            notifyAppointmentStatusChange($id, $status);
            jsonResponse(true, "Appointment $status successfully.", ['status' => $status]);
        }
        jsonResponse(false, 'Invalid status.');
    }
}

// Filters
$statusFilter = $_GET['status'] ?? '';
$dateFilter   = $_GET['date'] ?? '';
$search       = $_GET['search'] ?? '';

$where = ['1=1'];
$params = [];
if ($statusFilter) { $where[] = 'a.status = ?'; $params[] = $statusFilter; }
if ($dateFilter)   { $where[] = 'a.appointment_date = ?'; $params[] = $dateFilter; }
if ($search) {
    $where[] = '(u.name LIKE ? OR du.name LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%";
}

$whereSQL = implode(' AND ', $where);
$stmt = $db->prepare("
    SELECT a.*, u.name AS patient_name, du.name AS doctor_name, dep.name AS dept
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users du ON d.user_id = du.id
    JOIN departments dep ON a.department_id = dep.id
    WHERE $whereSQL
    ORDER BY a.appointment_date DESC, a.appointment_time ASC
");
$stmt->execute($params);
$appointments = $stmt->fetchAll();

$pageTitle  = 'Appointments';
$activeMenu = 'appointments';
include '../includes/layout_header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
  <div>
    <h4 style="font-family:var(--font-display);font-weight:800;margin:0">All Appointments</h4>
    <p style="color:#64748B;font-size:.875rem;margin:0"><?= count($appointments) ?> results</p>
  </div>
</div>

<!-- Filters -->
<div class="card mb-4">
  <div class="card-body" style="padding:16px 20px">
    <form method="GET" class="row g-3 align-items-end">
      <div class="col-md-3">
        <label class="form-label" style="font-size:.8rem">Search Patient / Doctor</label>
        <input type="search" name="search" class="form-control-custom" value="<?= e($search) ?>" placeholder="Type to search…">
      </div>
      <div class="col-md-2">
        <label class="form-label" style="font-size:.8rem">Status</label>
        <select name="status" class="form-control-custom">
          <option value="">All</option>
          <?php foreach(['pending','approved','rejected','completed','cancelled'] as $s): ?>
          <option value="<?= $s ?>" <?= $statusFilter===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label" style="font-size:.8rem">Date</label>
        <input type="date" name="date" class="form-control-custom" value="<?= e($dateFilter) ?>">
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn-primary-custom w-100"><i class="bi bi-filter"></i> Filter</button>
      </div>
      <div class="col-md-1">
        <a href="appointments.php" class="btn-outline-custom w-100" style="display:block;text-align:center">Clear</a>
      </div>
    </form>
  </div>
</div>

<!-- Table -->
<div class="card">
  <div class="table-responsive">
    <table class="data-table">
      <thead><tr>
        <th>ID</th><th>Patient</th><th>Doctor</th><th>Department</th>
        <th>Date & Time</th><th>Status</th><th>Actions</th>
      </tr></thead>
      <tbody>
      <?php if (empty($appointments)): ?>
      <tr><td colspan="7"><div class="empty-state"><i class="bi bi-calendar-x"></i><p>No appointments found.</p></div></td></tr>
      <?php else: foreach($appointments as $a): ?>
      <tr id="row-<?= $a['id'] ?>">
        <td style="color:#94A3B8;font-size:.8rem">#<?= $a['id'] ?></td>
        <td><strong><?= e($a['patient_name']) ?></strong></td>
        <td><?= e($a['doctor_name']) ?></td>
        <td style="font-size:.82rem;color:#64748B"><?= e($a['dept']) ?></td>
        <td>
          <div style="font-weight:600;font-size:.875rem"><?= date('d M Y', strtotime($a['appointment_date'])) ?></div>
          <div style="font-size:.75rem;color:#94A3B8"><?= date('h:i A', strtotime($a['appointment_time'])) ?></div>
        </td>
        <td><span class="badge-pill <?= e($a['status']) ?>" id="badge-<?= $a['id'] ?>"><?= ucfirst($a['status']) ?></span></td>
        <td>
          <?php if ($a['status'] === 'pending'): ?>
          <div style="display:flex;gap:5px">
            <button class="btn-sm-action success" title="Approve" onclick="updateStatus(<?= $a['id'] ?>, 'approved')">
              <i class="bi bi-check-lg"></i>
            </button>
            <button class="btn-sm-action danger" title="Reject" onclick="updateStatus(<?= $a['id'] ?>, 'rejected')">
              <i class="bi bi-x-lg"></i>
            </button>
          </div>
          <?php elseif ($a['status'] === 'approved'): ?>
          <button class="btn-sm-action success" title="Mark Complete" onclick="updateStatus(<?= $a['id'] ?>, 'completed')">
            <i class="bi bi-check2-all"></i>
          </button>
          <?php else: ?>
          <span style="font-size:.78rem;color:#94A3B8">—</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
async function updateStatus(id, status) {
  const labels = { approved: 'approve', rejected: 'reject', completed: 'complete' };
  confirmAction(`Are you sure you want to <strong>${labels[status] || status}</strong> appointment #${id}?`, async () => {
    const res = await ajaxPost('appointments.php', { action: 'update_status', id, status });
    if (res.success) {
      const badge = document.getElementById('badge-' + id);
      const statusColors = { pending:'pending', approved:'approved', rejected:'rejected', completed:'completed', cancelled:'cancelled' };
      badge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
      badge.className = 'badge-pill ' + statusColors[status];
      // Update action buttons
      const row = document.getElementById('row-' + id);
      row.cells[6].innerHTML = status === 'approved' ?
        `<button class="btn-sm-action success" title="Mark Complete" onclick="updateStatus(${id},'completed')"><i class="bi bi-check2-all"></i></button>` :
        `<span style="font-size:.78rem;color:#94A3B8">—</span>`;
      showToast('Updated', res.message, 'success');
    }
  });
}
</script>
<?php include '../includes/layout_footer.php'; ?>
