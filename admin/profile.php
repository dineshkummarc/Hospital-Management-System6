<?php
require_once '../config.php';
requireAuth('admin');
$db  = getDB();
$msg = $msgType = '';

$user = $db->prepare("SELECT * FROM users WHERE id=?");
$user->execute([$_SESSION['user_id']]);
$user = $user->fetch();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $name  = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $currPw = $_POST['current_password'] ?? '';
    $newPw  = $_POST['new_password'] ?? '';
    $confPw = $_POST['confirm_password'] ?? '';

    if (!$name) { $msg='Name is required.'; $msgType='error'; }
    else {
        $db->prepare("UPDATE users SET name=?, phone=? WHERE id=?")->execute([$name,$phone,$_SESSION['user_id']]);
        $_SESSION['name'] = $name;

        if ($newPw) {
            if (!password_verify($currPw, $user['password'])) { $msg='Current password is incorrect.'; $msgType='error'; }
            elseif (strlen($newPw)<8) { $msg='New password must be at least 8 characters.'; $msgType='error'; }
            elseif ($newPw!==$confPw) { $msg='Passwords do not match.'; $msgType='error'; }
            else {
                $hash = password_hash($newPw, PASSWORD_BCRYPT, ['cost'=>PASSWORD_COST]);
                $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash,$_SESSION['user_id']]);
                $msg='Profile and password updated.'; $msgType='success';
            }
        } else { $msg='Profile updated.'; $msgType='success'; }

        // Re-fetch
        $u2 = $db->prepare("SELECT * FROM users WHERE id=?"); $u2->execute([$_SESSION['user_id']]); $user=$u2->fetch();
    }
}

$pageTitle='Profile Settings'; $activeMenu='profile';
include '../includes/layout_header.php';
?>

<?php if($msg): ?>
<div style="background:<?= $msgType==='success'?'#DCFCE7':'#FEE2E2' ?>;border:1px solid <?= $msgType==='success'?'#BBF7D0':'#FECACA' ?>;color:<?= $msgType==='success'?'#166534':'#991B1B' ?>;padding:12px 16px;border-radius:10px;margin-bottom:20px;font-size:.875rem;display:flex;align-items:center;gap:8px">
  <i class="bi bi-<?= $msgType==='success'?'check-circle':'x-circle' ?>-fill"></i> <?= e($msg) ?>
</div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-lg-4">
    <div class="card text-center" style="padding:32px 24px">
      <div class="avatar-placeholder" style="width:80px;height:80px;font-size:2rem;margin:0 auto 16px">
        <?= strtoupper(substr($user['name'],0,2)) ?>
      </div>
      <h5 style="font-weight:700;margin-bottom:4px"><?= e($user['name']) ?></h5>
      <span class="badge-pill active" style="display:inline-flex;margin:0 auto">Administrator</span>
      <div class="divider"></div>
      <div style="text-align:left;display:flex;flex-direction:column;gap:12px">
        <div style="display:flex;align-items:center;gap:10px;font-size:.875rem">
          <i class="bi bi-envelope" style="color:#94A3B8;width:18px"></i>
          <span><?= e($user['email']) ?></span>
        </div>
        <div style="display:flex;align-items:center;gap:10px;font-size:.875rem">
          <i class="bi bi-telephone" style="color:#94A3B8;width:18px"></i>
          <span><?= e($user['phone'] ?? 'Not set') ?></span>
        </div>
        <div style="display:flex;align-items:center;gap:10px;font-size:.875rem">
          <i class="bi bi-calendar" style="color:#94A3B8;width:18px"></i>
          <span>Joined <?= date('d M Y', strtotime($user['created_at'])) ?></span>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card">
      <div class="card-header"><h5>Edit Profile</h5></div>
      <div class="card-body">
        <form method="POST">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Full Name *</label>
              <input type="text" name="name" class="form-control-custom" value="<?= e($user['name']) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input type="email" class="form-control-custom" value="<?= e($user['email']) ?>" disabled style="background:#F8FAFC;color:#94A3B8">
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone</label>
              <input type="tel" name="phone" class="form-control-custom" value="<?= e($user['phone'] ?? '') ?>">
            </div>
          </div>

          <div class="divider"></div>
          <h6 style="font-weight:700;margin-bottom:16px;color:#374151">Change Password <span style="font-weight:400;color:#94A3B8;font-size:.8rem">(leave blank to keep current)</span></h6>

          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Current Password</label>
              <input type="password" name="current_password" class="form-control-custom">
            </div>
            <div class="col-md-4">
              <label class="form-label">New Password</label>
              <input type="password" name="new_password" class="form-control-custom">
            </div>
            <div class="col-md-4">
              <label class="form-label">Confirm Password</label>
              <input type="password" name="confirm_password" class="form-control-custom">
            </div>
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
