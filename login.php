<?php
require_once 'config.php';

if (!empty($_SESSION['user_id'])) {
    redirect(APP_URL . '/dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember']);

    if (empty($email) || empty($password)) {
        $error = 'Please enter your email and password.';
    } else {
        try {
            $db   = getDB();
            $stmt = $db->prepare("SELECT id, name, email, password, role, status FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] !== 'active') {
                    $error = 'Your account has been suspended. Please contact admin.';
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['name']    = $user['name'];
                    $_SESSION['email']   = $user['email'];
                    $_SESSION['role']    = $user['role'];
                    session_regenerate_id(true);
                    redirect(APP_URL . '/dashboard.php');
                }
            } else {
                $error = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            $error = 'Database error. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — Smart Hospital</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo">
      <a href="index.php" class="d-block">
        <div class="auth-logo-icon"><i class="bi bi-heart-pulse-fill"></i></div>
      </a>
      <div class="auth-title">Welcome Back</div>
      <div class="auth-sub">Sign in to Smart Hospital</div>
    </div>

    <?php if ($error): ?>
    <div style="background:#FEE2E2;border:1px solid #FECACA;color:#991B1B;padding:12px 16px;border-radius:10px;margin-bottom:20px;font-size:.875rem;display:flex;align-items:center;gap:8px">
      <i class="bi bi-exclamation-circle-fill"></i> <?= e($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="login-form">
      <div class="form-group">
        <label class="form-label">Email Address</label>
        <div style="position:relative">
          <i class="bi bi-envelope" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#94A3B8"></i>
          <input type="email" name="email" class="form-control-custom" style="padding-left:38px"
                 value="<?= e($_POST['email'] ?? '') ?>" placeholder="your@email.com" required autofocus>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label" style="display:flex;justify-content:space-between">
          Password
          <a href="forgot_password.php" style="font-weight:500;color:var(--primary);text-decoration:none;font-size:.8rem">Forgot password?</a>
        </label>
        <div style="position:relative">
          <i class="bi bi-lock" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#94A3B8"></i>
          <input type="password" name="password" id="password" class="form-control-custom" style="padding-left:38px;padding-right:44px"
                 placeholder="••••••••" required>
          <button type="button" onclick="togglePwd()" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#94A3B8;padding:0">
            <i class="bi bi-eye" id="pwd-icon"></i>
          </button>
        </div>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:.875rem">
          <input type="checkbox" name="remember" style="accent-color:var(--primary)"> Remember me
        </label>
      </div>
      <button type="submit" class="btn-primary-custom w-100" style="padding:13px;font-size:.95rem;justify-content:center" id="login-btn">
        <i class="bi bi-box-arrow-in-right"></i> Sign In
      </button>
    </form>

    <div class="divider"></div>
    <p style="text-align:center;font-size:.875rem;color:#64748B">
      Don't have an account?
      <a href="register.php" style="color:var(--primary);font-weight:600;text-decoration:none">Create Account</a>
    </p>

    <!-- Demo Credentials -->
    <div style="background:#F8FAFC;border-radius:12px;padding:16px;margin-top:16px;font-size:.8rem">
      <div style="font-weight:700;margin-bottom:8px;color:#475569"><i class="bi bi-info-circle me-1"></i> Demo Credentials</div>
      <?php foreach([['Admin','admin@hospital.com'],['Doctor','rajesh@hospital.com'],['Patient','amit@email.com']] as [$role,$email]): ?>
      <div style="display:flex;justify-content:space-between;margin-bottom:4px;color:#64748B">
        <span><strong><?= $role ?>:</strong> <?= $email ?></span>
        <button onclick="fillCreds('<?= $email ?>')" style="background:none;border:none;cursor:pointer;color:var(--primary);font-size:.78rem;font-weight:600;padding:0">Use</button>
      </div>
      <?php endforeach; ?>
      <div style="color:#94A3B8;margin-top:6px">Password: <strong>password</strong></div>
    </div>
  </div>
</div>

<script>
function togglePwd() {
  const p = document.getElementById('password');
  const i = document.getElementById('pwd-icon');
  if (p.type === 'password') { p.type = 'text'; i.className = 'bi bi-eye-slash'; }
  else { p.type = 'password'; i.className = 'bi bi-eye'; }
}
function fillCreds(email) {
  document.querySelector('[name=email]').value = email;
  document.querySelector('[name=password]').value = 'password';
}
document.getElementById('login-form').addEventListener('submit', function() {
  const btn = document.getElementById('login-btn');
  btn.innerHTML = '<span class="spinner"></span> Signing in...';
  btn.disabled = true;
});
</script>
</body>
</html>
