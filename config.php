<?php
/**
 * Smart Hospital Management System — config.php
 * v2: Email Notification integration
 */

define('APP_NAME',    'Smart Hospital');
define('APP_VERSION', '1.1.0');
define('APP_URL',     'http://localhost/Hospital-Management-System6');
define('APP_ROOT',    dirname(__FILE__));

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'Hospital6');
define('DB_PORT', 3306);

define('SESSION_LIFETIME', 7200);
define('PASSWORD_COST',    12);

define('UPLOAD_PATH',   APP_ROOT . '/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024);
define('ALLOWED_TYPES', ['jpg','jpeg','png','pdf','doc','docx']);

date_default_timezone_set('Asia/Kolkata');
error_reporting(E_ALL);
ini_set('display_errors', 1);

/* ── PDO singleton ─────────────────────────────────────────── */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=".DB_HOST.";port=".DB_PORT.";dbname=".DB_NAME.";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
            ]);
        } catch (PDOException $e) {
            die(json_encode(['error'=>'DB failed: '.$e->getMessage()]));
        }
    }
    return $pdo;
}

/* ── Session ────────────────────────────────────────────────── */
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    session_start();
}

/* ── CSRF ───────────────────────────────────────────────────── */
function csrfToken(): string {
    if (empty($_SESSION['csrf_token']))
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function verifyCsrf(): void {
    $t = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $t)) {
        http_response_code(403);
        die(json_encode(['success'=>false,'message'=>'Invalid CSRF token.']));
    }
}

/* ── Tiny helpers ───────────────────────────────────────────── */
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function redirect(string $u): void { header("Location: $u"); exit; }
function requireAuth(string $role=''): void {
    if (empty($_SESSION['user_id'])) redirect(APP_URL.'/login.php');
    if ($role && $_SESSION['role']!==$role) redirect(APP_URL.'/dashboard.php');
}
function jsonResponse(bool $ok, string $msg, array $data=[]): void {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success'=>$ok,'message'=>$msg],$data)); exit;
}
function generateInvoice(): string { return 'INV-'.date('Ymd').'-'.strtoupper(substr(uniqid(),-5)); }
function formatCurrency(float $a): string { return '₹'.number_format($a,2); }
function timeAgo(string $dt): string {
    $t = time()-strtotime($dt);
    if($t<60)    return 'Just now';
    if($t<3600)  return floor($t/60).'m ago';
    if($t<86400) return floor($t/3600).'h ago';
    return floor($t/86400).'d ago';
}

/* ── Mailer lazy-loader ─────────────────────────────────────── */
function _ensureMailer(): bool {
    if (function_exists('sendEmail')) return true;
    $p = APP_ROOT.'/includes/Mailer.php';
    if (file_exists($p)) { require_once $p; return true; }
    return false;
}

/* ════════════════════════════════════════════════════════════
   UNIFIED NOTIFICATION + EMAIL
   ════════════════════════════════════════════════════════════ */
/**
 * Insert in-app notification and optionally send email.
 *
 * @param int    $userId
 * @param string $title
 * @param string $message
 * @param string $type      appointment|billing|prescription|system|alert
 * @param string $htmlEmail '' = auto-build alert; null = skip email
 * @return int|false
 */
function createNotification(int $userId, string $title, string $message,
                             string $type='system', ?string $htmlEmail=''): int|false
{
    $notifId = false;
    try {
        $db   = getDB();
        $stmt = $db->prepare("INSERT INTO notifications (user_id,title,message,type) VALUES(?,?,?,?)");
        $stmt->execute([$userId,$title,$message,$type]);
        $notifId = (int)$db->lastInsertId();
    } catch (Exception $e) { error_log('[Notif DB] '.$e->getMessage()); return false; }

    if ($htmlEmail === null) return $notifId;

    try {
        $db  = getDB();
        $usr = $db->prepare("SELECT name,email FROM users WHERE id=? AND status='active'");
        $usr->execute([$userId]); $user = $usr->fetch();
    } catch (Exception $e) { return $notifId; }

    if (!$user || empty($user['email'])) return $notifId;
    if (!_ensureMailer()) return $notifId;

    if ($htmlEmail === '') {
        $htmlEmail = mailTemplateAlert($title, $message,
            ['Type'=>ucfirst($type),'Recipient'=>$user['name']],
            in_array($type,['alert','billing']) ? 'warning' : 'info');
    }
    sendEmail([$user['email'],$user['name']], $title, $htmlEmail);
    return $notifId;
}

/* ── Event: Appointment Booked ──────────────────────────────── */
function notifyAppointmentBooked(int $apptId): void {
    if (!_ensureMailer()) return;
    try {
        $db=$db=getDB();
        $s=$db->prepare("
            SELECT a.*,
                   pu.id p_uid,pu.name patient_name,
                   du.id d_uid,du.name doctor_name,
                   dep.name dept_name
            FROM appointments a
            JOIN patients p ON a.patient_id=p.id JOIN users pu ON p.user_id=pu.id
            JOIN doctors  d ON a.doctor_id=d.id  JOIN users du ON d.user_id=du.id
            JOIN departments dep ON a.department_id=dep.id
            WHERE a.id=?");
        $s->execute([$apptId]); $a=$s->fetch(); if(!$a)return;

        $ds=date('d M Y',strtotime($a['appointment_date']));
        $ts=date('h:i A',strtotime($a['appointment_time']));
        $base=['patient'=>$a['patient_name'],'doctor'=>$a['doctor_name'],'department'=>$a['dept_name'],
               'date'=>$ds,'time'=>$ts,'token'=>$a['token_number'],'status'=>'pending','reason'=>$a['reason']??''];

        createNotification($a['p_uid'],'Appointment Booked ✅',
            "Appointment with {$a['doctor_name']} on {$ds} at {$ts}. Token #{$a['token_number']}. Awaiting approval.",
            'appointment',
            mailTemplateAppointment(array_merge($base,['title'=>'Appointment Booked Successfully ✅',
                'message'=>"Hello {$a['patient_name']}, your appointment has been booked!"])));

        createNotification($a['d_uid'],'New Appointment Request 📋',
            "New appointment from {$a['patient_name']} on {$ds} at {$ts}.",
            'appointment',
            mailTemplateAppointment(array_merge($base,['title'=>'New Appointment Request 📋',
                'message'=>"Hello {$a['doctor_name']}, please review this appointment request."])));

        $adm=$db->query("SELECT id FROM users WHERE role='admin' LIMIT 1")->fetch();
        if($adm) createNotification($adm['id'],'New Appointment Created',
            "{$a['patient_name']} booked with {$a['doctor_name']} on {$ds}.",
            'appointment',
            mailTemplateAlert('New Appointment','',[
                'Patient'=>$a['patient_name'],'Doctor'=>$a['doctor_name'],'Date'=>$ds],'info'));
    } catch(Exception $e){ error_log('[notifyApptBooked] '.$e->getMessage()); }
}

/* ── Event: Appointment Status Change ───────────────────────── */
function notifyAppointmentStatusChange(int $apptId, string $status): void {
    if (!_ensureMailer()) return;
    try {
        $db=$db=getDB();
        $s=$db->prepare("SELECT a.*,pu.id p_uid,pu.name patient_name,du.id d_uid,du.name doctor_name,dep.name dept_name
            FROM appointments a
            JOIN patients p ON a.patient_id=p.id JOIN users pu ON p.user_id=pu.id
            JOIN doctors  d ON a.doctor_id=d.id  JOIN users du ON d.user_id=du.id
            JOIN departments dep ON a.department_id=dep.id WHERE a.id=?");
        $s->execute([$apptId]); $a=$s->fetch(); if(!$a)return;

        $ds=date('d M Y',strtotime($a['appointment_date']));
        $ts=date('h:i A',strtotime($a['appointment_time']));
        $sc=ucfirst($status);
        $em=['approved'=>'✅','rejected'=>'❌','completed'=>'🏥','cancelled'=>'🚫'][$status]??'📋';

        $ptMsg=match($status){
            'approved'  =>"Your appointment with {$a['doctor_name']} on {$ds} at {$ts} is APPROVED.",
            'rejected'  =>"Your appointment on {$ds} has been declined. Please rebook.",
            'completed' =>"Appointment with {$a['doctor_name']} on {$ds} is completed. Check prescriptions.",
            default     =>"Appointment status updated to {$sc}.",
        };
        $base=['patient'=>$a['patient_name'],'doctor'=>$a['doctor_name'],'department'=>$a['dept_name'],
               'date'=>$ds,'time'=>$ts,'status'=>$status];
        createNotification($a['p_uid'],"Appointment {$sc} {$em}",$ptMsg,'appointment',
            mailTemplateAppointment(array_merge($base,['title'=>"Appointment {$sc} {$em}",'message'=>$ptMsg])));

        createNotification($a['d_uid'],"Appointment {$sc}",
            "{$a['patient_name']}'s appointment on {$ds} — {$sc}.",'appointment',
            mailTemplateAlert("Appointment {$sc}","",['Patient'=>$a['patient_name'],'Date'=>$ds,'Status'=>$sc],'info'));

        $adm=$db->query("SELECT id FROM users WHERE role='admin' LIMIT 1")->fetch();
        if($adm) createNotification($adm['id'],"Appointment {$sc}",
            "{$a['patient_name']} — {$a['doctor_name']} on {$ds}: {$sc}.",'system',null);
    } catch(Exception $e){ error_log('[notifyApptStatus] '.$e->getMessage()); }
}

/* ── Event: Prescription Created ────────────────────────────── */
function notifyPrescriptionCreated(int $rxId): void {
    if (!_ensureMailer()) return;
    try {
        $db=$db=getDB();
        $s=$db->prepare("SELECT pr.*,pu.id p_uid,pu.name patient_name,du.id d_uid,du.name doctor_name
            FROM prescriptions pr
            JOIN patients p ON pr.patient_id=p.id JOIN users pu ON p.user_id=pu.id
            JOIN doctors  d ON pr.doctor_id=d.id  JOIN users du ON d.user_id=du.id
            WHERE pr.id=?");
        $s->execute([$rxId]); $rx=$s->fetch(); if(!$rx)return;

        $meds=[]; try{$meds=json_decode($rx['medicines']??'[]',true)?:[];}catch(Exception $e){}

        $rd=['patient'=>$rx['patient_name'],'doctor'=>$rx['doctor_name'],'diagnosis'=>$rx['diagnosis'],
             'follow_up'=>$rx['follow_up_date']?date('d M Y',strtotime($rx['follow_up_date'])):'',
             'medicines'=>$meds,'advice'=>$rx['advice']??''];

        createNotification($rx['p_uid'],'Prescription Ready 💊',
            "Dr. {$rx['doctor_name']} issued prescription: {$rx['diagnosis']}.",'prescription',
            mailTemplatePrescription(array_merge($rd,['title'=>'Your Prescription 💊',
                'message'=>"Hello {$rx['patient_name']}, your prescription is ready."])));

        createNotification($rx['d_uid'],'Prescription Saved',
            "Prescription for {$rx['patient_name']} saved.",'prescription',
            mailTemplateAlert('Prescription Saved ✅',"Prescription for {$rx['patient_name']} recorded.",
                ['Patient'=>$rx['patient_name'],'Diagnosis'=>$rx['diagnosis']],'success'));

        $adm=$db->query("SELECT id FROM users WHERE role='admin' LIMIT 1")->fetch();
        if($adm) createNotification($adm['id'],'New Prescription',
            "Dr. {$rx['doctor_name']} → {$rx['patient_name']}.",'prescription',null);
    } catch(Exception $e){ error_log('[notifyRx] '.$e->getMessage()); }
}

/* ── Event: Billing Created ──────────────────────────────────── */
function notifyBillingCreated(int $billId): void {
    if (!_ensureMailer()) return;
    try {
        $db=$db=getDB();
        $s=$db->prepare("SELECT b.*,u.id p_uid,u.name patient_name
            FROM billing b JOIN patients p ON b.patient_id=p.id JOIN users u ON p.user_id=u.id
            WHERE b.id=?");
        $s->execute([$billId]); $b=$s->fetch(); if(!$b)return;
        $total=number_format($b['total_amount'],2);
        $bd=['Consultation'=>$b['consultation_fee'],'Medicines'=>$b['medicine_charge'],
             'Room'=>$b['room_charge'],'Tests'=>$b['test_charge'],'Other'=>$b['other_charge']];

        createNotification($b['p_uid'],'Invoice Generated 🧾',
            "Invoice {$b['invoice_number']}: ₹{$total}. Status: {$b['payment_status']}.",'billing',
            mailTemplateBilling(['title'=>'Invoice Ready 🧾','message'=>"Hello {$b['patient_name']}, your invoice is ready.",
                'patient'=>$b['patient_name'],'invoice'=>$b['invoice_number'],'total'=>$total,
                'status'=>$b['payment_status'],'method'=>$b['payment_method'],
                'date'=>date('d M Y',strtotime($b['created_at'])),'breakdown'=>$bd]));

        $adm=$db->query("SELECT id FROM users WHERE role='admin' LIMIT 1")->fetch();
        if($adm) createNotification($adm['id'],'New Invoice',
            "{$b['patient_name']}: {$b['invoice_number']} ₹{$total}.",'billing',
            mailTemplateAlert('New Invoice','',[
                'Patient'=>$b['patient_name'],'Invoice'=>$b['invoice_number'],'Amount'=>'₹'.$total],'info'));
    } catch(Exception $e){ error_log('[notifyBilling] '.$e->getMessage()); }
}

/* ── Event: Payment Completed ───────────────────────────────── */
function notifyPaymentCompleted(int $billId): void {
    if (!_ensureMailer()) return;
    try {
        $db=$db=getDB();
        $s=$db->prepare("SELECT b.*,u.id p_uid,u.name patient_name
            FROM billing b JOIN patients p ON b.patient_id=p.id JOIN users u ON p.user_id=u.id
            WHERE b.id=?");
        $s->execute([$billId]); $b=$s->fetch(); if(!$b)return;
        $total=number_format($b['total_amount'],2);

        createNotification($b['p_uid'],'Payment Confirmed ✅',
            "Payment of ₹{$total} for {$b['invoice_number']} received. Thank you!",'billing',
            mailTemplateBilling(['title'=>'Payment Confirmed ✅','message'=>"Thank you {$b['patient_name']}! Payment received.",
                'patient'=>$b['patient_name'],'invoice'=>$b['invoice_number'],'total'=>$total,
                'status'=>'paid','method'=>$b['payment_method'],'date'=>date('d M Y'),'breakdown'=>[]]));

        $adm=$db->query("SELECT id FROM users WHERE role='admin' LIMIT 1")->fetch();
        if($adm) createNotification($adm['id'],'Payment Received',
            "{$b['patient_name']}: ₹{$total} ({$b['invoice_number']}) paid.",'billing',
            mailTemplateAlert('Payment Received ✅','',
                ['Patient'=>$b['patient_name'],'Amount'=>'₹'.$total,'Invoice'=>$b['invoice_number']],'success'));
    } catch(Exception $e){ error_log('[notifyPayment] '.$e->getMessage()); }
}

/* ── Event: User Registered ─────────────────────────────────── */
function notifyUserRegistered(int $userId): void {
    if (!_ensureMailer()) return;
    try {
        $db=$db=getDB();
        $s=$db->prepare("SELECT id,name,email,role FROM users WHERE id=?");
        $s->execute([$userId]); $u=$s->fetch(); if(!$u)return;

        createNotification($userId,'Welcome to Smart Hospital 🎉',
            "Account created. Login: ".APP_URL.'/login.php','system',
            mailTemplateWelcome($u['name'],$u['role'],$u['email'],APP_URL.'/login.php'));

        $adm=$db->query("SELECT id FROM users WHERE role='admin' ORDER BY id LIMIT 1")->fetch();
        if($adm) createNotification($adm['id'],'New Registration',
            "{$u['name']} ({$u['email']}) registered as {$u['role']}.",'alert',
            mailTemplateAlert('New User Registered',"A new user just signed up.",
                ['Name'=>$u['name'],'Email'=>$u['email'],'Role'=>ucfirst($u['role']),'Time'=>date('d M Y, h:i A')],'info'));
    } catch(Exception $e){ error_log('[notifyRegister] '.$e->getMessage()); }
}
