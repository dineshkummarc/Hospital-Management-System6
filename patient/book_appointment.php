<?php
require_once '../config.php';
requireAuth('patient');
$db = getDB();

$ptStmt = $db->prepare("SELECT id FROM patients WHERE user_id=?"); $ptStmt->execute([$_SESSION['user_id']]); $patient=$ptStmt->fetch();
$ptId   = $patient['id'] ?? 0;
if (!$ptId) { redirect(APP_URL.'/patient/profile.php?setup=1'); }

$msg = $msgType = '';

// AJAX: get doctors by department
if (!empty($_GET['ajax']) && $_GET['ajax']==='doctors') {
    header('Content-Type: application/json');
    $deptId = (int)$_GET['dept_id'];
    $docs   = $db->prepare("SELECT d.id, u.name, d.specialization, d.consultation_fee, d.experience_years FROM doctors d JOIN users u ON d.user_id=u.id WHERE d.department_id=? AND d.status='active'");
    $docs->execute([$deptId]); echo json_encode($docs->fetchAll()); exit;
}

// Submit booking
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $deptId  = (int)$_POST['department_id'];
    $docId   = (int)$_POST['doctor_id'];
    $date    = $_POST['appointment_date'] ?? '';
    $time    = $_POST['appointment_time'] ?? '';
    $reason  = trim($_POST['reason'] ?? '');

    $errors = [];
    if (!$deptId) $errors[] = 'Select a department.';
    if (!$docId)  $errors[] = 'Select a doctor.';
    if (!$date || $date < date('Y-m-d')) $errors[] = 'Select a valid future date.';
    if (!$time)   $errors[] = 'Select a time slot.';

    // Check for duplicate
    if (empty($errors)) {
        $dup = $db->prepare("SELECT id FROM appointments WHERE patient_id=? AND doctor_id=? AND appointment_date=? AND status NOT IN ('rejected','cancelled')");
        $dup->execute([$ptId,$docId,$date]);
        if ($dup->fetch()) $errors[] = 'You already have an appointment with this doctor on this date.';
    }

    if (empty($errors)) {
        try {
            // Token number for the day
            $tok = $db->prepare("SELECT COUNT(*)+1 FROM appointments WHERE doctor_id=? AND appointment_date=?");
            $tok->execute([$docId,$date]);
            $token = $tok->fetchColumn();

            $db->prepare("INSERT INTO appointments (patient_id,doctor_id,department_id,appointment_date,appointment_time,reason,token_number) VALUES (?,?,?,?,?,?,?)")
               ->execute([$ptId,$docId,$deptId,$date,$time,$reason,$token]);
            $newApptId = (int)$db->lastInsertId();

            // ── Fire email + in-app notifications ─────────────
            notifyAppointmentBooked($newApptId);

            $msg = 'Appointment booked successfully! Token #'.$token.'. Please wait for doctor approval.';
            $msgType = 'success';
        } catch(PDOException $e) {
            $msg = 'Booking failed. Please try again.'; $msgType = 'error';
        }
    } else {
        $msg = implode(' ', $errors); $msgType = 'error';
    }
}

$departments = $db->query("SELECT * FROM departments WHERE status='active' ORDER BY name")->fetchAll();

$pageTitle = 'Book Appointment'; $activeMenu = 'book';
include '../includes/layout_header.php';
?>

<?php if ($msg): ?>
<div style="background:<?= $msgType==='success'?'#DCFCE7':'#FEE2E2' ?>;border:1px solid <?= $msgType==='success'?'#BBF7D0':'#FECACA' ?>;color:<?= $msgType==='success'?'#166534':'#991B1B' ?>;padding:14px 18px;border-radius:12px;margin-bottom:24px;display:flex;align-items:center;gap:10px;font-size:.875rem">
  <i class="bi bi-<?= $msgType==='success'?'check-circle-fill':'exclamation-circle-fill' ?>"></i>
  <?= e($msg) ?>
  <?php if($msgType==='success'): ?><a href="appointments.php" style="margin-left:auto;font-weight:600;text-decoration:none;color:inherit">View Appointments →</a><?php endif; ?>
</div>
<?php endif; ?>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">
        <h5><i class="bi bi-calendar-plus me-2 text-primary"></i>Book New Appointment</h5>
      </div>
      <div class="card-body">
        <form method="POST" id="book-form">
          <!-- Step 1: Department -->
          <div style="background:var(--primary-light);border-radius:12px;padding:16px;margin-bottom:24px;display:flex;align-items:center;gap:12px">
            <div style="width:36px;height:36px;background:var(--primary);border-radius:8px;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;flex-shrink:0">1</div>
            <div style="font-weight:600;font-size:.9rem">Select Department</div>
          </div>
          <div class="row g-4 mb-4" id="dept-grid">
            <?php foreach($departments as $dep): ?>
            <div class="col-md-4 col-6">
              <label style="cursor:pointer;display:block">
                <input type="radio" name="department_id" value="<?= $dep['id'] ?>" class="d-none dept-radio" <?= isset($_POST['department_id'])&&$_POST['department_id']==$dep['id']?'checked':'' ?> required>
                <div class="dept-card" style="border:2px solid #E2E8F0;border-radius:12px;padding:16px;text-align:center;transition:all .2s">
                  <i class="bi bi-<?= e($dep['icon']??'hospital') ?>" style="font-size:1.6rem;color:#94A3B8;display:block;margin-bottom:8px"></i>
                  <div style="font-size:.82rem;font-weight:600;color:#374151"><?= e($dep['name']) ?></div>
                </div>
              </label>
            </div>
            <?php endforeach; ?>
          </div>

          <!-- Step 2: Doctor -->
          <div style="background:var(--primary-light);border-radius:12px;padding:16px;margin-bottom:24px;display:flex;align-items:center;gap:12px">
            <div style="width:36px;height:36px;background:var(--primary);border-radius:8px;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;flex-shrink:0">2</div>
            <div style="font-weight:600;font-size:.9rem">Choose Doctor</div>
          </div>
          <div id="doctors-container" style="margin-bottom:24px">
            <p style="color:#94A3B8;font-size:.875rem;text-align:center;padding:20px">Please select a department first.</p>
          </div>
          <input type="hidden" name="doctor_id" id="selected-doctor-id">

          <!-- Step 3: Date & Time -->
          <div style="background:var(--primary-light);border-radius:12px;padding:16px;margin-bottom:24px;display:flex;align-items:center;gap:12px">
            <div style="width:36px;height:36px;background:var(--primary);border-radius:8px;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;flex-shrink:0">3</div>
            <div style="font-weight:600;font-size:.9rem">Select Date & Time</div>
          </div>
          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label class="form-label">Appointment Date *</label>
              <input type="date" name="appointment_date" id="appt-date" class="form-control-custom" min="<?= date('Y-m-d') ?>" value="<?= e($_POST['appointment_date']??'') ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Time Slot *</label>
              <select name="appointment_time" id="appt-time" class="form-control-custom" required>
                <option value="">Select time</option>
                <?php
                $slots = ['09:00','09:30','10:00','10:30','11:00','11:30','12:00','14:00','14:30','15:00','15:30','16:00','16:30','17:00'];
                foreach($slots as $s): ?>
                <option value="<?= $s ?>:00" <?= isset($_POST['appointment_time'])&&$_POST['appointment_time']===$s.':00'?'selected':'' ?>>
                  <?= date('h:i A',strtotime($s)) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <!-- Step 4: Reason -->
          <div style="background:var(--primary-light);border-radius:12px;padding:16px;margin-bottom:24px;display:flex;align-items:center;gap:12px">
            <div style="width:36px;height:36px;background:var(--primary);border-radius:8px;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;flex-shrink:0">4</div>
            <div style="font-weight:600;font-size:.9rem">Reason for Visit</div>
          </div>
          <div class="form-group">
            <textarea name="reason" id="appt-reason" class="form-control-custom" rows="3" placeholder="Briefly describe your symptoms or reason for visiting…"><?= e($_POST['reason']??'') ?></textarea>
          </div>

          <button type="submit" class="btn-primary-custom w-100" style="padding:14px;font-size:1rem;justify-content:center">
            <i class="bi bi-calendar-check"></i> Confirm Appointment
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<style>
.dept-radio:checked + .dept-card { border-color: var(--primary); background: var(--primary-light); }
.dept-radio:checked + .dept-card i { color: var(--primary); }
.doctor-radio:checked + .doc-card { border-color: var(--primary); background: var(--primary-light); }
</style>
<script>
document.querySelectorAll('.dept-radio').forEach(radio => {
  radio.addEventListener('change', () => loadDoctors(radio.value));
  if (radio.checked) loadDoctors(radio.value);
});

async function loadDoctors(deptId) {
  document.getElementById('doctors-container').innerHTML = '<p style="color:#94A3B8;font-size:.875rem;text-align:center;padding:20px"><span class="spinner" style="border-color:rgba(0,0,0,.2);border-top-color:var(--primary)"></span></p>';
  const res  = await fetch(`book_appointment.php?ajax=doctors&dept_id=${deptId}`);
  const docs = await res.json();
  if (!docs.length) { document.getElementById('doctors-container').innerHTML = '<p style="color:#94A3B8;text-align:center;padding:16px">No doctors available in this department.</p>'; return; }
  document.getElementById('doctors-container').innerHTML = `<div class="row g-3">${docs.map(d=>`
    <div class="col-md-6">
      <label style="cursor:pointer;display:block">
        <input type="radio" name="_doc_radio" value="${d.id}" class="d-none doctor-radio">
        <div class="doc-card" style="border:2px solid #E2E8F0;border-radius:12px;padding:16px;transition:all .2s;display:flex;align-items:center;gap:12px">
          <div class="avatar-placeholder" style="width:48px;height:48px;font-size:1rem;flex-shrink:0">${d.name.substring(0,2).toUpperCase()}</div>
          <div>
            <div style="font-weight:700;font-size:.875rem">${d.name}</div>
            <div style="font-size:.78rem;color:var(--primary);font-weight:500">${d.specialization}</div>
            <div style="font-size:.75rem;color:#94A3B8">${d.experience_years} yrs · ₹${parseFloat(d.consultation_fee).toFixed(0)}</div>
          </div>
        </div>
      </label>
    </div>`).join('')}</div>`;

  document.querySelectorAll('.doctor-radio').forEach(r => {
    r.addEventListener('change', () => {
      document.getElementById('selected-doctor-id').value = r.value;
      document.querySelectorAll('.doc-card').forEach(c => { c.style.borderColor='#E2E8F0'; c.style.background=''; });
      r.nextElementSibling.style.borderColor = 'var(--primary)';
      r.nextElementSibling.style.background  = 'var(--primary-light)';
    });
  });
}
</script>
<?php include '../includes/layout_footer.php'; ?>
