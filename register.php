<?php
require_once 'config.php';
if (!empty($_SESSION['user_id'])) redirect(APP_URL . '/dashboard.php');

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $role     = $_POST['role'] ?? 'patient';

    // Validate
    if (strlen($name) < 3)       $errors[] = 'Name must be at least 3 characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
    if (strlen($password) < 8)   $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm)  $errors[] = 'Passwords do not match.';
    if (!in_array($role, ['patient','doctor'])) $role = 'patient';

    if (empty($errors)) {
        try {
            $db = getDB();
            // Check duplicate email
            $check = $db->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch()) {
                $errors[] = 'Email already registered. Please login.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => PASSWORD_COST]);
                $db->beginTransaction();
                $stmt = $db->prepare("INSERT INTO users (name, email, password, role, phone) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $email, $hash, $role, $phone]);
                $userId = $db->lastInsertId();

                if ($role === 'patient') {
                    $dob    = $_POST['dob'] ?? null;
                    $gender = $_POST['gender'] ?? null;
                    $bg     = $_POST['blood_group'] ?? null;
                    $stmt2  = $db->prepare("INSERT INTO patients (user_id, date_of_birth, gender, blood_group) VALUES (?, ?, ?, ?)");
                    $stmt2->execute([$userId, $dob ?: null, $gender ?: null, $bg ?: null]);
                }
                if ($role === 'doctor') {

    $stmt2 = $db->prepare("
        INSERT INTO doctors
        (
            user_id,
            department_id,
            specialization,
            experience_years,
            consultation_fee,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?)
    ");

       $stmt2->execute([
       $userId,
       1,
       'General Physician',
       0,
       0,
       'pending'
      ]);
}
                $db->commit();
                // ── Send welcome email + admin alert ──────────
                notifyUserRegistered($userId);
                $success = 'Account created successfully! You can now login.';
            }
        } catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    $errors[] = $e->getMessage();
}
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register — Smart Hospital</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="auth-page">
  <div class="auth-card" style="max-width:520px;padding:36px 40px">
    <div class="auth-logo">
      <a href="index.php" class="d-block">
        <div class="auth-logo-icon"><i class="bi bi-heart-pulse-fill"></i></div>
      </a>
      <div class="auth-title">Create Account</div>
      <div class="auth-sub">Join Smart Hospital today</div>
    </div>

    <?php if ($errors): ?>
    <div style="background:#FEE2E2;border:1px solid #FECACA;color:#991B1B;padding:12px 16px;border-radius:10px;margin-bottom:20px;font-size:.875rem">
      <ul style="margin:0;padding-left:16px">
        <?php foreach($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div style="background:#DCFCE7;border:1px solid #BBF7D0;color:#166534;padding:14px 16px;border-radius:10px;margin-bottom:20px;font-size:.875rem;display:flex;align-items:center;gap:8px">
      <i class="bi bi-check-circle-fill"></i> <?= e($success) ?>
      <a href="login.php" style="margin-left:auto;font-weight:600;color:#15803D">Login →</a>
    </div>
    <?php endif; ?>

    <form method="POST" id="reg-form">
      <!-- Role Selector -->
      <div class="form-group">
        <label class="form-label">Register as</label>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <?php foreach(['patient'=>['bi-person-heart','Patient'],'doctor'=>['bi-person-badge','Doctor']] as $val => [$icon,$label]): ?>
          <label style="cursor:pointer">
            <input type="radio" name="role" value="<?= $val ?>" class="d-none role-radio" <?= ($_POST['role'] ?? 'patient') === $val ? 'checked' : '' ?>>
            <div class="role-option <?= ($_POST['role'] ?? 'patient') === $val ? 'selected' : '' ?>"
                 style="padding:14px;border-radius:12px;border:2px solid #E2E8F0;text-align:center;transition:all .2s">
              <i class="bi <?= $icon ?>" style="font-size:1.4rem;display:block;margin-bottom:6px"></i>
              <span style="font-size:.875rem;font-weight:600"><?= $label ?></span>
            </div>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-12">
          <div class="form-group" style="margin:0">
            <label class="form-label">Full Name</label>
            <input type="text" name="name" class="form-control-custom" value="<?= e($_POST['name'] ?? '') ?>" placeholder="Dr. John Smith" required>
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group" style="margin:0">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control-custom" value="<?= e($_POST['email'] ?? '') ?>" placeholder="your@email.com" required>
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group" style="margin:0">
            <label class="form-label">Phone</label>
            <input type="tel" name="phone" class="form-control-custom" value="<?= e($_POST['phone'] ?? '') ?>" placeholder="+91 98765 43210">
          </div>
        </div>

        <!-- Patient-only fields -->
        <div id="patient-fields" style="display:contents">
          <div class="col-md-4">
            <div class="form-group" style="margin:0">
              <label class="form-label">Date of Birth</label>
              <input type="date" name="dob" class="form-control-custom" value="<?= e($_POST['dob'] ?? '') ?>" max="<?= date('Y-m-d') ?>">
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group" style="margin:0">
              <label class="form-label">Gender</label>
              <select name="gender" class="form-control-custom">
                <option value="">Select</option>
                <option value="male" <?= ($_POST['gender']??'')==='male'?'selected':'' ?>>Male</option>
                <option value="female" <?= ($_POST['gender']??'')==='female'?'selected':'' ?>>Female</option>
                <option value="other" <?= ($_POST['gender']??'')==='other'?'selected':'' ?>>Other</option>
              </select>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group" style="margin:0">
              <label class="form-label">Blood Group</label>
              <select name="blood_group" class="form-control-custom">
                <option value="">Select</option>
                <?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                <option value="<?= $bg ?>" <?= ($_POST['blood_group']??'')===$bg?'selected':'' ?>><?= $bg ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>

        <div class="col-md-6">
          <div class="form-group" style="margin:0">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control-custom" placeholder="Min 8 characters" required>
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group" style="margin:0">
            <label class="form-label">Confirm Password</label>
            <input type="password" name="confirm_password" class="form-control-custom" placeholder="Repeat password" required>
          </div>
        </div>
      </div>

      <div style="margin-top:8px;margin-bottom:20px">
        <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;font-size:.82rem;color:#64748B">
          <input type="checkbox" required style="accent-color:var(--primary);margin-top:2px">
          I agree to the <a href="#" style="color:var(--primary)">Terms of Service</a> and <a href="#" style="color:var(--primary)">Privacy Policy</a>
        </label>
      </div>

      <button type="submit" class="btn-primary-custom w-100" style="padding:13px;font-size:.95rem;justify-content:center">
        <i class="bi bi-person-plus"></i> Create Account
      </button>
    </form>

    <div class="divider"></div>
    <p style="text-align:center;font-size:.875rem;color:#64748B">
      Already have an account?
      <a href="login.php" style="color:var(--primary);font-weight:600;text-decoration:none">Sign In</a>
    </p>
  </div>
</div>

<style>
.role-option.selected { border-color: var(--primary) !important; background: var(--primary-light); color: var(--primary); }
</style>
<script>
document.querySelectorAll('.role-radio').forEach(radio => {
  radio.addEventListener('change', () => {
    document.querySelectorAll('.role-option').forEach(el => el.classList.remove('selected'));
    radio.nextElementSibling.classList.add('selected');
    const isPatient = radio.value === 'patient';
    document.getElementById('patient-fields').style.display = isPatient ? 'contents' : 'none';
  });
});
</script>
</body>
</html>
