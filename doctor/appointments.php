<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../config.php';
requireAuth('doctor');
$db = getDB();

$docStmt = $db->prepare("SELECT id FROM doctors WHERE user_id=?");
$docStmt->execute([$_SESSION['user_id']]);
$doctor = $docStmt->fetch();
$docId  = $doctor['id'] ?? 0;

// AJAX status update
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action'])
) {
    $action = $_POST['action'];
    $id     = (int)$_POST['id'];
    if ($action === 'update_status') {
        $status = $_POST['status'];
        $valid  = ['approved','rejected','completed'];
        if (in_array($status, $valid)) {
            // Verify this appointment belongs to this doctor
            $check = $db->prepare("SELECT id FROM appointments WHERE id=? AND doctor_id=?");
            $check->execute([$id, $docId]);
            if ($check->fetch()) {
                $db->prepare("UPDATE appointments SET status=?, updated_at=NOW() WHERE id=?")->execute([$status,$id]);
                // ── Fire email + in-app notifications ─────────
                notifyAppointmentStatusChange($id, $status);
                jsonResponse(true, "Appointment $status.", ['status'=>$status]);
            }
        }
        jsonResponse(false, 'Not authorized.');
    }
}

$statusFilter = $_GET['status'] ?? '';
$dateFilter   = $_GET['date'] ?? '';
$where  = ['a.doctor_id=?']; $params = [$docId];
if ($statusFilter) { $where[] = 'a.status=?'; $params[] = $statusFilter; }
if ($dateFilter)   { $where[] = 'a.appointment_date=?'; $params[] = $dateFilter; }

$stmt = $db->prepare("
    SELECT a.*, u.name AS patient_name, u.phone, p.blood_group, p.gender, dep.name AS dept
    FROM appointments a
    JOIN patients p ON a.patient_id=p.id
    JOIN users u ON p.user_id=u.id
    JOIN departments dep ON a.department_id=dep.id
    WHERE ".implode(' AND ',$where)."
    ORDER BY a.appointment_date DESC, a.appointment_time ASC
");
$stmt->execute($params);
$appointments = $stmt->fetchAll();

$pageTitle = 'My Appointments'; $activeMenu = 'appointments';
include '../includes/layout_header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
  <div>
    <h4 style="font-family:var(--font-display);font-weight:800;margin:0">My Appointments</h4>
    <p style="color:#64748B;font-size:.875rem;margin:0"><?= count($appointments) ?> appointments</p>
  </div>
</div>

<!-- Filters -->
<div class="card mb-4">
  <div class="card-body" style="padding:14px 20px">
    <form method="GET" class="d-flex gap-3 flex-wrap align-items-end">
      <div>
        <label class="form-label" style="font-size:.8rem">Status</label>
        <select name="status" class="form-control-custom" style="width:160px">
          <option value="">All</option>
          <?php foreach(['pending','approved','completed','rejected'] as $s): ?>
          <option value="<?= $s ?>" <?= $statusFilter===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="form-label" style="font-size:.8rem">Date</label>
        <input type="date" name="date" class="form-control-custom" value="<?= e($dateFilter) ?>">
      </div>
      <button type="submit" class="btn-primary-custom"><i class="bi bi-filter"></i> Filter</button>
      <a href="appointments.php" class="btn-outline-custom">Clear</a>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="data-table">
      <thead><tr>
        <th>Patient</th><th>Contact</th><th>Blood</th><th>Date & Time</th>
        <th>Reason</th><th>Status</th><th>Actions</th>
      </tr></thead>
      <tbody>
      <?php if (empty($appointments)): ?>
      <tr><td colspan="7"><div class="empty-state"><i class="bi bi-calendar-x"></i><p>No appointments found.</p></div></td></tr>
      <?php else: foreach($appointments as $a): ?>
      <tr id="appt-row-<?= $a['id'] ?>">
        <td>
          <div style="display:flex;align-items:center;gap:10px">
            <div class="avatar-placeholder" style="width:38px;height:38px;font-size:.85rem;flex-shrink:0">
              <?= strtoupper(substr($a['patient_name'],0,2)) ?>
            </div>
            <div>
              <div style="font-weight:600"><?= e($a['patient_name']) ?></div>
              <div style="font-size:.75rem;color:#94A3B8;text-transform:capitalize"><?= e($a['gender']??'') ?></div>
            </div>
          </div>
        </td>
        <td style="font-size:.82rem"><?= e($a['phone']??'—') ?></td>
        <td><?= $a['blood_group']?"<span style='background:#FEE2E2;color:#DC2626;font-size:.72rem;font-weight:700;padding:2px 8px;border-radius:6px'>".e($a['blood_group'])."</span>":'—' ?></td>
        <td>
          <div style="font-weight:600"><?= date('d M Y',strtotime($a['appointment_date'])) ?></div>
          <div style="font-size:.75rem;color:#94A3B8"><?= date('h:i A',strtotime($a['appointment_time'])) ?></div>
        </td>
        <td style="font-size:.82rem;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
          <?= e($a['reason']??'—') ?>
        </td>
        <td><span class="badge-pill <?= $a['status'] ?>" id="appt-badge-<?= $a['id'] ?>"><?= ucfirst($a['status']) ?></span></td>
        <td>
          <div style="display:flex;gap:5px">
            <?php if ($a['status']==='pending'): ?>
            <button class="btn-sm-action success" title="Approve" onclick="changeStatus(<?= $a['id'] ?>,'approved')"><i class="bi bi-check-lg"></i></button>
            <button class="btn-sm-action danger" title="Reject"  onclick="changeStatus(<?= $a['id'] ?>,'rejected')"><i class="bi bi-x-lg"></i></button>
            <?php elseif ($a['status']==='approved'): ?>
            <button class="btn-sm-action success" title="Complete" onclick="changeStatus(<?= $a['id'] ?>,'completed')"><i class="bi bi-check2-all"></i></button>
            <a href="prescriptions.php?appt_id=<?= $a['id'] ?>&patient_id=<?= $a['patient_id'] ?>" class="btn-sm-action" title="Write Rx"><i class="bi bi-pencil-square"></i></a>
            <?php else: ?>
            <span style="font-size:.78rem;color:#94A3B8">—</span>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
async function changeStatus(id, status) {
  const labels = {approved:'approve', rejected:'reject', completed:'mark as complete'};
  confirmAction(`Are you sure you want to <strong>${labels[status]||status}</strong> this appointment?`, async () => {
    const res = await ajaxPost('appointments.php', { action:'update_status', id, status });
    if (res.success) {
      const badge = document.getElementById('appt-badge-'+id);
      badge.textContent = status.charAt(0).toUpperCase()+status.slice(1);
      badge.className = 'badge-pill '+status;
      showToast('Updated', res.message, 'success');
      setTimeout(()=>location.reload(), 900);
    } else showToast('Error', res.message, 'error');
  });
}
</script>
<?php include '../includes/layout_footer.php'; ?>
