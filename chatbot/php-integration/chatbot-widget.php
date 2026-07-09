<?php
/**
 * chatbot-widget.php
 * -------------------
 * Drop-in include that adds the floating "Smart Hospital Assistant"
 * widget to any page in your existing PHP Hospital Management System.
 *
 * This file does NOT touch your login, session handling, routing, or
 * database code. It only READS session variables (if present) so the
 * chatbot can later answer things like "my appointment" for logged-in
 * users (wired up in Phase 5).
 *
 * -------------------------------------------------------------------
 * HOW TO USE
 * -------------------------------------------------------------------
 * 1. Copy this file (and the accompanying static/ folder, if you chose
 *    the "self-hosted" option in README.md) into your PHP project,
 *    e.g. into an existing "includes/" or "partials/" folder.
 *
 * 2. In your shared footer/layout file — the one file already included
 *    on every page (often footer.php, layout.php, or similar) — add
 *    ONE line right before the closing </body> tag:
 *
 *        <?php include 'includes/chatbot-widget.php'; ?>
 *
 *    That's it. No other PHP file needs to change.
 *
 * 3. Update $chatbotApiBase below to point at wherever your Flask
 *    service actually runs (localhost while developing, your real
 *    domain in production).
 *
 * 4. IMPORTANT: adjust the session variable names in the "Read the
 *    logged-in user" section below to match your project's actual
 *    session keys (these are best-effort common names — your login
 *    module may name them differently).
 */

// Only start a session if one isn't already running (your login system
// almost certainly already calls session_start() elsewhere - this is
// just a safety net so the widget doesn't break standalone pages).
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---------------------------------------------------------------------
// 1. Where is the Flask API running?
// ---------------------------------------------------------------------
$chatbotApiBase = "http://localhost:5000"; // change for production

// ---------------------------------------------------------------------
// 2. Read the logged-in user from your EXISTING session (read-only).
//    Update the $_SESSION[...] keys below to match your real login
//    code (check your login.php / auth logic for the exact key names).
// ---------------------------------------------------------------------
$chatbotUserId   = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
$chatbotUserRole = isset($_SESSION['role']) ? $_SESSION['role'] : null;   // e.g. 'patient' | 'doctor' | 'admin'
$chatbotUserName = isset($_SESSION['name']) ? $_SESSION['name'] : null;

$chatbotIsLoggedIn = $chatbotUserId !== null;
?>

<!-- ================================================================
     Smart Hospital Assistant — floating widget
     Safe to include on every page. Namespaced with "hms-" so it will
     not collide with existing styles/scripts/IDs in your project.
     ================================================================ -->

<link rel="stylesheet" href="/hospital_management/assets/css/chatbot.css">

<script>
  window.HMS_CHATBOT_CONFIG = {
    apiBase: <?php echo json_encode($chatbotApiBase); ?>,
    user: {
      isLoggedIn: <?php echo $chatbotIsLoggedIn ? 'true' : 'false'; ?>,
      id: <?php echo json_encode($chatbotUserId); ?>,
      role: <?php echo json_encode($chatbotUserRole); ?>,
      name: <?php echo json_encode($chatbotUserName); ?>
    }
  };
</script>

<div id="hms-chatbot-root" data-theme="light">

  <button id="hms-chatbot-toggle" aria-label="Open Smart Hospital Assistant" aria-expanded="false">
    <i class="bi bi-chat-dots-fill"></i>
    <span class="hms-toggle-badge" aria-hidden="true"></span>
  </button>

  <div id="hms-chatbot-panel" class="hms-hidden" role="dialog" aria-modal="false" aria-label="Smart Hospital Assistant chat">

    <div class="hms-chat-header">
      <div class="hms-bot-identity">
        <div class="hms-bot-avatar"><i class="bi bi-hospital"></i></div>
        <div>
          <div class="hms-bot-name">Hospital Assistant</div>
          <div class="hms-bot-status"><span class="hms-status-dot"></span> Online</div>
        </div>
      </div>
      <div class="hms-header-actions">
        <button id="hms-theme-toggle" type="button" title="Toggle theme" aria-label="Toggle dark/light theme">
          <i class="bi bi-moon-stars-fill"></i>
        </button>
        <button id="hms-clear-chat" type="button" title="Clear conversation" aria-label="Clear conversation">
          <i class="bi bi-trash3-fill"></i>
        </button>
        <button id="hms-minimize-chat" type="button" title="Minimize" aria-label="Minimize chat">
          <i class="bi bi-dash-lg"></i>
        </button>
        <button id="hms-close-chat" type="button" title="Close" aria-label="Close chat">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
    </div>

    <div class="hms-chat-body" id="hms-chat-body"></div>

    <div class="hms-suggestions" id="hms-suggestions">
      <button type="button" class="hms-chip" data-message="I want to check my symptoms">🩺 Check Symptoms</button>
      <button type="button" class="hms-chip" data-message="Help me find a doctor">👨‍⚕️ Find Doctor</button>
      <button type="button" class="hms-chip" data-message="Show my appointment">📅 My Appointment</button>
      <button type="button" class="hms-chip" data-message="Show my prescription">💊 My Prescription</button>
      <button type="button" class="hms-chip" data-message="What departments do you have?">🏥 Departments</button>
      <button type="button" class="hms-chip" data-message="How can I contact the hospital?">📞 Contact</button>
    </div>

    <form id="hms-chat-form" class="hms-chat-input-row">
      <input type="text" id="hms-chat-input" placeholder="Type your message..." autocomplete="off" />
      <button type="submit" id="hms-chat-send" aria-label="Send message"><i class="bi bi-send-fill"></i></button>
    </form>

    <div class="hms-disclaimer">
      This recommendation is based only on the symptoms you entered and is not a medical diagnosis.
      Please consult a qualified doctor.
    </div>

  </div>
</div>

<script src="/hospital_management/assets/js/chatbot.js"></script>
