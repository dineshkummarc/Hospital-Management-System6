<?php
require_once '../config.php';
requireAuth('doctor');
$db = getDB();

$user = $db->prepare("SELECT * FROM users WHERE id=?"); $user->execute([$_SESSION['user_id']]); $user=$user->fetch();
$doc  = $db->prepare("SELECT d.*, dep.name AS dept_name FROM doctors d JOIN departments dep ON d.department_id=dep.id WHERE d.user_id=?"); $doc->execute([$_SESSION['user_id']]); $doc=$doc->fetch();

$departments = $db->query("SELECT * FROM departments WHERE status='active' ORDER BY name")->fetchAll();
$msg = $msgType = '';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $name    = trim($_POST['name']??'');
    $phone   = trim($_POST['phone']??'');
    $spec    = trim($_POST['specialization']??'');
    $qual    = trim($_POST['qualification']??'');
    $exp     = (int)($_POST['experience_years']??0);
    $fee     = (float)($_POST['consultation_fee']??0);
    $bio     = trim($_POST['bio']??'');
    $deptId  = (int)$_POST['department_id'];
    $currPw  = $_POST['current_password']??'';
    $newPw   = $_POST['new_password']??'';
    $confPw  = $_POST['confirm_password']??'';

    $db->prepare("UPDATE users SET name=?,phone=? WHERE id=?")->execute([$name,$phone,$_SESSION['user_id']]);
    $sql = "
UPDATE doctors
SET
department_id=?,
specialization=?,
qualification=?,
experience_years=?,
consultation_fee=?,
bio=?
WHERE id=?
";

echo "<pre>$sql</pre>";

$db->prepare($sql)->execute([
    $deptId,
    $spec,
    $qual,
    $exp,
    $fee,
    $bio,
    $doc['id']
]);
       
    $_SESSION['name'] = $name;
    $msg = 'Profile updated.'; $msgType = 'success';

    if ($newPw) {
        if (!password_verify($currPw,$user['password'])) { $msg='Wrong current password.'; $msgType='error'; }
        elseif (strlen($newPw)<8) { $msg='Password min 8 chars.'; $msgType='error'; }
        elseif ($newPw!==$confPw) { $msg='Passwords do not match.'; $msgType='error'; }
        else { $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($newPw,PASSWORD_BCRYPT,['cost'=>PASSWORD_COST]),$_SESSION['user_id']]); $msg='Profile & password updated.'; $msgType='success'; }
    }
    // Re-fetch
    $user=$db->prepare("SELECT * FROM users WHERE id=?"); $user->execute([$_SESSION['user_id']]); $user=$user->fetch();
    $doc=$db->prepare("SELECT d.*,dep.name AS dept_name FROM doctors d JOIN departments dep ON d.department_id=dep.id WHERE d.user_id=?"); $doc->execute([$_SESSION['user_id']]); $doc=$doc->fetch();
}

$pageTitle='My Profile'; $activeMenu='profile';
include '../includes/layout_header.php';
?>

<?php if($msg): ?>
<div style="background:<?= $msgType==='success'?'#DCFCE7':'#FEE2E2' ?>;border:1px solid <?= $msgType==='success'?'#BBF7D0':'#FECACA' ?>;color:<?= $msgType==='success'?'#166534':'#991B1B' ?>;padding:12px 16px;border-radius:10px;margin-bottom:20px;font-size:.875rem;display:flex;align-items:center;gap:8px">
  <i class="bi bi-<?= $msgType==='success'?'check-circle':'x-circle' ?>-fill"></i> <?= e($msg) ?>
</div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-lg-4">
    <div class="profile-card mb-4">
      <div class="avatar-placeholder" style="width:80px;height:80px;font-size:2rem;margin-bottom:14px;background:rgba(255,255,255,.2)">
        <?= strtoupper(substr($user['name'],0,2)) ?>
      </div>
      <h4 style="font-weight:800;margin-bottom:4px"><?= e($user['name']) ?></h4>
      <p style="opacity:.8;font-size:.875rem;margin-bottom:10px"><?= e($doc['specialization']??'') ?></p>
      <p style="opacity:.7;font-size:.82rem"><?= e($doc['dept_name']??'') ?></p>
    </div>
    <div class="card">
      <div class="card-body" style="display:flex;flex-direction:column;gap:12px">
        <?php foreach([
          ['bi-envelope','Email',$user['email']],
          ['bi-telephone','Phone',$user['phone']??'Not set'],
          ['bi-award','Experience',($doc['experience_years']??0).' years'],
          ['bi-currency-rupee','Consultation Fee','₹'.number_format($doc['consultation_fee']??0,0)],
          ['bi-shield-check','License',$doc['license_number']??'—'],
        ] as [$icon,$lbl,$val]): ?>
        <div style="display:flex;align-items:flex-start;gap:10px">
          <i class="bi <?= $icon ?>" style="color:#94A3B8;margin-top:2px;width:16px"></i>
          <div><div style="font-size:.72rem;color:#94A3B8;font-weight:600"><?= $lbl ?></div><div style="font-size:.875rem"><?= e($val) ?></div></div>
        </div>
        <?php endforeach; ?>
        <?php if($doc['bio']): ?>
        <div style="padding-top:8px;border-top:1px solid #F1F5F9;font-size:.82rem;color:#475569;line-height:1.6"><?= e($doc['bio']) ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card">
      <div class="card-header"><h5>Edit Profile</h5></div>
      <div class="card-body">
        <form method="POST">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Full Name *</label><input type="text" name="name" class="form-control-custom" value="<?= e($user['name']) ?>" required></div>
            <div class="col-md-6"><label class="form-label">Phone</label><input type="tel" name="phone" class="form-control-custom" value="<?= e($user['phone']??'') ?>"></div>
            <div class="col-md-6">
              <label class="form-label">Department</label>
              <select name="department_id" class="form-control-custom">
                <?php foreach($departments as $dep): ?>
                <option value="<?= $dep['id'] ?>" <?= ($doc['department_id']??0)==$dep['id']?'selected':'' ?>><?= e($dep['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6"><label class="form-label">Specialization</label><input type="text" name="specialization" class="form-control-custom" value="<?= e($doc['specialization']??'') ?>"></div>
            <div class="col-12"><label class="form-label">Qualification</label><input type="text" name="qualification" class="form-control-custom" value="<?= e($doc['qualification']??'') ?>"></div>
            <div class="col-md-6"><label class="form-label">Experience (years)</label><input type="number" name="experience_years" class="form-control-custom" value="<?= $doc['experience_years']??0 ?>" min="0"></div>
            <div class="col-md-6"><label class="form-label">Consultation Fee (₹)</label><input type="number" name="consultation_fee" class="form-control-custom" value="<?= $doc['consultation_fee']??0 ?>" min="0" step="50"></div>
            <div class="col-12"><label class="form-label">Bio</label><textarea name="bio" class="form-control-custom" rows="3"><?= e($doc['bio']??'') ?></textarea></div>
          </div>
          <div class="divider"></div>
          <h6 style="font-weight:700;margin-bottom:16px">Change Password <span style="font-weight:400;color:#94A3B8;font-size:.8rem">(leave blank to keep)</span></h6>
          <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Current Password</label><input type="password" name="current_password" class="form-control-custom"></div>
            <div class="col-md-4"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-control-custom"></div>
            <div class="col-md-4"><label class="form-label">Confirm</label><input type="password" name="confirm_password" class="form-control-custom"></div>
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
