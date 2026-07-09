/**
 * Smart Hospital Management System — Main JavaScript
 */

/* ── Page Loader ────────────────────────────────────────────── */
window.addEventListener('load', () => {
  const loader = document.getElementById('page-loader');
  if (loader) {
    setTimeout(() => {
      loader.style.opacity = '0';
      setTimeout(() => loader.style.display = 'none', 500);
    }, 800);
  }
});

/* ── Sidebar Toggle ─────────────────────────────────────────── */
const sidebarToggle  = document.querySelector('.sidebar-toggle');
const sidebar        = document.querySelector('.sidebar');
const sidebarOverlay = document.querySelector('.sidebar-overlay');

if (sidebarToggle) {
  sidebarToggle.addEventListener('click', () => {
    sidebar.classList.toggle('open');
    sidebarOverlay.classList.toggle('show');
  });
}
if (sidebarOverlay) {
  sidebarOverlay.addEventListener('click', () => {
    sidebar.classList.remove('open');
    sidebarOverlay.classList.remove('show');
  });
}

/* ── Toast Notifications ────────────────────────────────────── */
function showToast(title, message, type = 'success') {
  const container = document.getElementById('toast-container') || (() => {
    const el = document.createElement('div');
    el.id = 'toast-container';
    document.body.appendChild(el);
    return el;
  })();

  const icons = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill', info: 'bi-info-circle-fill', warning: 'bi-exclamation-triangle-fill' };
  const toast = document.createElement('div');
  toast.className = `toast-item ${type}`;
  toast.innerHTML = `
    <i class="bi ${icons[type] || icons.info} toast-icon"></i>
    <div><div class="toast-title">${title}</div><div class="toast-message">${message}</div></div>
    <button onclick="this.closest('.toast-item').remove()" style="margin-left:auto;background:none;border:none;cursor:pointer;color:#94A3B8;font-size:1rem;padding:0 0 0 8px">&times;</button>
  `;
  container.appendChild(toast);
  setTimeout(() => toast.remove(), 4500);
}

/* ── Modal System ───────────────────────────────────────────── */
function openModal(id)  {
  const m = document.getElementById(id);
  if (m) { m.classList.add('show'); document.body.style.overflow = 'hidden'; }
}
function closeModal(id) {
  const m = document.getElementById(id);
  if (m) { m.classList.remove('show'); document.body.style.overflow = ''; }
}

// Close modal on backdrop click
document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-custom')) {
    e.target.classList.remove('show');
    document.body.style.overflow = '';
  }
});
// Close on Escape
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-custom.show').forEach(m => { m.classList.remove('show'); document.body.style.overflow = ''; });
  }
});

/* ── Confirm Dialog ─────────────────────────────────────────── */
function confirmAction(message, callback) {
  const existing = document.getElementById('confirm-modal');
  if (existing) existing.remove();

  const modal = document.createElement('div');
  modal.id = 'confirm-modal';
  modal.className = 'modal-custom show';
  modal.innerHTML = `
    <div class="modal-box" style="max-width:420px">
      <div class="modal-header-custom">
        <h5><i class="bi bi-exclamation-triangle text-warning me-2"></i>Confirm Action</h5>
        <button class="modal-close" onclick="document.getElementById('confirm-modal').remove();document.body.style.overflow=''">&times;</button>
      </div>
      <div class="modal-body-custom">
        <p style="color:#475569;margin:0">${message}</p>
      </div>
      <div class="modal-footer-custom">
        <button onclick="document.getElementById('confirm-modal').remove();document.body.style.overflow=''" 
                style="padding:9px 20px;border-radius:10px;border:1.5px solid #E2E8F0;background:#fff;cursor:pointer;font-weight:600">Cancel</button>
        <button id="confirm-ok" class="btn-primary-custom" style="background:linear-gradient(135deg,#EF4444,#DC2626)">
          <i class="bi bi-check2"></i> Confirm
        </button>
      </div>
    </div>`;
  document.body.appendChild(modal);
  document.body.style.overflow = 'hidden';
  document.getElementById('confirm-ok').addEventListener('click', () => {
    modal.remove();
    document.body.style.overflow = '';
    callback();
  });
}

/* ── AJAX Helper ────────────────────────────────────────────── */
async function ajaxPost(url, data, btn = null) {
  let origHTML = '';
  if (btn) {
    origHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Processing...';
  }
  try {
    const fd = data instanceof FormData ? data : (() => {
      const f = new FormData();
      Object.entries(data).forEach(([k, v]) => f.append(k, v));
      return f;
    })();
    const res = await fetch(url, { method: 'POST', body: fd });

    // Detect non-JSON responses (PHP errors outputting HTML)
    const ct = res.headers.get('content-type') || '';
    if (!ct.includes('application/json')) {
      const text = await res.text();
      // Extract first line of PHP error for a useful message
      const firstLine = text.replace(/<[^>]+>/g, '').trim().split('\n')[0].substring(0, 200);
      return {
        success: false,
        message: 'Server error — ' + (firstLine || 'unexpected response. Check PHP error logs.')
      };
    }
    return await res.json();
  } catch (err) {
    // True network failure (server down, CORS, etc.)
    return {
      success: false,
      message: 'Could not reach the server. Check that XAMPP Apache is running.'
    };
  } finally {
    if (btn) { btn.disabled = false; btn.innerHTML = origHTML; }
  }
}

/* ── Live Search in Tables ──────────────────────────────────── */
function initTableSearch(inputId, tableId) {
  const input = document.getElementById(inputId);
  const table = document.getElementById(tableId);
  if (!input || !table) return;
  input.addEventListener('input', () => {
    const q = input.value.toLowerCase();
    table.querySelectorAll('tbody tr').forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
}

/* ── Form Validation ────────────────────────────────────────── */
function validateForm(formId) {
  const form = document.getElementById(formId);
  if (!form) return true;
  let valid = true;
  form.querySelectorAll('[required]').forEach(field => {
    const err = field.parentElement.querySelector('.invalid-feedback');
    if (!field.value.trim()) {
      field.classList.add('is-invalid');
      if (err) err.style.display = 'block';
      valid = false;
    } else {
      field.classList.remove('is-invalid');
      if (err) err.style.display = 'none';
    }
  });
  return valid;
}

/* ── Number Counter Animation ───────────────────────────────── */
function animateCounters() {
  document.querySelectorAll('[data-count]').forEach(el => {
    const target = parseInt(el.dataset.count);
    let current = 0;
    const step = Math.ceil(target / 50);
    const timer = setInterval(() => {
      current = Math.min(current + step, target);
      el.textContent = current.toLocaleString();
      if (current >= target) clearInterval(timer);
    }, 20);
  });
}
document.addEventListener('DOMContentLoaded', animateCounters);

/* ── Date Helpers ───────────────────────────────────────────── */
function formatDate(d) {
  return new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

/* ── Print Helper ───────────────────────────────────────────── */
function printDiv(divId) {
  const content = document.getElementById(divId).innerHTML;
  const win = window.open('', '_blank');
  win.document.write(`<html><head><title>Print</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>body{font-family:'Inter',sans-serif;padding:24px}@media print{.no-print{display:none}}</style>
    </head><body>${content}<script>window.onload=()=>window.print()<\/script></body></html>`);
  win.document.close();
}

/* ── Notifications dropdown ─────────────────────────────────── */
async function loadNotifications() {
  const container = document.getElementById('notif-list');
  if (!container) return;

  try {
    const res = await fetch('/hospital_management/ajax/get_notifications.php');
    const data = await res.json();
    if (data.notifications?.length) {
      container.innerHTML = data.notifications.map(n => `
        <div class="notif-item" style="padding:12px 16px;border-bottom:1px solid #F1F5F9;cursor:pointer" onclick="markRead(${n.id})">
          <div style="font-size:.82rem;font-weight:600;color:#0F172A">${n.title}</div>
          <div style="font-size:.78rem;color:#64748B">${n.message}</div>
          <div style="font-size:.72rem;color:#94A3B8;margin-top:4px">${n.time_ago}</div>
        </div>`).join('');
    } else {
      container.innerHTML = '<div style="padding:24px;text-align:center;color:#94A3B8;font-size:.875rem">No new notifications</div>';
    }
  } catch(e) {}
}

async function markRead(id) {
  await fetch('/hospital_management/ajax/mark_notification_read.php', { method:'POST', body: new URLSearchParams({id}) });
}
