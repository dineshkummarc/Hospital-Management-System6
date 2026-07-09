<?php
ini_set('display_errors',1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
requireAuth();

$db = getDB();

// AJAX: mark single read
if if (!empty($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'mark_read') {
        $id = (int)$_POST['id'];
        $db->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")
           ->execute([$id, $_SESSION['user_id']]);
        jsonResponse(true, 'Marked as read.');
    }

    if ($action === 'mark_all_read') {
        $db->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")
           ->execute([$_SESSION['user_id']]);
        jsonResponse(true, 'All notifications marked as read.');
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $db->prepare("DELETE FROM notifications WHERE id=? AND user_id=?")
           ->execute([$id, $_SESSION['user_id']]);
        jsonResponse(true, 'Notification deleted.');
    }

    if ($action === 'delete_all') {
        $db->prepare("DELETE FROM notifications WHERE user_id=?")
           ->execute([$_SESSION['user_id']]);
        jsonResponse(true, 'All notifications cleared.');
    }
}

// Filters
$typeFilter = $_GET['type']   ?? '';
$readFilter = $_GET['read']   ?? '';
$search     = trim($_GET['search'] ?? '');

$where  = ['user_id = ?'];
$params = [$_SESSION['user_id']];

if ($typeFilter) { $where[] = 'type = ?';     $params[] = $typeFilter; }
if ($readFilter !== '') { $where[] = 'is_read = ?'; $params[] = (int)$readFilter; }
if ($search)     { $where[] = '(title LIKE ? OR message LIKE ?)'; $s="%$search%"; $params[]=$s; $params[]=$s; }

$stmt = $db->prepare("
    SELECT * FROM notifications
    WHERE " . implode(' AND ', $where) . "
    ORDER BY created_at DESC
");
$stmt->execute($params);
$notifications = $stmt->fetchAll();

// Counts
$totalCount  = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=?"); $totalCount->execute([$_SESSION['user_id']]);
$unreadCount = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0"); $unreadCount->execute([$_SESSION['user_id']]);
$totalCount  = $totalCount->fetchColumn();
$unreadCount = $unreadCount->fetchColumn();

// Count per type
$typeCounts = $db->prepare("SELECT type, COUNT(*) AS cnt FROM notifications WHERE user_id=? GROUP BY type");
$typeCounts->execute([$_SESSION['user_id']]);
$typeCounts = array_column($typeCounts->fetchAll(), 'cnt', 'type');

$typeConfig = [
    'appointment' => ['icon' => 'bi-calendar-check',    'color' => '#2563EB', 'bg' => '#EFF6FF', 'label' => 'Appointment'],
    'billing'     => ['icon' => 'bi-receipt',            'color' => '#F59E0B', 'bg' => '#FFFBEB', 'label' => 'Billing'],
    'prescription'=> ['icon' => 'bi-file-medical',       'color' => '#10B981', 'bg' => '#ECFDF5', 'label' => 'Prescription'],
    'alert'       => ['icon' => 'bi-exclamation-triangle','color' => '#EF4444', 'bg' => '#FEF2F2', 'label' => 'Alert'],
    'system'      => ['icon' => 'bi-gear',               'color' => '#6366F1', 'bg' => '#EEF2FF', 'label' => 'System'],
];

$role = $_SESSION['role'];
// Set correct back path depending on role
$dashPath = APP_URL . "/$role/dashboard.php";

$pageTitle  = 'All Notifications';
$activeMenu = '';
include '../includes/layout_header.php';
?>

<!-- Page Header -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
  <div>
    <h4 style="font-family:var(--font-display);font-weight:800;margin:0">
      <i class="bi bi-bell-fill me-2" style="color:var(--primary)"></i>Notifications
    </h4>
    <p style="color:#64748B;font-size:.875rem;margin:4px 0 0">
      <?= $unreadCount ?> unread · <?= $totalCount ?> total
    </p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if ($unreadCount > 0): ?>
    <button class="btn-outline-custom" onclick="markAllRead(this)" style="padding:8px 16px;font-size:.82rem">
      <i class="bi bi-check2-all"></i> Mark All Read
    </button>
    <?php endif; ?>
    <?php if ($totalCount > 0): ?>
    <button onclick="clearAll()" style="padding:8px 16px;border-radius:10px;border:1.5px solid #FECACA;background:#FFF;color:#EF4444;font-size:.82rem;font-weight:600;cursor:pointer;transition:all .2s"
      onmouseover="this.style.background='#FEE2E2'" onmouseout="this.style.background='#fff'">
      <i class="bi bi-trash3"></i> Clear All
    </button>
    <?php endif; ?>
  </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card blue" style="padding:18px 20px">
      <div class="stat-icon blue" style="width:42px;height:42px;font-size:1.1rem;margin-bottom:10px"><i class="bi bi-bell"></i></div>
      <div class="stat-value" style="font-size:1.6rem" data-count="<?= $totalCount ?>"><?= $totalCount ?></div>
      <div class="stat-label">Total</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card orange" style="padding:18px 20px">
      <div class="stat-icon orange" style="width:42px;height:42px;font-size:1.1rem;margin-bottom:10px"><i class="bi bi-bell-fill"></i></div>
      <div class="stat-value" style="font-size:1.6rem" data-count="<?= $unreadCount ?>" id="unread-count-display"><?= $unreadCount ?></div>
      <div class="stat-label">Unread</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card green" style="padding:18px 20px">
      <div class="stat-icon green" style="width:42px;height:42px;font-size:1.1rem;margin-bottom:10px"><i class="bi bi-check2-circle"></i></div>
      <div class="stat-value" style="font-size:1.6rem" data-count="<?= $totalCount - $unreadCount ?>"><?= $totalCount - $unreadCount ?></div>
      <div class="stat-label">Read</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card purple" style="padding:18px 20px">
      <div class="stat-icon purple" style="width:42px;height:42px;font-size:1.1rem;margin-bottom:10px"><i class="bi bi-calendar-check"></i></div>
      <div class="stat-value" style="font-size:1.6rem" data-count="<?= $typeCounts['appointment'] ?? 0 ?>"><?= $typeCounts['appointment'] ?? 0 ?></div>
      <div class="stat-label">Appointments</div>
    </div>
  </div>
</div>

<!-- Filters -->
<div class="card mb-4">
  <div class="card-body" style="padding:16px 20px">
    <form method="GET" class="row g-3 align-items-end">
      <div class="col-md-4">
        <label class="form-label" style="font-size:.8rem">Search</label>
        <div style="position:relative">
          <i class="bi bi-search" style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#94A3B8;font-size:.82rem"></i>
          <input type="search" name="search" class="form-control-custom" style="padding-left:34px"
                 placeholder="Search notifications…" value="<?= e($search) ?>">
        </div>
      </div>
      <div class="col-md-3">
        <label class="form-label" style="font-size:.8rem">Type</label>
        <select name="type" class="form-control-custom">
          <option value="">All Types</option>
          <?php foreach($typeConfig as $val => $cfg): ?>
          <option value="<?= $val ?>" <?= $typeFilter===$val?'selected':'' ?>>
            <?= $cfg['label'] ?> <?= isset($typeCounts[$val]) ? '('.$typeCounts[$val].')' : '' ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label" style="font-size:.8rem">Status</label>
        <select name="read" class="form-control-custom">
          <option value="">All</option>
          <option value="0" <?= $readFilter==='0'?'selected':'' ?>>Unread</option>
          <option value="1" <?= $readFilter==='1'?'selected':'' ?>>Read</option>
        </select>
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn-primary-custom" style="flex:1;justify-content:center;padding:9px 10px">
          <i class="bi bi-filter"></i> Filter
        </button>
        <a href="notifications.php" class="btn-outline-custom" style="padding:9px 10px;display:flex;align-items:center">
          <i class="bi bi-x"></i>
        </a>
      </div>
    </form>
  </div>
</div>

<!-- Type Quick Filters -->
<div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
  <a href="notifications.php" style="padding:6px 14px;border-radius:20px;border:1.5px solid;text-decoration:none;font-size:.78rem;font-weight:600;transition:all .2s;
    <?= $typeFilter==='' && $readFilter==='' ? 'background:var(--primary);border-color:var(--primary);color:#fff' : 'background:#fff;border-color:#E2E8F0;color:#64748B' ?>">
    All (<?= $totalCount ?>)
  </a>
  <a href="?read=0" style="padding:6px 14px;border-radius:20px;border:1.5px solid;text-decoration:none;font-size:.78rem;font-weight:600;transition:all .2s;
    <?= $readFilter==='0' && !$typeFilter ? 'background:#F59E0B;border-color:#F59E0B;color:#fff' : 'background:#fff;border-color:#E2E8F0;color:#64748B' ?>">
    🔵 Unread (<?= $unreadCount ?>)
  </a>
  <?php foreach($typeConfig as $tKey => $cfg): if(isset($typeCounts[$tKey]) && $typeCounts[$tKey] > 0): ?>
  <a href="?type=<?= $tKey ?>" style="padding:6px 14px;border-radius:20px;border:1.5px solid;text-decoration:none;font-size:.78rem;font-weight:600;transition:all .2s;
    <?= $typeFilter===$tKey ? "background:{$cfg['color']};border-color:{$cfg['color']};color:#fff" : 'background:#fff;border-color:#E2E8F0;color:#64748B' ?>">
    <i class="bi <?= $cfg['icon'] ?>"></i> <?= $cfg['label'] ?> (<?= $typeCounts[$tKey] ?>)
  </a>
  <?php endif; endforeach; ?>
</div>

<!-- Notifications List -->
<?php if (empty($notifications)): ?>
<div class="card">
  <div class="empty-state" style="padding:80px 20px">
    <i class="bi bi-bell-slash" style="font-size:3.5rem;color:#CBD5E1"></i>
    <p style="margin-top:14px;color:#94A3B8;font-size:1rem">
      <?= ($typeFilter || $readFilter !== '' || $search) ? 'No notifications match your filters.' : 'You have no notifications yet.' ?>
    </p>
    <?php if ($typeFilter || $readFilter !== '' || $search): ?>
    <a href="notifications.php" class="btn-outline-custom" style="margin-top:14px;display:inline-flex">
      <i class="bi bi-x-circle me-1"></i> Clear Filters
    </a>
    <?php endif; ?>
  </div>
</div>

<?php else: ?>

<!-- Grouped by Date -->
<?php
// Group notifications by date
$grouped = [];
foreach ($notifications as $n) {
    $date = date('Y-m-d', strtotime($n['created_at']));
    $grouped[$date][] = $n;
}
$today     = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
?>

<div id="notifications-wrapper">
<?php foreach ($grouped as $date => $items): ?>

  <!-- Date Group Header -->
  <div style="display:flex;align-items:center;gap:12px;margin:0 0 14px">
    <div style="height:1px;background:#E2E8F0;flex:1"></div>
    <span style="font-size:.75rem;font-weight:700;color:#94A3B8;text-transform:uppercase;letter-spacing:.08em;white-space:nowrap">
      <?php
        if ($date === $today)          echo '📅 Today';
        elseif ($date === $yesterday)  echo '📅 Yesterday';
        else echo '📅 ' . date('d M Y', strtotime($date));
      ?>
    </span>
    <div style="height:1px;background:#E2E8F0;flex:1"></div>
  </div>

  <!-- Notification Items -->
  <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:24px">
  <?php foreach ($items as $notif):
    $cfg = $typeConfig[$notif['type']] ?? $typeConfig['system'];
    $isUnread = !$notif['is_read'];
  ?>
  <div id="notif-item-<?= $notif['id'] ?>"
       class="notif-card <?= $isUnread ? 'unread' : '' ?>"
       style="background:#fff;border-radius:14px;padding:18px 20px;border:1.5px solid <?= $isUnread ? '#BFDBFE' : '#F1F5F9' ?>;
              box-shadow:<?= $isUnread ? '0 2px 12px rgba(37,99,235,.08)' : 'var(--shadow-sm)' ?>;
              display:flex;align-items:flex-start;gap:16px;transition:all .25s;position:relative;
              <?= $isUnread ? 'background:linear-gradient(135deg,#fff,#EFF6FF)' : '' ?>">

    <!-- Unread dot -->
    <?php if ($isUnread): ?>
    <div style="position:absolute;top:16px;right:16px;width:9px;height:9px;background:var(--primary);border-radius:50%;box-shadow:0 0 0 3px rgba(37,99,235,.2)"></div>
    <?php endif; ?>

    <!-- Icon -->
    <div style="width:48px;height:48px;border-radius:13px;background:<?= $cfg['bg'] ?>;display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:<?= $cfg['color'] ?>;flex-shrink:0">
      <i class="bi <?= $cfg['icon'] ?>"></i>
    </div>

    <!-- Content -->
    <div style="flex:1;min-width:0">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;flex-wrap:wrap">
        <span style="font-weight:700;font-size:.9rem;color:#0F172A"><?= e($notif['title']) ?></span>
        <span style="font-size:.68rem;font-weight:600;padding:2px 9px;border-radius:20px;background:<?= $cfg['bg'] ?>;color:<?= $cfg['color'] ?>;text-transform:capitalize">
          <?= $cfg['label'] ?>
        </span>
        <?php if ($isUnread): ?>
        <span style="font-size:.68rem;font-weight:700;padding:2px 9px;border-radius:20px;background:#DBEAFE;color:#1E40AF">NEW</span>
        <?php endif; ?>
      </div>
      <p style="font-size:.85rem;color:#475569;margin:0 0 8px;line-height:1.55"><?= e($notif['message']) ?></p>
      <div style="font-size:.75rem;color:#94A3B8;display:flex;align-items:center;gap:6px">
        <i class="bi bi-clock"></i>
        <?= date('d M Y, h:i A', strtotime($notif['created_at'])) ?>
        &nbsp;·&nbsp;
        <?= timeAgo($notif['created_at']) ?>
      </div>
    </div>

    <!-- Actions -->
    <div style="display:flex;flex-direction:column;gap:6px;flex-shrink:0">
      <?php if ($isUnread): ?>
      <button class="btn-sm-action success"
              title="Mark as read"
              onclick="markRead(<?= $notif['id'] ?>, this)">
        <i class="bi bi-check2"></i>
      </button>
      <?php endif; ?>
      <button class="btn-sm-action danger"
              title="Delete"
              onclick="deleteNotif(<?= $notif['id'] ?>, this)">
        <i class="bi bi-trash"></i>
      </button>
    </div>
  </div>
  <?php endforeach; ?>
  </div>

<?php endforeach; ?>
</div>

<!-- Pagination Info -->
<div style="text-align:center;padding:16px;font-size:.82rem;color:#94A3B8">
  Showing <?= count($notifications) ?> notification<?= count($notifications)!==1?'s':'' ?>
  <?= ($typeFilter || $readFilter!=='' || $search) ? ' (filtered)' : '' ?>
</div>

<?php endif; ?>

<script>
// Mark single notification as read
async function markRead(id, btn) {
  const res = await ajaxPost('notifications.php', { action: 'mark_read', id });
  if (res.success) {
    const card = document.getElementById('notif-item-' + id);
    if (card) {
      // Remove unread styling
      card.classList.remove('unread');
      card.style.border       = '1.5px solid #F1F5F9';
      card.style.boxShadow    = 'var(--shadow-sm)';
      card.style.background   = '#fff';

      // Remove dot
      const dot = card.querySelector('[style*="border-radius:50%"]');
      if (dot) dot.remove();

      // Remove NEW badge
      card.querySelectorAll('span').forEach(s => { if (s.textContent.trim()==='NEW') s.remove(); });

      // Remove the "mark read" button itself
      btn.closest('button').remove();

      // Update counter
      const counter = document.getElementById('unread-count-display');
      if (counter) {
        const cur = parseInt(counter.textContent) || 0;
        counter.textContent = Math.max(0, cur - 1);
      }
    }
    showToast('Done', 'Marked as read.', 'success');
  }
}

// Mark ALL as read
async function markAllRead(btn) {
  const res = await ajaxPost('notifications.php', { action: 'mark_all_read' }, btn);
  if (res.success) {
    document.querySelectorAll('.notif-card.unread').forEach(card => {
      card.classList.remove('unread');
      card.style.border     = '1.5px solid #F1F5F9';
      card.style.boxShadow  = 'var(--shadow-sm)';
      card.style.background = '#fff';
      card.querySelectorAll('span').forEach(s => { if (s.textContent.trim()==='NEW') s.remove(); });
      const dot = card.querySelector('[style*="border-radius:50%"]');
      if (dot) dot.remove();
    });
    // Remove all "mark read" buttons
    document.querySelectorAll('[onclick^="markRead"]').forEach(b => b.remove());
    const counter = document.getElementById('unread-count-display');
    if (counter) counter.textContent = '0';
    showToast('Done', res.message, 'success');
    btn.remove(); // remove "Mark All Read" button
  }
}

// Delete single notification
async function deleteNotif(id, btn) {
  confirmAction('Delete this notification?', async () => {
    const res = await ajaxPost('notifications.php', { action: 'delete', id });
    if (res.success) {
      const card = document.getElementById('notif-item-' + id);
      if (card) {
        card.style.transition = 'opacity .3s, transform .3s';
        card.style.opacity    = '0';
        card.style.transform  = 'translateX(20px)';
        setTimeout(() => card.remove(), 300);
      }
      showToast('Deleted', res.message, 'success');
    }
  });
}

// Clear all notifications
function clearAll() {
  confirmAction('Are you sure you want to <strong>delete all notifications</strong>? This cannot be undone.', async () => {
    const res = await ajaxPost('notifications.php', { action: 'delete_all' });
    if (res.success) {
      document.getElementById('notifications-wrapper')?.remove();
      showToast('Cleared', res.message, 'success');
      setTimeout(() => location.reload(), 1000);
    }
  });
}

// Hover effect on cards
document.querySelectorAll('.notif-card').forEach(card => {
  card.addEventListener('mouseenter', () => {
    if (!card.classList.contains('unread')) card.style.background = '#F8FAFC';
  });
  card.addEventListener('mouseleave', () => {
    if (!card.classList.contains('unread')) card.style.background = '#fff';
  });
});
</script>

<?php include '../includes/layout_footer.php'; ?>
