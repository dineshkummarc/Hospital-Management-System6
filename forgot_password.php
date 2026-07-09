<?php
require_once 'config.php';
if (!empty($_SESSION['user_id'])) redirect(APP_URL . '/dashboard.php');

$msg = $msgType = '';
$step = 1; // 1=enter email, 2=sent

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = 'Please enter a valid email address.'; $msgType = 'error';
    } else {
        $db   = getDB();
        $stmt = $db->prepare("SELECT id, name FROM users WHERE email=? AND status='active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        // We always show "success" to prevent email enumeration
        $step = 2;
        $msg  = "If an account exists for <strong>" . e($email) . "</strong>, a password reset link has been sent. Please check your inbox.";
        $msgType = 'success';
        // In production: generate token, store in DB, send email
        // For demo: just show the message
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password — Smart Hospital</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo">
      <a href="index.php">
        <div class="auth-logo-icon"><i class="bi bi-heart-pulse-fill"></i></div>
      </a>
      <div class="auth-title"><?= $step===2 ? 'Check Your Email' : 'Forgot Password' ?></div>
      <div class="auth-sub"><?= $step===2 ? 'We sent a reset link to your email' : 'Enter your email to reset your password' ?></div>
    </div>

    <?php if ($msg): ?>
    <div style="background:<?= $msgType==='success'?'#DCFCE7':'#FEE2E2' ?>;border:1px solid <?= $msgType==='success'?'#BBF7D0':'#FECACA' ?>;color:<?= $msgType==='success'?'#166534':'#991B1B' ?>;padding:14px 16px;border-radius:10px;margin-bottom:20px;font-size:.875rem;line-height:1.5">
      <i class="bi bi-<?= $msgType==='success'?'envelope-check':'exclamation-circle' ?>-fill me-2"></i><?= $msg ?>
    </div>
    <?php endif; ?>

    <?php if ($step === 1): ?>
    <form method="POST">
      <div class="form-group">
        <label class="form-label">Email Address</label>
        <div style="position:relative">
          <i class="bi bi-envelope" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#94A3B8"></i>
          <input type="email" name="email" class="form-control-custom" style="padding-left:38px"
                 placeholder="your@email.com" required autofocus>
        </div>
      </div>
      <button type="submit" class="btn-primary-custom w-100" style="padding:13px;font-size:.95rem;justify-content:center">
        <i class="bi bi-send"></i> Send Reset Link
      </button>
    </form>
    <?php else: ?>
    <!-- Success state -->
    <div style="text-align:center;padding:20px 0">
      <div style="width:72px;height:72px;background:#DCFCE7;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:2rem;margin:0 auto 16px">
        <i class="bi bi-envelope-check" style="color:#16A34A"></i>
      </div>
      <p style="color:#475569;font-size:.875rem;line-height:1.6;margin-bottom:20px">
        For this demo, password reset emails are not actually sent.<br>
        Please use the demo credentials on the login page.
      </p>
      <a href="login.php" class="btn-primary-custom" style="justify-content:center;width:100%;padding:13px">
        <i class="bi bi-arrow-left"></i> Back to Login
      </a>
    </div>
    <?php endif; ?>

    <div class="divider"></div>
    <p style="text-align:center;font-size:.875rem;color:#64748B">
      Remember your password?
      <a href="login.php" style="color:var(--primary);font-weight:600;text-decoration:none">Sign In</a>
    </p>
  </div>
</div>
</body>
</html>
