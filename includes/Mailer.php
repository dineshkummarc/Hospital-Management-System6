<?php
/**
 * Smart Hospital — Mailer.php
 * ─────────────────────────────────────────────────────────────
 * sendEmail()  — core send function (SMTP via PHPMailer)
 * HTML template generators for every notification event.
 * ─────────────────────────────────────────────────────────────
 */

if (!defined('APP_ROOT')) {
    die('Direct access not permitted.');
}

// ── Load PHPMailer ────────────────────────────────────────────
$_phpmailerAutoload = APP_ROOT . '/vendor/phpmailer/autoload.php';
if (!file_exists($_phpmailerAutoload)) {
    error_log('[Mailer] PHPMailer autoload not found at: ' . $_phpmailerAutoload);
} else {
    require_once $_phpmailerAutoload;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

// ── Load mail config ─────────────────────────────────────────
$_mailConfigPath = APP_ROOT . '/config/mail_config.php';
if (file_exists($_mailConfigPath)) {
    require_once $_mailConfigPath;
}

// ── Ensure logs dir ───────────────────────────────────────────
if (!is_dir(APP_ROOT . '/logs')) {
    @mkdir(APP_ROOT . '/logs', 0755, true);
}

// ─────────────────────────────────────────────────────────────
// CORE SEND FUNCTION
// ─────────────────────────────────────────────────────────────

/**
 * Send an HTML email via SMTP.
 *
 * @param string|array $to      'email' | ['email','Name'] | [['email','Name'],…]
 * @param string       $subject
 * @param string       $htmlBody
 * @param array        $options  ['cc'=>[], 'bcc'=>[], 'alt_body'=>'']
 * @return bool
 */
function sendEmail($to, string $subject, string $htmlBody, array $options = []): bool
{
    // ── Kill switch ───────────────────────────────────────────
    if (defined('MAIL_ENABLED') && !MAIL_ENABLED) {
        return false;
    }

    // ── Get config ────────────────────────────────────────────
    $cfg = function_exists('getMailConfig') ? getMailConfig() : [];
    $host = $cfg['host']         ?? '';
    $port = (int)($cfg['port']   ?? 587);
    $user = $cfg['username']     ?? '';
    $pass = $cfg['password']     ?? '';
    $enc  = $cfg['encryption']   ?? 'tls';
    $fromEmail = $cfg['sender_email'] ?? '';
    $fromName  = $cfg['sender_name']  ?? 'Smart Hospital';

    // ── Validate credentials before touching PHPMailer ────────
    if (empty($user) || empty($pass) || empty($host)) {
        _logMailError(
            'sendEmail',
            'SMTP not configured: host=' . ($host ?: 'EMPTY')
            . ' user=' . ($user ? 'SET' : 'EMPTY')
            . ' pass=' . ($pass ? 'SET' : 'EMPTY'),
            _extractEmail($to)
        );
        return false;
    }

    // ── Build recipient string for log ────────────────────────
    $recipientLog = _extractEmail($to);

    try {
        $mail = new PHPMailer(true); // true = throw exceptions

        // ── Server ───────────────────────────────────────────
        $mail->isSMTP();
        $mail->Host     = $host;
        $mail->Port     = $port;
        $mail->SMTPAuth = true;
        $mail->Username = $user;
        $mail->Password = $pass;
        $mail->CharSet  = 'UTF-8';
        $mail->Timeout  = 30;

        // Encryption
        if ($enc === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($enc === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }

        // Disable SSL cert verification for localhost/dev (XAMPP)
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ];

        $mail->SMTPDebug = (defined('MAIL_DEBUG_MODE') && MAIL_DEBUG_MODE) ? 2 : 0;
        if ($mail->SMTPDebug > 0) {
            $mail->Debugoutput = function(string $str, int $level) {
                _logMailError('SMTP_DEBUG', $str, '');
            };
        }

        // ── From ─────────────────────────────────────────────
        if ($fromEmail) {
            $mail->setFrom($fromEmail, $fromName);
        }

        // ── To ───────────────────────────────────────────────
        if (is_string($to)) {
            if (!empty($to)) $mail->addAddress($to);
        } elseif (is_array($to)) {
            // ['email@x.com', 'Name']
            if (count($to) === 2 && is_string($to[0]) && filter_var($to[0], FILTER_VALIDATE_EMAIL)) {
                $mail->addAddress($to[0], $to[1] ?? '');
            } else {
                // [['email','Name'], …]  or ['e1@x.com', 'e2@x.com']
                foreach ($to as $r) {
                    if (is_array($r) && !empty($r[0])) {
                        $mail->addAddress($r[0], $r[1] ?? '');
                    } elseif (is_string($r) && filter_var($r, FILTER_VALIDATE_EMAIL)) {
                        $mail->addAddress($r);
                    }
                }
            }
        }

        // ── CC / BCC ─────────────────────────────────────────
        foreach ($options['cc']  ?? [] as $cc)  {
            $mail->addCC(is_array($cc)  ? ($cc[0]  ?? $cc)  : $cc,  is_array($cc)  ? ($cc[1]  ?? '') : '');
        }
        foreach ($options['bcc'] ?? [] as $bcc) {
            $mail->addBCC(is_array($bcc) ? ($bcc[0] ?? $bcc) : $bcc, is_array($bcc) ? ($bcc[1] ?? '') : '');
        }

        // ── Content ──────────────────────────────────────────
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $options['alt_body']
            ?? strip_tags(str_replace(
                ['<br>', '<br/>', '<br />', '</p>', '</div>', '</h1>', '</h2>', '</h3>', '</li>'],
                "\n",
                $htmlBody
            ));

        $mail->send();
        return true;

    } catch (MailException $e) {
        _logMailError('sendEmail[MailException]', $e->getMessage(), $recipientLog);
        return false;
    } catch (\Throwable $e) {
        _logMailError('sendEmail[Exception]', $e->getMessage(), $recipientLog);
        return false;
    }
}

// ─────────────────────────────────────────────────────────────
// DIAGNOSTIC SEND — returns detailed error string (for test UI)
// ─────────────────────────────────────────────────────────────

/**
 * Same as sendEmail() but returns '' on success or an error message string on failure.
 * Use ONLY from the test-email AJAX endpoint.
 */
function sendEmailDiagnostic(string $to, string $subject, string $htmlBody): string
{
    $cfg  = function_exists('getMailConfig') ? getMailConfig() : [];
    $host = $cfg['host']      ?? '';
    $user = $cfg['username']  ?? '';
    $pass = $cfg['password']  ?? '';

    if (empty($host)) return 'SMTP host is not configured. Please save your settings first.';
    if (empty($user)) return 'SMTP username is not configured. Please save your settings first.';
    if (empty($pass)) return 'SMTP password is not configured. Please save your settings first.';
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return "Invalid recipient email: {$to}";

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->Port       = (int)($cfg['port'] ?? 587);
        $mail->SMTPAuth   = true;
        $mail->Username   = $user;
        $mail->Password   = $pass;
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 15;
        $mail->SMTPOptions = [
            'ssl' => ['verify_peer'=>false,'verify_peer_name'=>false,'allow_self_signed'=>true],
        ];

        $enc = $cfg['encryption'] ?? 'tls';
        if ($enc === 'ssl')      $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        elseif ($enc === 'tls')  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        else                     { $mail->SMTPSecure = ''; $mail->SMTPAutoTLS = false; }

        if (!empty($cfg['sender_email'])) {
            $mail->setFrom($cfg['sender_email'], $cfg['sender_name'] ?? 'Smart Hospital');
        }
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);
        $mail->send();
        return ''; // success

    } catch (MailException $e) {
        $msg = $e->getMessage();
        _logMailError('sendEmailDiagnostic', $msg, $to);
        return $msg;
    } catch (\Throwable $e) {
        $msg = $e->getMessage();
        _logMailError('sendEmailDiagnostic', $msg, $to);
        return $msg;
    }
}

// ─────────────────────────────────────────────────────────────
// HELPERS
// ─────────────────────────────────────────────────────────────

function _extractEmail($to): string
{
    if (is_string($to)) return $to;
    if (is_array($to)) {
        if (isset($to[0]) && is_string($to[0])) return $to[0];
        if (isset($to[0][0])) return $to[0][0];
    }
    return 'unknown';
}

function _logMailError(string $event, string $error, string $recipient = ''): void
{
    if (defined('MAIL_LOG_ENABLED') && !MAIL_LOG_ENABLED) return;
    $logPath = defined('MAIL_LOG_PATH') ? MAIL_LOG_PATH : (APP_ROOT . '/logs/email_errors.log');
    $line = sprintf(
        "[%s] EVENT=%s | TO=%s | ERROR=%s\n",
        date('Y-m-d H:i:s'), $event, $recipient,
        str_replace(["\n", "\r"], ' ', $error)
    );
    @error_log($line, 3, $logPath);
}

// ─────────────────────────────────────────────────────────────
// HTML EMAIL TEMPLATES
// ─────────────────────────────────────────────────────────────

function _mailWrap(string $content, string $preheader = ''): string
{
    $appName = defined('APP_NAME') ? APP_NAME : 'Smart Hospital';
    $year    = date('Y');
    $pre     = htmlspecialchars($preheader, ENT_QUOTES);

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$appName}</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{background:#F1F5F9;font-family:'Segoe UI',Arial,sans-serif;color:#1E293B;padding:20px 0}
  .wrapper{max-width:600px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.1)}
  .hdr{background:linear-gradient(135deg,#1E3A8A,#2563EB 60%,#6366F1);padding:28px 36px;text-align:center}
  .hdr-icon{display:inline-block;background:rgba(255,255,255,.15);border-radius:12px;width:52px;height:52px;line-height:52px;font-size:26px;margin-bottom:10px}
  .hdr-name{color:#fff;font-size:1.25rem;font-weight:800;letter-spacing:-.01em;display:block}
  .hdr-sub{color:rgba(255,255,255,.7);font-size:.8rem;margin-top:4px;display:block}
  .body{padding:32px 36px}
  .card{background:#F8FAFC;border:1px solid #E2E8F0;border-radius:12px;padding:20px;margin:16px 0}
  .row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #F1F5F9;font-size:.875rem}
  .row:last-child{border:none}
  .lbl{color:#64748B;font-weight:500}
  .val{color:#0F172A;font-weight:600;text-align:right;max-width:60%}
  .btn{display:inline-block;background:linear-gradient(135deg,#2563EB,#1D4ED8);color:#fff!important;padding:12px 28px;border-radius:10px;text-decoration:none;font-weight:700;font-size:.9rem;margin:20px 0 4px}
  .badge{display:inline-block;padding:3px 12px;border-radius:20px;font-size:.75rem;font-weight:700}
  .b-blue{background:#DBEAFE;color:#1E40AF}
  .b-green{background:#DCFCE7;color:#166534}
  .b-orange{background:#FEF3C7;color:#92400E}
  .b-red{background:#FEE2E2;color:#991B1B}
  .b-purple{background:#EDE9FE;color:#5B21B6}
  .divider{height:1px;background:#E2E8F0;margin:20px 0}
  .alert{border-left:4px solid #2563EB;background:#EFF6FF;padding:12px 16px;border-radius:0 8px 8px 0;font-size:.875rem;color:#1E40AF;margin:14px 0}
  .ftr{background:#F8FAFC;border-top:1px solid #E2E8F0;padding:20px 36px;text-align:center}
  .ftr p{margin:3px 0;font-size:.75rem;color:#94A3B8;line-height:1.5}
  h2{color:#0F172A;font-size:1.2rem;font-weight:800;margin:0 0 6px}
  p{font-size:.875rem;color:#475569;line-height:1.65;margin:0 0 10px}
  table.meds{width:100%;border-collapse:collapse;font-size:.83rem;margin-top:10px}
  table.meds th{padding:8px 10px;text-align:left;background:#F1F5F9;color:#475569;font-weight:600}
  table.meds td{padding:8px 10px;border-bottom:1px solid #F8FAFC}
  @media(max-width:600px){.body,.hdr,.ftr{padding:20px!important}}
</style>
</head>
<body>
<div style="display:none;max-height:0;overflow:hidden;color:#F1F5F9">{$pre}</div>
<div class="wrapper">
  <div class="hdr">
    <div class="hdr-icon">🏥</div>
    <span class="hdr-name">{$appName}</span>
    <span class="hdr-sub">Smart Hospital Management System</span>
  </div>
  <div class="body">{$content}</div>
  <div class="ftr">
    <p>© {$year} {$appName}. All rights reserved.</p>
    <p>123 Hospital Road, Raipur, CG | +91 98765 43210</p>
    <p>This is an automated message — please do not reply.</p>
  </div>
</div>
</body>
</html>
HTML;
}

// ── Welcome ───────────────────────────────────────────────────
function mailTemplateWelcome(string $name, string $role, string $email, string $loginUrl = ''): string
{
    if (!$loginUrl && defined('APP_URL')) $loginUrl = APP_URL . '/login.php';
    $bc   = ['admin'=>'b-red','doctor'=>'b-purple','patient'=>'b-blue'][$role] ?? 'b-blue';
    $rl   = ucfirst($role);
    $n    = htmlspecialchars($name,  ENT_QUOTES);
    $e    = htmlspecialchars($email, ENT_QUOTES);
    $lu   = htmlspecialchars($loginUrl, ENT_QUOTES);

    $body = <<<HTML
<h2>Welcome to Smart Hospital! 🎉</h2>
<p>Hello <strong>{$n}</strong>, your account has been created successfully.</p>
<div class="card">
  <div class="row"><span class="lbl">Name</span><span class="val">{$n}</span></div>
  <div class="row"><span class="lbl">Email</span><span class="val">{$e}</span></div>
  <div class="row"><span class="lbl">Role</span><span class="val"><span class="badge {$bc}">{$rl}</span></span></div>
</div>
<div class="alert">🔐 For your security, please change your password after your first login.</div>
<p style="text-align:center"><a href="{$lu}" class="btn">Login to Your Account →</a></p>
HTML;
    return _mailWrap($body, "Welcome to Smart Hospital, {$n}!");
}

// ── Appointment ───────────────────────────────────────────────
function mailTemplateAppointment(array $d): string
{
    $title  = htmlspecialchars($d['title']      ?? 'Appointment Update',   ENT_QUOTES);
    $msg    = htmlspecialchars($d['message']    ?? '',                     ENT_QUOTES);
    $pt     = htmlspecialchars($d['patient']    ?? 'N/A',                  ENT_QUOTES);
    $doc    = htmlspecialchars($d['doctor']     ?? 'N/A',                  ENT_QUOTES);
    $dept   = htmlspecialchars($d['department'] ?? 'N/A',                  ENT_QUOTES);
    $date   = htmlspecialchars($d['date']       ?? 'N/A',                  ENT_QUOTES);
    $time   = htmlspecialchars($d['time']       ?? 'N/A',                  ENT_QUOTES);
    $token  = htmlspecialchars((string)($d['token']  ?? ''),               ENT_QUOTES);
    $status = htmlspecialchars($d['status']     ?? 'pending',              ENT_QUOTES);
    $reason = htmlspecialchars($d['reason']     ?? '',                     ENT_QUOTES);
    $lu     = defined('APP_URL') ? htmlspecialchars(APP_URL . '/login.php', ENT_QUOTES) : '#';

    $sc = ['pending'=>'b-orange','approved'=>'b-green','rejected'=>'b-red',
           'completed'=>'b-blue','cancelled'=>'b-red'][$d['status']??'pending'] ?? 'b-blue';
    $tk = $token ? "<div class=\"row\"><span class=\"lbl\">Token #</span><span class=\"val\">{$token}</span></div>" : '';
    $re = $reason ? "<div class=\"row\"><span class=\"lbl\">Reason</span><span class=\"val\">{$reason}</span></div>" : '';

    $body = <<<HTML
<h2>{$title}</h2>
<p>{$msg}</p>
<div class="card">
  <div class="row"><span class="lbl">Patient</span><span class="val">{$pt}</span></div>
  <div class="row"><span class="lbl">Doctor</span><span class="val">{$doc}</span></div>
  <div class="row"><span class="lbl">Department</span><span class="val">{$dept}</span></div>
  <div class="row"><span class="lbl">Date</span><span class="val">{$date}</span></div>
  <div class="row"><span class="lbl">Time</span><span class="val">{$time}</span></div>
  {$tk}
  <div class="row"><span class="lbl">Status</span><span class="val"><span class="badge {$sc}">{$status}</span></span></div>
  {$re}
</div>
<p style="text-align:center"><a href="{$lu}" class="btn">View in Portal →</a></p>
HTML;
    return _mailWrap($body, $d['title'] ?? 'Appointment Update');
}

// ── Prescription ──────────────────────────────────────────────
function mailTemplatePrescription(array $d): string
{
    $title   = htmlspecialchars($d['title']     ?? 'Prescription Issued',  ENT_QUOTES);
    $msg     = htmlspecialchars($d['message']   ?? '',                     ENT_QUOTES);
    $pt      = htmlspecialchars($d['patient']   ?? 'N/A',                  ENT_QUOTES);
    $doc     = htmlspecialchars($d['doctor']    ?? 'N/A',                  ENT_QUOTES);
    $diag    = htmlspecialchars($d['diagnosis'] ?? 'N/A',                  ENT_QUOTES);
    $follow  = htmlspecialchars($d['follow_up'] ?? '',                     ENT_QUOTES);
    $advice  = htmlspecialchars($d['advice']    ?? '',                     ENT_QUOTES);
    $meds    = $d['medicines'] ?? [];
    $lu      = defined('APP_URL') ? htmlspecialchars(APP_URL . '/login.php', ENT_QUOTES) : '#';

    $medRows = '';
    if (!empty($meds) && is_array($meds)) {
        foreach ($meds as $m) {
            $mn = htmlspecialchars($m['name']         ?? '', ENT_QUOTES);
            $md = htmlspecialchars($m['dosage']       ?? '', ENT_QUOTES);
            $mu = htmlspecialchars($m['duration']     ?? '', ENT_QUOTES);
            $mi = htmlspecialchars($m['instructions'] ?? '', ENT_QUOTES);
            $medRows .= "<tr><td>{$mn}</td><td>{$md}</td><td>{$mu}</td><td>{$mi}</td></tr>";
        }
    }
    $medTable = $medRows
        ? "<table class=\"meds\"><thead><tr><th>Medicine</th><th>Dosage</th><th>Duration</th><th>Instructions</th></tr></thead><tbody>{$medRows}</tbody></table>"
        : '';
    $fu  = $follow  ? "<div class=\"row\"><span class=\"lbl\">Follow-up</span><span class=\"val\">{$follow}</span></div>" : '';
    $adv = $advice  ? "<div class=\"alert\" style=\"border-color:#10B981;background:#F0FDF4;color:#166534\">💊 {$advice}</div>" : '';

    $body = <<<HTML
<h2>{$title}</h2>
<p>{$msg}</p>
<div class="card">
  <div class="row"><span class="lbl">Patient</span><span class="val">{$pt}</span></div>
  <div class="row"><span class="lbl">Doctor</span><span class="val">{$doc}</span></div>
  <div class="row"><span class="lbl">Diagnosis</span><span class="val">{$diag}</span></div>
  {$fu}
  {$medTable}
</div>
{$adv}
<p style="text-align:center"><a href="{$lu}" class="btn">View Full Prescription →</a></p>
HTML;
    return _mailWrap($body, $d['title'] ?? 'Prescription Issued');
}

// ── Billing ───────────────────────────────────────────────────
function mailTemplateBilling(array $d): string
{
    $title   = htmlspecialchars($d['title']   ?? 'Invoice Generated',  ENT_QUOTES);
    $msg     = htmlspecialchars($d['message'] ?? '',                   ENT_QUOTES);
    $pt      = htmlspecialchars($d['patient'] ?? 'N/A',                ENT_QUOTES);
    $inv     = htmlspecialchars($d['invoice'] ?? 'N/A',                ENT_QUOTES);
    $total   = htmlspecialchars((string)($d['total']  ?? '0.00'),      ENT_QUOTES);
    $status  = htmlspecialchars($d['status']  ?? 'pending',            ENT_QUOTES);
    $method  = htmlspecialchars($d['method']  ?? 'cash',               ENT_QUOTES);
    $date    = htmlspecialchars($d['date']    ?? date('d M Y'),        ENT_QUOTES);
    $bd      = $d['breakdown'] ?? [];
    $lu      = defined('APP_URL') ? htmlspecialchars(APP_URL . '/login.php', ENT_QUOTES) : '#';

    $sc  = ['pending'=>'b-orange','paid'=>'b-green','partial'=>'b-blue','cancelled'=>'b-red'][$d['status']??'pending'] ?? 'b-orange';
    $bdr = '';
    foreach ($bd as $lbl => $amt) {
        if ((float)$amt > 0) {
            $bdr .= '<div class="row"><span class="lbl">' . htmlspecialchars($lbl, ENT_QUOTES)
                 . '</span><span class="val">₹' . number_format((float)$amt, 2) . '</span></div>';
        }
    }

    $body = <<<HTML
<h2>{$title}</h2>
<p>{$msg}</p>
<div class="card">
  <div class="row"><span class="lbl">Patient</span><span class="val">{$pt}</span></div>
  <div class="row"><span class="lbl">Invoice</span><span class="val" style="color:#2563EB;font-family:monospace">{$inv}</span></div>
  <div class="row"><span class="lbl">Date</span><span class="val">{$date}</span></div>
  <div class="row"><span class="lbl">Method</span><span class="val">{$method}</span></div>
  {$bdr}
  <div class="divider"></div>
  <div class="row"><span class="lbl" style="font-weight:700;font-size:1rem">Total</span><span class="val" style="font-size:1.05rem">₹{$total}</span></div>
  <div class="row"><span class="lbl">Status</span><span class="val"><span class="badge {$sc}">{$status}</span></span></div>
</div>
<p style="text-align:center"><a href="{$lu}" class="btn">View Invoice →</a></p>
HTML;
    return _mailWrap($body, "Invoice {$inv} — ₹{$total}");
}

// ── Alert / System ────────────────────────────────────────────
function mailTemplateAlert(string $title, string $message, array $details = [], string $type = 'info'): string
{
    $colors  = ['info'=>'#2563EB','success'=>'#10B981','warning'=>'#F59E0B','error'=>'#EF4444'];
    $emojis  = ['info'=>'ℹ️','success'=>'✅','warning'=>'⚠️','error'=>'🚨'];
    $col     = $colors[$type]  ?? $colors['info'];
    $em      = $emojis[$type]  ?? 'ℹ️';
    $t       = htmlspecialchars($title,   ENT_QUOTES);
    $m       = htmlspecialchars($message, ENT_QUOTES);
    $lu      = defined('APP_URL') ? htmlspecialchars(APP_URL . '/login.php', ENT_QUOTES) : '#';

    $rows = '';
    foreach ($details as $k => $v) {
        $rows .= '<div class="row"><span class="lbl">' . htmlspecialchars((string)$k, ENT_QUOTES)
               . '</span><span class="val">' . htmlspecialchars((string)$v, ENT_QUOTES) . '</span></div>';
    }
    $card = $rows ? "<div class=\"card\">{$rows}</div>" : '';

    $body = <<<HTML
<div style="text-align:center;margin-bottom:20px">
  <div style="width:64px;height:64px;background:{$col}22;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:2rem">{$em}</div>
</div>
<h2 style="text-align:center">{$t}</h2>
<p style="text-align:center">{$m}</p>
{$card}
<p style="text-align:center"><a href="{$lu}" class="btn">Go to Dashboard →</a></p>
HTML;
    return _mailWrap($body, $t);
}

// ── Test email ────────────────────────────────────────────────
function mailTemplateTest(string $to): string
{
    $ts  = date('d M Y, h:i:s A');
    $cfg = function_exists('getMailConfig') ? getMailConfig() : [];
    $to  = htmlspecialchars($to, ENT_QUOTES);

    $body = <<<HTML
<div style="text-align:center;margin-bottom:20px"><div style="font-size:3.5rem">✅</div></div>
<h2 style="text-align:center">SMTP Test Successful!</h2>
<p style="text-align:center">Your email configuration is working correctly.</p>
<div class="card">
  <div class="row"><span class="lbl">Sent To</span><span class="val">{$to}</span></div>
  <div class="row"><span class="lbl">Sent At</span><span class="val">{$ts}</span></div>
  <div class="row"><span class="lbl">SMTP Host</span><span class="val">{$cfg['host']}</span></div>
  <div class="row"><span class="lbl">Port</span><span class="val">{$cfg['port']}</span></div>
  <div class="row"><span class="lbl">Encryption</span><span class="val" style="text-transform:uppercase">{$cfg['encryption']}</span></div>
  <div class="row"><span class="lbl">Status</span><span class="val"><span class="badge b-green">Delivered ✓</span></span></div>
</div>
<div class="alert">🎉 Automated email notifications will now be sent for all hospital events.</div>
HTML;
    return _mailWrap($body, 'SMTP Test — Smart Hospital');
}
