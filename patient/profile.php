<?php
require_once '../config.php';
requireAuth('patient');
$db = getDB();

$user = $db->prepare("SELECT * FROM users WHERE id=?"); $user->execute([$_SESSION['user_id']]); $user=$user->fetch();
$pt   = $db->prepare("SELECT * FROM patients WHERE user_id=?"); $pt->execute([$_SESSION['user_id']]); $pt=$pt->fetch();
$msg  = $msgType = '';

// Auto-create patient record if missing
if (!$pt) {
    $db->prepare("INSERT INTO patients (user_id) VALUES (?)")->execute([$_SESSION['user_id']]);
    $pt = $db->prepare("SELECT * FROM patients WHERE user_id=?"); $pt->execute([$_SESSION['user_id']]); $pt=$pt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim($_POST['name'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $dob       = $_POST['date_of_birth'] ?: null;
    $gender    = $_POST['gender'] ?? null;
    $blood     = $_POST['blood_group'] ?? null;
    $address   = trim($_POST['address'] ?? '');
    $emergName = trim($_POST['emergency_contact'] ?? '');
    $emergPh   = trim($_POST['emergency_phone'] ?? '');
    $allergies = trim($_POST['allergies'] ?? '');
    $chronic   = trim($_POST['chronic_diseases'] ?? '');
    $currPw    = $_POST['current_password'] ?? '';
    $newPw     = $_POST['new_password'] ?? '';
    $confPw    = $_POST['confirm_password'] ?? '';

    if (!$name) { $msg='Name is required.'; $msgType='error'; }
    else {
        $db->prepare("UPDATE users SET name=?,phone=? WHERE id=?")->execute([$name,$phone,$_SESSION['user_id']]);
        $db->prepare("UPDATE patients SET date_of_birth=?,gender=?,blood_group=?,address=?,emergency_contact=?,emergency_phone=?,allergies=?,chronic_diseases=? WHERE user_id=?")
           ->execute([$dob,$gender,$blood,$address,$emergName,$emergPh,$allergies,$chronic,$_SESSION['user_id']]);
        $_SESSION['name'] = $name;
        $msg = 'Profile updated successfully.'; $msgType = 'success';

        if ($newPw) {
            if (!password_verify($currPw,$user['password']))  { $msg='Current password is incorrect.'; $msgType='error'; }
            elseif (strlen($newPw)<8) { $msg='Password min 8 characters.'; $msgType='error'; }
            elseif ($newPw!==$confPw) { $msg='Passwords do not match.'; $msgType='error'; }
            else { $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($newPw,PASSWORD_BCRYPT,['cost'=>PASSWORD_COST]),$_SESSION['user_id']]); $msg='Profile & password updated.'; $msgType='success'; }
        }
        // Re-fetch
        $user=$db->prepare("SELECT * FROM users WHERE id=?"); $user->execute([$_SESSION['user_id']]); $user=$user->fetch();
        $pt  =$db->prepare("SELECT * FROM patients WHERE user_id=?"); $pt->execute([$_SESSION['user_id']]); $pt=$pt->fetch();
    }
}

$pageTitle = 'My Profile'; $activeMenu = 'profile';
include '../includes/layout_header.php';
?>

<?php if($msg): ?>
<div style="background:<?= $msgType==='success'?'#DCFCE7':'#FEE2E2' ?>;border:1px solid <?= $msgType==='success'?'#BBF7D0':'#FECACA' ?>;color:<?= $msgType==='success'?'#166534':'#991B1B' ?>;padding:12px 16px;border-radius:10px;margin-bottom:20px;font-size:.875rem;display:flex;align-items:center;gap:8px">
  <i class="bi bi-<?= $msgType==='success'?'check-circle':'x-circle' ?>-fill"></i> <?= e($msg) ?>
</div>
<?php endif; ?>

<div class="row g-4">
  <!-- Sidebar Card -->
  <div class="col-lg-4">
    <div class="profile-card mb-4">
      <div class="avatar-placeholder" style="width:80px;height:80px;font-size:2rem;margin-bottom:14px;background:rgba(255,255,255,.2)">
        <?= strtoupper(substr($user['name'],0,2)) ?>
      </div>
      <h4 style="font-weight:800;margin-bottom:4px"><?= e($user['name']) ?></h4>
      <p style="opacity:.8;font-size:.875rem">Patient</p>
    </div>
    <div class="card">
      <div class="card-body" style="display:flex;flex-direction:column;gap:12px">
        <?php foreach([
          ['bi-envelope','Email',$user['email']],
          ['bi-telephone','Phone',$user['phone']??'Not set'],
          ['bi-calendar3','Date of Birth',$pt['date_of_birth']?date('d M Y',strtotime($pt['date_of_birth'])):'Not set'],
          ['bi-gender-ambiguous','Gender',ucfirst($pt['gender']??'Not set')],
          ['bi-droplet-fill','Blood Group',$pt['blood_group']??'Not set'],
        ] as [$icon,$lbl,$val]): ?>
        <div style="display:flex;align-items:flex-start;gap:10px">
          <i class="bi <?= $icon ?>" style="color:#94A3B8;margin-top:2px;width:16px"></i>
          <div>
            <div style="font-size:.72rem;color:#94A3B8;font-weight:600"><?= $lbl ?></div>
            <div style="font-size:.875rem;font-weight:500"><?= e($val) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Edit Form -->
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header"><h5>Edit Profile</h5></div>
      <div class="card-body">
        <form method="POST">
          <h6 style="font-weight:700;margin-bottom:16px;color:#374151;font-size:.85rem;text-transform:uppercase;letter-spacing:.05em">Personal Information</h6>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Full Name *</label><input type="text" name="name" class="form-control-custom" value="<?= e($user['name']) ?>" required></div>
            <div class="col-md-6"><label class="form-label">Phone</label><input type="tel" name="phone" class="form-control-custom" value="<?= e($user['phone']??'') ?>"></div>
            <div class="col-md-4"><label class="form-label">Date of Birth</label><input type="date" name="date_of_birth" class="form-control-custom" value="<?= e($pt['date_of_birth']??'') ?>" max="<?= date('Y-m-d') ?>"></div>
            <div class="col-md-4">
              <label class="form-label">Gender</label>
              <select name="gender" class="form-control-custom">
                <option value="">Select</option>
                <?php foreach(['male'=>'Male','female'=>'Female','other'=>'Other'] as $v=>$l): ?>
                <option value="<?= $v ?>" <?= ($pt['gender']??'')===$v?'selected':'' ?>><?= $l ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Blood Group</label>
              <select name="blood_group" class="form-control-custom">
                <option value="">Select</option>
                <?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                <option value="<?= $bg ?>" <?= ($pt['blood_group']??'')===$bg?'selected':'' ?>><?= $bg ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12"><label class="form-label">Address</label><textarea name="address" class="form-control-custom" rows="2"><?= e($pt['address']??'') ?></textarea></div>
          </div>

          <div class="divider"></div>
          <h6 style="font-weight:700;margin-bottom:16px;color:#374151;font-size:.85rem;text-transform:uppercase;letter-spacing:.05em">Emergency Contact</h6>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Contact Name</label><input type="text" name="emergency_contact" class="form-control-custom" value="<?= e($pt['emergency_contact']??'') ?>"></div>
            <div class="col-md-6"><label class="form-label">Contact Phone</label><input type="tel" name="emergency_phone" class="form-control-custom" value="<?= e($pt['emergency_phone']??'') ?>"></div>
          </div>

          <div class="divider"></div>
          <h6 style="font-weight:700;margin-bottom:16px;color:#374151;font-size:.85rem;text-transform:uppercase;letter-spacing:.05em">Medical History</h6>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Allergies</label><textarea name="allergies" class="form-control-custom" rows="2" placeholder="e.g. Penicillin, Peanuts…"><?= e($pt['allergies']??'') ?></textarea></div>
            <div class="col-md-6"><label class="form-label">Chronic Diseases</label><textarea name="chronic_diseases" class="form-control-custom" rows="2" placeholder="e.g. Diabetes, Hypertension…"><?= e($pt['chronic_diseases']??'') ?></textarea></div>
          </div>

          <div class="divider"></div>
          <h6 style="font-weight:700;margin-bottom:16px;color:#374151;font-size:.85rem;text-transform:uppercase;letter-spacing:.05em">Change Password <span style="font-weight:400;color:#94A3B8">(leave blank to keep)</span></h6>
          <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Current Password</label><input type="password" name="current_password" class="form-control-custom"></div>
            <div class="col-md-4"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-control-custom"></div>
            <div class="col-md-4"><label class="form-label">Confirm Password</label><input type="password" name="confirm_password" class="form-control-custom"></div>
          </div>

          <div style="margin-top:24px">
            <button type="submit" class="btn-primary-custom"><i class="bi bi-check2"></i> Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php include '../includes/layout_footer.php'; ?>
