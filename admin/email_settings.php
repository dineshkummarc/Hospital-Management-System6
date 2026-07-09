<?php
/**
 * admin/email_settings.php
 * SMTP Configuration — Admin Panel
 */
error_reporting(0);
ini_set('display_errors', '0');
ob_start();

require_once '../config.php';
requireAuth('admin');

$db = getDB();

// ── Ensure table exists ───────────────────────────────────────
try {
    $db->exec("CREATE TABLE IF NOT EXISTS email_settings (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        smtp_host       VARCHAR(255) NOT NULL DEFAULT 'smtp.gmail.com',
        smtp_port       INT          NOT NULL DEFAULT 587,
        smtp_username   VARCHAR(255) NOT NULL DEFAULT '',
        smtp_password   VARCHAR(255) NOT NULL DEFAULT '',
        smtp_encryption VARCHAR(20)  NOT NULL DEFAULT 'tls',
        sender_name     VARCHAR(255) NOT NULL DEFAULT 'Smart Hospital',
        sender_email    VARCHAR(255) NOT NULL DEFAULT '',
        is_active       TINYINT(1)   NOT NULL DEFAULT 1,
        updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if ($db->query("SELECT COUNT(*) FROM email_settings")->fetchColumn() == 0) {
        $db->exec("INSERT INTO email_settings
            (smtp_host,smtp_port,smtp_encryption,sender_name,sender_email)
            VALUES ('smtp.gmail.com',587,'tls','Smart Hospital','')");
    }
} catch (PDOException $e) { /* already exists */ }

// ── AJAX: save_settings only (test handled by ajax/send_test_email.php) ──
if (!empty($_POST['action'])) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    verifyCsrf();

    if ($_POST['action'] === 'save_settings') {
        $host    = trim($_POST['smtp_host']     ?? '');
        $port    = (int)($_POST['smtp_port']    ?? 587);
        $user    = trim($_POST['smtp_username'] ?? '');
        $pass    = $_POST['smtp_password']      ?? '';
        $enc     = $_POST['smtp_encryption']    ?? 'tls';
        $sname   = trim($_POST['sender_name']   ?? 'Smart Hospital');
        $semail  = trim($_POST['sender_email']  ?? '');

        if (!$host)  { echo json_encode(['success'=>false,'message'=>'SMTP Host is required.']); exit; }
        if ($semail && !filter_var($semail, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success'=>false,'message'=>'Invalid sender email address.']); exit;
        }
        if (!in_array($enc, ['tls','ssl','none'])) $enc = 'tls';

        // Keep existing password if field left blank
        if (trim($pass) === '' || $pass === '••••••••') {
            $row  = $db->query("SELECT smtp_password FROM email_settings WHERE id=1")->fetch();
            $pass = $row['smtp_password'] ?? '';
        }

        $db->prepare("INSERT INTO email_settings
            (id,smtp_host,smtp_port,smtp_username,smtp_password,smtp_encryption,sender_name,sender_email)
            VALUES (1,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
              smtp_host=VALUES(smtp_host), smtp_port=VALUES(smtp_port),
              smtp_username=VALUES(smtp_username), smtp_password=VALUES(smtp_password),
              smtp_encryption=VALUES(smtp_encryption),
              sender_name=VALUES(sender_name), sender_email=VALUES(sender_email),
              updated_at=NOW()")
           ->execute([$host,$port,$user,$pass,$enc,$sname,$semail]);

        echo json_encode(['success'=>true,'message'=>'Settings saved successfully!']);
        exit;
    }

    if ($_POST['action'] === 'clear_log') {
        $logFile = APP_ROOT . '/logs/email_errors.log';
        if (file_exists($logFile)) file_put_contents($logFile, '');
        echo json_encode(['success'=>true,'message'=>'Log cleared.']);
        exit;
    }

    echo json_encode(['success'=>false,'message'=>'Unknown action.']);
    exit;
}

// ── Load settings ─────────────────────────────────────────────
$s = $db->query("SELECT * FROM email_settings WHERE id=1")->fetch() ?: [];

// ── Log ───────────────────────────────────────────────────────
$logFile    = APP_ROOT . '/logs/email_errors.log';
$logLines   = [];
$errorCount = 0;
if (file_exists($logFile) && filesize($logFile) > 0) {
    $all        = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $errorCount = count($all);
    $logLines   = array_slice(array_reverse($all), 0, 20);
}

$hasCredentials = !empty($s['smtp_username']) && !empty($s['smtp_password']);

$pageTitle  = 'Email Settings';
$activeMenu = 'email_settings';
include '../includes/layout_header.php';
?>

<!-- ── Page header ──────────────────────────────────────────── -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
  <div>
    <h4 style="font-family:var(--font-display);font-weight:800;margin:0">
      <i class="bi bi-envelope-gear me-2" style="color:var(--primary)"></i>Email Configuration
    </h4>
    <p style="color:#64748B;font-size:.875rem;margin:0">
      Configure SMTP for automated email notifications
    </p>
  </div>
  <span class="badge-pill <?= $hasCredentials ? 'approved' : 'rejected' ?>">
    <?= $hasCredentials ? '✅ SMTP Configured' : '⚠️ Not Configured' ?>
  </span>
</div>

<!-- ── Summary cards ────────────────────────────────────────── -->
<div class="row g-4 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card blue" style="padding:18px">
      <div class="stat-icon blue" style="width:42px;height:42px;margin-bottom:10px"><i class="bi bi-envelope"></i></div>
      <div class="stat-value" style="font-size:1.4rem"><?= $hasCredentials ? 'Active' : 'Off' ?></div>
      <div class="stat-label">SMTP Status</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card teal" style="padding:18px">
      <div class="stat-icon teal" style="width:42px;height:42px;margin-bottom:10px"><i class="bi bi-hdd-network"></i></div>
      <div class="stat-value" style="font-size:1rem;word-break:break-all"><?= e($s['smtp_host'] ?? '—') ?></div>
      <div class="stat-label">SMTP Host</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card purple" style="padding:18px">
      <div class="stat-icon purple" style="width:42px;height:42px;margin-bottom:10px"><i class="bi bi-shield-lock"></i></div>
      <div class="stat-value" style="font-size:1.4rem;text-transform:uppercase"><?= e($s['smtp_encryption'] ?? 'TLS') ?></div>
      <div class="stat-label">Encryption · Port <?= e((string)($s['smtp_port'] ?? 587)) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card <?= $errorCount > 0 ? 'orange' : 'green' ?>" style="padding:18px">
      <div class="stat-icon <?= $errorCount > 0 ? 'orange' : 'green' ?>" style="width:42px;height:42px;margin-bottom:10px"><i class="bi bi-bug"></i></div>
      <div class="stat-value" style="font-size:1.4rem" data-count="<?= $errorCount ?>"><?= $errorCount ?></div>
      <div class="stat-label">Error Log Entries</div>
    </div>
  </div>
</div>

<div class="row g-4">

  <!-- ── LEFT: Settings form ──────────────────────────────── -->
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header">
        <h5><i class="bi bi-gear me-2 text-primary"></i>SMTP Settings</h5>
        <span style="font-size:.78rem;color:#94A3B8">
          <?= $s['updated_at'] ? 'Updated: ' . date('d M Y, h:i A', strtotime($s['updated_at'])) : 'Never saved' ?>
        </span>
      </div>
      <div class="card-body">

        <!-- Quick provider buttons -->
        <div class="form-group">
          <label class="form-label">Quick Fill — Select Provider</label>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <?php
            $providers = [
              'Gmail'    => ['smtp.gmail.com',      587, 'tls'],
              'Outlook'  => ['smtp.office365.com',  587, 'tls'],
              'Yahoo'    => ['smtp.mail.yahoo.com', 465, 'ssl'],
              'Zoho'     => ['smtp.zoho.com',        587, 'tls'],
              'SendGrid' => ['smtp.sendgrid.net',   587, 'tls'],
              'Mailgun'  => ['smtp.mailgun.org',    587, 'tls'],
            ];
            foreach ($providers as $name => [$ph, $pp, $pe]):
            ?>
            <button type="button" class="provider-btn"
                    onclick="quickFill('<?= $ph ?>',<?= $pp ?>,'<?= $pe ?>')"
                    style="padding:6px 14px;border-radius:8px;border:1.5px solid #E2E8F0;
                           background:#fff;cursor:pointer;font-size:.8rem;font-weight:600;
                           color:#475569;transition:all .2s"
                    onmouseover="this.style.borderColor='var(--primary)';this.style.color='var(--primary)'"
                    onmouseout="this.style.borderColor='#E2E8F0';this.style.color='#475569'">
              <?= $name ?>
            </button>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="divider"></div>

        <form id="smtp-form" autocomplete="off">
          <input type="hidden" name="action"     value="save_settings">
          <input type="hidden" name="csrf_token" id="form-csrf" value="<?= csrfToken() ?>">

          <div class="row g-3">
            <div class="col-8">
              <label class="form-label">SMTP Host *</label>
              <input type="text" name="smtp_host" id="f-host" class="form-control-custom"
                     value="<?= e($s['smtp_host'] ?? 'smtp.gmail.com') ?>"
                     placeholder="smtp.gmail.com" required>
            </div>
            <div class="col-4">
              <label class="form-label">Port *</label>
              <input type="number" name="smtp_port" id="f-port" class="form-control-custom"
                     value="<?= e((string)($s['smtp_port'] ?? 587)) ?>"
                     min="1" max="65535" required>
            </div>
            <div class="col-6">
              <label class="form-label">Encryption</label>
              <select name="smtp_encryption" id="f-enc" class="form-control-custom">
                <option value="tls"  <?= ($s['smtp_encryption']??'tls')==='tls' ?'selected':'' ?>>TLS — port 587 (recommended)</option>
                <option value="ssl"  <?= ($s['smtp_encryption']??'')==='ssl'  ?'selected':'' ?>>SSL — port 465</option>
                <option value="none" <?= ($s['smtp_encryption']??'')==='none' ?'selected':'' ?>>None — port 25</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label">Sender Name</label>
              <input type="text" name="sender_name" class="form-control-custom"
                     value="<?= e($s['sender_name'] ?? 'Smart Hospital') ?>">
            </div>
            <div class="col-12">
              <label class="form-label">SMTP Username / Email *</label>
              <input type="text" name="smtp_username" id="f-user" class="form-control-custom"
                     value="<?= e($s['smtp_username'] ?? '') ?>"
                     placeholder="your.account@gmail.com" autocomplete="new-password">
            </div>
            <div class="col-12">
              <label class="form-label">
                SMTP Password
                <?php if (!empty($s['smtp_password'])): ?>
                <span style="color:#10B981;font-size:.72rem;font-weight:600;margin-left:6px">✓ Password saved</span>
                <?php endif; ?>
              </label>
              <div style="position:relative">
                <input type="password" name="smtp_password" id="f-pass" class="form-control-custom"
                       style="padding-right:44px"
                       placeholder="<?= !empty($s['smtp_password']) ? '••••••••  (leave blank to keep)' : 'Enter SMTP / App Password' ?>"
                       autocomplete="new-password">
                <button type="button" onclick="togglePwd()"
                  style="position:absolute;right:12px;top:50%;transform:translateY(-50%);
                         background:none;border:none;cursor:pointer;color:#94A3B8;padding:4px">
                  <i class="bi bi-eye" id="eye-icon"></i>
                </button>
              </div>
              <div style="font-size:.72rem;color:#94A3B8;margin-top:5px;line-height:1.5">
                💡 Gmail requires an <strong>App Password</strong> — not your Gmail password.
                <a href="https://myaccount.google.com/apppasswords" target="_blank"
                   style="color:var(--primary)">Generate one here →</a>
              </div>
            </div>
            <div class="col-12">
              <label class="form-label">Sender Email Address</label>
              <input type="email" name="sender_email" class="form-control-custom"
                     value="<?= e($s['sender_email'] ?? '') ?>"
                     placeholder="noreply@yourdomain.com">
            </div>
          </div>

          <div style="display:flex;gap:10px;margin-top:20px;flex-wrap:wrap">
            <button type="submit" class="btn-primary-custom" id="save-btn">
              <i class="bi bi-floppy2"></i> Save Settings
            </button>
            <button type="button" class="btn-outline-custom"
                    onclick="openModal('test-modal')" id="test-open-btn"
                    <?= !$hasCredentials ? 'title="Save SMTP settings first"' : '' ?>>
              <i class="bi bi-send"></i> Send Test Email
            </button>
          </div>
        </form>

      </div>
    </div>
  </div>

  <!-- ── RIGHT: Guide + Log ─────────────────────────────────── -->
  <div class="col-lg-5 d-flex flex-column gap-4">

    <!-- Gmail guide -->
    <div class="card">
      <div class="card-header"><h5><i class="bi bi-book me-2 text-info"></i>Gmail Setup (5 steps)</h5></div>
      <div class="card-body" style="font-size:.84rem;color:#475569;padding:18px 20px">
        <ol style="padding-left:18px;display:flex;flex-direction:column;gap:8px;line-height:1.6">
          <li>Go to <a href="https://myaccount.google.com/security" target="_blank" style="color:var(--primary)">Google Account → Security</a></li>
          <li>Enable <strong>2-Step Verification</strong></li>
          <li>Search "App Passwords" and open it</li>
          <li>Choose app: <em>Mail</em>, device: <em>Other</em> → type "Hospital"</li>
          <li>Copy the <strong>16-character password</strong> → paste above</li>
        </ol>
        <div style="background:#EFF6FF;border-radius:8px;padding:10px 12px;margin-top:12px;font-size:.8rem;color:#1E40AF;line-height:1.5">
          Use host <code>smtp.gmail.com</code>, port <code>587</code>, encryption <code>TLS</code>
        </div>
      </div>
    </div>

    <!-- PHPMailer status -->
    <?php
    $pmFile   = APP_ROOT . '/vendor/phpmailer/src/PHPMailer.php';
    $isReal   = file_exists($pmFile) && filesize($pmFile) > 8000;
    ?>
    <div class="card">
      <div class="card-header">
        <h5><i class="bi bi-box me-2 text-warning"></i>PHPMailer Library</h5>
        <span class="badge-pill <?= $isReal ? 'approved' : 'pending' ?>" style="display:inline-flex">
          <?= $isReal ? '✅ Full version' : '⚠️ Stub only' ?>
        </span>
      </div>
      <div class="card-body" style="font-size:.84rem;color:#475569;padding:18px 20px">
        <?php if (!$isReal): ?>
        <p>The project ships with a <strong>stub</strong> that falls back to PHP's built-in <code>mail()</code>. For reliable SMTP delivery, install the real library:</p>
        <div style="background:#0F172A;border-radius:8px;padding:12px 14px;margin:10px 0;font-family:monospace;font-size:.82rem;color:#86EFAC;line-height:1.6">
          cd /path/to/hospital_management<br>
          composer require phpmailer/phpmailer
        </div>
        <p>No Composer? <a href="https://github.com/PHPMailer/PHPMailer/releases" target="_blank" style="color:var(--primary)">Download from GitHub →</a><br>
        Copy <code>PHPMailer.php</code>, <code>SMTP.php</code>, <code>Exception.php</code> into <code>vendor/phpmailer/src/</code></p>
        <?php else: ?>
        <p>✅ Full PHPMailer is installed. Real SMTP delivery is active.</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Error log -->
    <div class="card">
      <div class="card-header">
        <h5><i class="bi bi-bug me-2 text-danger"></i>Error Log
          <?php if ($errorCount > 0): ?>
          <span style="background:#FEE2E2;color:#DC2626;font-size:.7rem;font-weight:700;padding:2px 8px;border-radius:12px;margin-left:6px"><?= $errorCount ?></span>
          <?php endif; ?>
        </h5>
        <?php if ($errorCount > 0): ?>
        <button id="clear-log-btn" onclick="clearLog(this)"
                style="font-size:.78rem;color:#EF4444;background:none;border:none;cursor:pointer;font-weight:600">
          Clear
        </button>
        <?php endif; ?>
      </div>
      <div id="log-body" style="max-height:200px;overflow-y:auto">
        <?php if (empty($logLines)): ?>
        <div style="padding:20px;text-align:center;color:#94A3B8;font-size:.82rem">
          <i class="bi bi-check-circle" style="font-size:1.4rem;color:#10B981;display:block;margin-bottom:6px"></i>
          No errors logged
        </div>
        <?php else: foreach ($logLines as $line): ?>
        <div style="padding:7px 16px;border-bottom:1px solid #F8FAFC;font-size:.7rem;
                    color:#475569;font-family:monospace;word-break:break-all;line-height:1.5">
          <?= e(trim($line)) ?>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

  </div><!-- /col-lg-5 -->
</div>

<!-- ── Email triggers reference ─────────────────────────────── -->
<div class="card mt-4">
  <div class="card-header">
    <h5><i class="bi bi-lightning me-2 text-warning"></i>Automated Email Triggers</h5>
  </div>
  <div class="table-responsive">
    <table class="data-table">
      <thead><tr><th>Event</th><th>Patient</th><th>Doctor</th><th>Admin</th></tr></thead>
      <tbody>
      <?php foreach ([
        ['Registration',           '✅ Welcome',      '✅ Welcome',     '✅ Alert'],
        ['Appointment Booked',     '✅ Confirmation', '✅ New Request',  '✅ Log'],
        ['Appointment Approved',   '✅ Approved',     '✅ Update',       '—'],
        ['Appointment Rejected',   '✅ Rejected',     '✅ Update',       '—'],
        ['Appointment Completed',  '✅ Complete',     '✅ Done',         '—'],
        ['Prescription Created',   '✅ Rx Ready',     '✅ Confirmed',    '✅ Log'],
        ['Bill Generated',         '✅ Invoice',      '—',              '✅ Revenue'],
        ['Payment Confirmed',      '✅ Receipt',      '—',              '✅ Paid'],
      ] as [$ev,$pt,$doc,$adm]): ?>
      <tr>
        <td style="font-weight:600"><?= $ev ?></td>
        <td style="font-size:.82rem"><?= $pt ?></td>
        <td style="font-size:.82rem"><?= $doc ?></td>
        <td style="font-size:.82rem"><?= $adm ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── Test Email Modal ──────────────────────────────────────── -->
<div class="modal-custom" id="test-modal">
  <div class="modal-box" style="max-width:480px">
    <div class="modal-header-custom">
      <h5><i class="bi bi-send me-2 text-primary"></i>Send Test Email</h5>
      <button class="modal-close" onclick="closeModal('test-modal')">&times;</button>
    </div>
    <div class="modal-body-custom">
      <p style="color:#475569;font-size:.875rem;margin-bottom:18px">
        Sends a test email using your saved SMTP configuration.
      </p>
      <div class="form-group">
        <label class="form-label">Recipient Email Address *</label>
        <input type="email" id="test-to" class="form-control-custom"
               placeholder="your@email.com"
               value="<?= e($s['smtp_username'] ?? '') ?>">
      </div>
      <!-- Result area -->
      <div id="test-result" style="display:none;margin-top:14px;padding:14px 16px;
           border-radius:10px;font-size:.875rem;line-height:1.6"></div>
    </div>
    <div class="modal-footer-custom">
      <button class="btn-outline-custom" onclick="closeModal('test-modal')">Close</button>
      <button class="btn-primary-custom" id="test-send-btn" onclick="sendTestEmail(this)">
        <i class="bi bi-send"></i> Send Test
      </button>
    </div>
  </div>
</div>

<!-- ── Scripts ───────────────────────────────────────────────── -->
<script>
/* CSRF token — embedded securely from PHP session */
const CSRF_TOKEN = <?= json_encode(csrfToken()) ?>;

/* ── Save form ────────────────────────────────────────────── */
document.getElementById('smtp-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = document.getElementById('save-btn');
  const fd  = new FormData(this);
  // Ensure latest csrf token
  fd.set('csrf_token', CSRF_TOKEN);

  const origHTML = btn.innerHTML;
  btn.disabled   = true;
  btn.innerHTML  = '<span class="spinner"></span> Saving…';

  try {
    const resp = await fetch('email_settings.php', { method:'POST', body:fd });
    const ct   = resp.headers.get('content-type') || '';
    let res;
    if (ct.includes('application/json')) {
      res = await resp.json();
    } else {
      const txt = await resp.text();
      res = { success:false, message:'Server error: ' + txt.replace(/<[^>]+>/g,'').trim().substring(0,200) };
    }
    showToast(res.success ? 'Saved ✅' : 'Error ❌', res.message, res.success ? 'success' : 'error');
  } catch(err) {
    showToast('Error', 'Could not reach server. Is Apache running?', 'error');
  } finally {
    btn.disabled  = false;
    btn.innerHTML = origHTML;
  }
});

/* ── Quick-fill provider ──────────────────────────────────── */
function quickFill(host, port, enc) {
  document.getElementById('f-host').value = host;
  document.getElementById('f-port').value = port;
  document.getElementById('f-enc').value  = enc;
  showToast('Filled', host + ' settings applied.', 'info');
}

/* ── Password toggle ──────────────────────────────────────── */
function togglePwd() {
  const inp  = document.getElementById('f-pass');
  const icon = document.getElementById('eye-icon');
  if (inp.type === 'password') { inp.type = 'text';     icon.className = 'bi bi-eye-slash'; }
  else                         { inp.type = 'password'; icon.className = 'bi bi-eye'; }
}

/* ── Send test email ──────────────────────────────────────── */
async function sendTestEmail(btn) {
  const to = document.getElementById('test-to').value.trim();
  if (!to) {
    showToast('Error', 'Enter a recipient email address.', 'error');
    return;
  }

  const resultEl = document.getElementById('test-result');

  // Show loading state
  resultEl.style.display     = 'block';
  resultEl.style.background  = '#EFF6FF';
  resultEl.style.color       = '#1E40AF';
  resultEl.style.border      = '1px solid #BFDBFE';
  resultEl.innerHTML         = '<span class="spinner" style="border-color:rgba(0,0,0,.15);border-top-color:#2563EB;width:16px;height:16px"></span> Connecting to SMTP…';

  const origHTML = btn.innerHTML;
  btn.disabled   = true;
  btn.innerHTML  = '<span class="spinner"></span> Sending…';

  let res = null;
  try {
    const fd = new FormData();
    fd.append('test_to',    to);
    fd.append('csrf_token', CSRF_TOKEN);

    /* ── KEY FIX: correct relative path from /admin/ ── */
    const response = await fetch('../ajax/send_test_email.php', {
      method:      'POST',
      body:        fd,
      credentials: 'same-origin'   // always send session cookie
    });

    const ct = response.headers.get('content-type') || '';
    if (!ct.includes('application/json')) {
      const raw = await response.text();
      const err = raw.replace(/<[^>]+>/g, '').trim().substring(0, 300);
      throw new Error('Server returned non-JSON:\n' + err);
    }
    res = await response.json();

  } catch (err) {
    res = {
      success: false,
      message: err.message || 'Could not reach the server.',
      hint:    'Make sure XAMPP Apache is running and you are logged in as admin.'
    };
  } finally {
    btn.disabled  = false;
    btn.innerHTML = origHTML;
  }

  // ── Display result ──────────────────────────────────────
  if (res.success) {
    resultEl.style.background = '#DCFCE7';
    resultEl.style.color      = '#166534';
    resultEl.style.border     = '1px solid #BBF7D0';
    resultEl.innerHTML        = '<i class="bi bi-check-circle-fill me-2"></i>' + res.message;
    showToast('✅ Sent!', 'Check your inbox.', 'success');

  } else {
    resultEl.style.background = '#FEF2F2';
    resultEl.style.color      = '#991B1B';
    resultEl.style.border     = '1px solid #FECACA';

    let html = '<i class="bi bi-x-circle-fill me-2"></i><strong>Failed:</strong> ' + res.message;

    if (res.hint) {
      html += `<div style="margin-top:10px;padding:10px 12px;background:#FFF7ED;
               border-radius:8px;border-left:3px solid #F59E0B;
               color:#92400E;font-size:.82rem;line-height:1.5">
        <strong>💡 Fix:</strong> ${res.hint}
      </div>`;
    }
    if (res.config) {
      html += `<div style="margin-top:8px;font-size:.75rem;color:#64748B">
        Config: ${res.config.host}:${res.config.port} / ${res.config.enc} / ${res.config.user}
      </div>`;
    }
    if (res.last_log) {
      html += `<div style="margin-top:6px;font-size:.7rem;color:#94A3B8;
               font-family:monospace;word-break:break-all">
        Log: ${res.last_log}
      </div>`;
    }
    resultEl.innerHTML = html;
    showToast('❌ Failed', 'See error details.', 'error');
  }
}

/* ── Clear log ────────────────────────────────────────────── */
async function clearLog(btn) {
  confirmAction('Clear all email error logs?', async () => {
    const fd = new FormData();
    fd.append('action',     'clear_log');
    fd.append('csrf_token', CSRF_TOKEN);

    try {
      const resp = await fetch('email_settings.php', { method:'POST', body:fd });
      const res  = await resp.json();
      if (res.success) {
        document.getElementById('log-body').innerHTML =
          '<div style="padding:20px;text-align:center;color:#94A3B8;font-size:.82rem">'
        + '<i class="bi bi-check-circle" style="font-size:1.4rem;color:#10B981;display:block;margin-bottom:6px"></i>'
        + 'No errors logged</div>';
        const clearBtn = document.getElementById('clear-log-btn');
        if (clearBtn) clearBtn.remove();
        showToast('Cleared', 'Error log cleared.', 'success');
      }
    } catch(e) {
      showToast('Error', 'Could not clear log.', 'error');
    }
  });
}
</script>

<?php
ob_end_flush(); // flush buffered output
include '../includes/layout_footer.php';
?>
