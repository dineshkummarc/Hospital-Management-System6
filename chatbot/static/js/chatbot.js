/* ==========================================================================
   chatbot.js
   Smart Hospital Assistant — floating chat widget behavior + AJAX layer.

   This file:
   1. Builds/controls the widget UI (open, close, minimize, clear, theme).
   2. Sends user messages to the Flask API via fetch() (AJAX) and renders
      whatever structured response comes back (plain text, department
      recommendation cards, doctor cards, emergency warnings).
   3. (Phase 7) Adds UI polish: a typewriter effect on live bot replies,
      per-message timestamps, a toggle-button icon that morphs between
      chat/close, a smoother minimize/restore transition, and small
      press/hover feedback on buttons and chips.

   It expects `window.HMS_CHATBOT_CONFIG` to already be defined by the PHP
   include (chatbot-widget.php) before this script runs, e.g.:

     window.HMS_CHATBOT_CONFIG = {
       apiBase: "http://localhost:5000",
       user: { isLoggedIn: false, id: null, role: null, name: null }
     };

   No build step, no framework — plain JS so it can be dropped into any
   PHP page as-is.
   ========================================================================== */

(function () {
  "use strict";

  const CONFIG = window.HMS_CHATBOT_CONFIG || { apiBase: "", user: { isLoggedIn: false } };
  const API_BASE = (CONFIG.apiBase || "").replace(/\/$/, "");
  const CHAT_ENDPOINT = `${API_BASE}/api/chat`;

  const STORAGE_KEY = "hms_chatbot_history_v1";
  const THEME_KEY = "hms_chatbot_theme";

  const PREFERS_REDUCED_MOTION = window.matchMedia &&
    window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  // ------------------------------------------------------------------
  // Element references (all markup already exists in chatbot-widget.php)
  // ------------------------------------------------------------------
  const root = document.getElementById("hms-chatbot-root");
  const toggleBtn = document.getElementById("hms-chatbot-toggle");
  const panel = document.getElementById("hms-chatbot-panel");
  const body = document.getElementById("hms-chat-body");
  const form = document.getElementById("hms-chat-form");
  const input = document.getElementById("hms-chat-input");
  const sendBtn = document.getElementById("hms-chat-send");
  const minimizeBtn = document.getElementById("hms-minimize-chat");
  const closeBtn = document.getElementById("hms-close-chat");
  const clearBtn = document.getElementById("hms-clear-chat");
  const themeBtn = document.getElementById("hms-theme-toggle");
  const suggestionsWrap = document.getElementById("hms-suggestions");

  if (!root || !panel) {
    console.warn("[hms-chatbot] Widget markup not found on this page.");
    return;
  }

  // ------------------------------------------------------------------
  // State
  // ------------------------------------------------------------------
  let isOpen = false;
  let isMinimized = false;
  let isSending = false;
  let hasStaggeredChipsOnce = false;
  let history = loadHistory();

  // ------------------------------------------------------------------
  // Theme
  // ------------------------------------------------------------------
  function initTheme() {
    const saved = localStorage.getItem(THEME_KEY);
    const preferred = saved || (window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light");
    root.setAttribute("data-theme", preferred);
  }

  function toggleTheme() {
    const current = root.getAttribute("data-theme") === "dark" ? "dark" : "light";
    const next = current === "dark" ? "light" : "dark";
    root.setAttribute("data-theme", next);
    localStorage.setItem(THEME_KEY, next);
  }

  // ------------------------------------------------------------------
  // Toggle button icon (Phase 7: morphs between chat bubble and close X)
  // ------------------------------------------------------------------
  function setToggleIcon(isOpenState) {
    const icon = toggleBtn.querySelector("i");
    if (!icon) return;
    icon.classList.remove("bi-chat-dots-fill", "bi-x-lg");
    icon.classList.add(isOpenState ? "bi-x-lg" : "bi-chat-dots-fill");
    if (!PREFERS_REDUCED_MOTION) {
      // Restart the pop-in animation even though we're reusing the same
      // element, by removing the class, forcing a reflow, then re-adding it.
      icon.classList.remove("hms-icon-pop");
      void icon.offsetWidth;
      icon.classList.add("hms-icon-pop");
    }
  }

  // ------------------------------------------------------------------
  // Panel open / close / minimize
  // ------------------------------------------------------------------
  function openPanel() {
    isOpen = true;
    isMinimized = false;
    panel.classList.remove("hms-hidden", "hms-minimized");
    toggleBtn.setAttribute("aria-expanded", "true");
    setToggleIcon(true);

    if (history.length === 0) {
      if (!PREFERS_REDUCED_MOTION && !hasStaggeredChipsOnce) {
        suggestionsWrap.classList.add("hms-stagger");
        hasStaggeredChipsOnce = true;
      }
      renderWelcomeMessage();
    }
    scrollToBottom();
    setTimeout(() => input && input.focus(), 150);
  }

  function closePanel() {
    isOpen = false;
    isMinimized = false;
    panel.classList.add("hms-hidden");
    panel.classList.remove("hms-minimized");
    toggleBtn.setAttribute("aria-expanded", "false");
    setToggleIcon(false);
  }

  function toggleMinimize() {
    isMinimized = !isMinimized;
    panel.classList.toggle("hms-minimized", isMinimized);
  }

  function togglePanel() {
    if (isOpen) {
      closePanel();
    } else {
      openPanel();
    }
  }

  // ------------------------------------------------------------------
  // Message rendering
  // ------------------------------------------------------------------
  function renderWelcomeMessage() {
    appendBotMessage({
      type: "text",
      reply:
        "Hi, I'm your Smart Hospital Assistant. Tell me your symptoms, ask about a department, " +
        "or use one of the quick options below to get started.",
    });
  }

  function scrollToBottom() {
    requestAnimationFrame(() => {
      body.scrollTop = body.scrollHeight;
    });
  }

  function escapeHtml(str) {
    const div = document.createElement("div");
    div.textContent = str == null ? "" : String(str);
    return div.innerHTML;
  }

  function formatTimestamp(ms) {
    try {
      return new Date(ms).toLocaleTimeString([], { hour: "numeric", minute: "2-digit" });
    } catch (e) {
      return "";
    }
  }

  function appendUserMessage(text) {
    const timestamp = Date.now();
    const row = document.createElement("div");
    row.className = "hms-msg-row hms-user";
    row.innerHTML = `
      <div class="hms-msg-avatar"><i class="bi bi-person-fill"></i></div>
      <div class="hms-bubble-wrap">
        <div class="hms-bubble"><p>${escapeHtml(text)}</p></div>
        <div class="hms-msg-time">${formatTimestamp(timestamp)}</div>
      </div>
    `;
    body.appendChild(row);
    history.push({ role: "user", text, timestamp });
    saveHistory();
    scrollToBottom();
  }

  /**
   * Builds the HTML for everything in a bot message EXCEPT the reply
   * text itself (department/doctor/appointment/prescription cards,
   * emergency warning, disclaimer). Kept separate from the reply so the
   * typewriter effect (Phase 7) can type the reply first, then reveal
   * the rest immediately after - matching how a person actually reads
   * a chat response.
   *
   * Supports this structured contract so every phase's payload renders
   * without any changes to this file:
   * {
   *   type: "text",
   *   reply: "plain text message",
   *   department: { name, icon, reason } | undefined,
   *   doctors: [{ name, specialization, experience, fee, department }] | undefined,
   *   appointments: [{ doctor_name, department, date, time, status }] | undefined,
   *   prescriptions: [{ doctor_name, prescribed_on, details }] | undefined,
   *   warning: "emergency warning text" | undefined,
   *   disclaimer: "medical disclaimer text" | undefined
   * }
   */
  function buildBotExtrasHtml(payload) {
    let extras = "";

    if (payload.department) {
      extras += `
        <div class="hms-card">
          <div class="hms-card-title">${escapeHtml(payload.department.icon || "🏥")} ${escapeHtml(payload.department.name)}</div>
          ${payload.department.reason ? `<div class="hms-card-meta">${escapeHtml(payload.department.reason)}</div>` : ""}
        </div>`;
    }

    if (Array.isArray(payload.doctors) && payload.doctors.length > 0) {
      extras += payload.doctors
        .map(
          (doc) => `
        <div class="hms-card">
          <div class="hms-card-title">${escapeHtml(doc.name)}</div>
          <div class="hms-card-meta">${escapeHtml(doc.specialization || "")}${doc.experience ? " • " + escapeHtml(doc.experience) + " yrs exp" : ""}</div>
          ${doc.department ? `<div class="hms-card-meta">${escapeHtml(doc.department)}</div>` : ""}
          ${doc.fee ? `<div class="hms-card-meta">Consultation fee: ${escapeHtml(doc.fee)}</div>` : ""}
        </div>`
        )
        .join("");
    }

    if (Array.isArray(payload.appointments) && payload.appointments.length > 0) {
      extras += payload.appointments
        .map(
          (appt) => `
        <div class="hms-card">
          <div class="hms-card-title">${escapeHtml(appt.doctor_name || "Doctor")}</div>
          ${appt.department ? `<div class="hms-card-meta">${escapeHtml(appt.department)}</div>` : ""}
          <div class="hms-card-meta">${escapeHtml(appt.date || "")}${appt.time ? " • " + escapeHtml(appt.time) : ""}</div>
          ${appt.status ? `<div class="hms-card-meta">Status: ${escapeHtml(appt.status)}</div>` : ""}
        </div>`
        )
        .join("");
    }

    if (Array.isArray(payload.prescriptions) && payload.prescriptions.length > 0) {
      extras += payload.prescriptions
        .map(
          (presc) => `
        <div class="hms-card">
          <div class="hms-card-title">${escapeHtml(presc.doctor_name || "Doctor")}</div>
          ${presc.prescribed_on ? `<div class="hms-card-meta">${escapeHtml(presc.prescribed_on)}</div>` : ""}
          ${presc.details ? `<div class="hms-card-meta">${escapeHtml(presc.details)}</div>` : ""}
        </div>`
        )
        .join("");
    }

    if (payload.warning) {
      extras += `
        <div class="hms-warning-banner">
          <i class="bi bi-exclamation-triangle-fill"></i>
          <span>${escapeHtml(payload.warning)}</span>
        </div>`;
    }

    if (payload.disclaimer) {
      extras += `<p class="hms-inline-disclaimer">${escapeHtml(payload.disclaimer)}</p>`;
    }

    return extras;
  }

  /**
   * Reveals `text` into `el` one character at a time. Speed scales down
   * for longer messages so a long doctor list summary doesn't take
   * forever to finish typing. Calls `onDone` when finished.
   */
  function typeText(el, text, onDone) {
    const total = text.length;
    const perCharDelay = total > 220 ? 4 : total > 120 ? 8 : 14;
    let i = 0;

    function step() {
      i++;
      el.textContent = text.slice(0, i);
      scrollToBottom();
      if (i < total) {
        setTimeout(step, perCharDelay);
      } else if (onDone) {
        onDone();
      }
    }
    step();
  }

  /**
   * Renders a bot response.
   *
   * @param {object} payload - the response contract described above.
   * @param {object} [options]
   * @param {boolean} [options.animate=false] - if true (and the user
   *   doesn't prefer reduced motion), types the reply text out character
   *   by character before revealing cards/warnings/disclaimer. Used for
   *   live responses only - replayed history and the welcome message
   *   render instantly.
   */
  function appendBotMessage(payload, options) {
    const opts = options || {};
    const animate = !!opts.animate && !PREFERS_REDUCED_MOTION && !!payload.reply;
    const timestamp = Date.now();

    const row = document.createElement("div");
    row.className = "hms-msg-row hms-bot";

    const bubble = document.createElement("div");
    bubble.className = "hms-bubble";

    const wrap = document.createElement("div");
    wrap.className = "hms-bubble-wrap";

    const timeEl = document.createElement("div");
    timeEl.className = "hms-msg-time";
    timeEl.textContent = formatTimestamp(timestamp);

    row.innerHTML = `<div class="hms-msg-avatar"><i class="bi bi-heart-pulse-fill"></i></div>`;
    wrap.appendChild(bubble);
    wrap.appendChild(timeEl);
    row.appendChild(wrap);
    body.appendChild(row);

    // Save to history immediately regardless of animation timing, so a
    // page refresh mid-typewriter still persists the full message.
    history.push({ role: "bot", payload, timestamp });
    saveHistory();

    const extrasHtml = buildBotExtrasHtml(payload);

    if (animate) {
      const p = document.createElement("p");
      bubble.appendChild(p);
      typeText(p, payload.reply, () => {
        bubble.insertAdjacentHTML("beforeend", extrasHtml);
        scrollToBottom();
      });
    } else {
      const replyHtml = payload.reply ? `<p>${escapeHtml(payload.reply)}</p>` : "";
      bubble.innerHTML = replyHtml + extrasHtml;
    }

    scrollToBottom();
  }

  function showTypingIndicator() {
    const row = document.createElement("div");
    row.className = "hms-msg-row hms-bot";
    row.id = "hms-typing-row";
    row.innerHTML = `
      <div class="hms-msg-avatar"><i class="bi bi-heart-pulse-fill"></i></div>
      <div class="hms-bubble">
        <div class="hms-typing"><span></span><span></span><span></span></div>
      </div>
    `;
    body.appendChild(row);
    scrollToBottom();
  }

  function hideTypingIndicator() {
    const row = document.getElementById("hms-typing-row");
    if (row) row.remove();
  }

  // ------------------------------------------------------------------
  // History persistence (per-browser, not tied to PHP session)
  // ------------------------------------------------------------------
  function saveHistory() {
    try {
      // Cap stored history so localStorage doesn't grow unbounded
      const trimmed = history.slice(-50);
      localStorage.setItem(STORAGE_KEY, JSON.stringify(trimmed));
    } catch (e) {
      // localStorage may be unavailable (private browsing, quota) - fail silently
    }
  }

  function loadHistory() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      return raw ? JSON.parse(raw) : [];
    } catch (e) {
      return [];
    }
  }

  function replayHistory() {
    history.forEach((entry) => {
      if (entry.role === "user") {
        const row = document.createElement("div");
        row.className = "hms-msg-row hms-user";
        row.innerHTML = `
          <div class="hms-msg-avatar"><i class="bi bi-person-fill"></i></div>
          <div class="hms-bubble-wrap">
            <div class="hms-bubble"><p>${escapeHtml(entry.text)}</p></div>
            <div class="hms-msg-time">${entry.timestamp ? formatTimestamp(entry.timestamp) : ""}</div>
          </div>
        `;
        body.appendChild(row);
      } else if (entry.role === "bot") {
        const payload = entry.payload || {};
        const row = document.createElement("div");
        row.className = "hms-msg-row hms-bot";
        const replyHtml = payload.reply ? `<p>${escapeHtml(payload.reply)}</p>` : "";
        const extrasHtml = buildBotExtrasHtml(payload);
        row.innerHTML = `
          <div class="hms-msg-avatar"><i class="bi bi-heart-pulse-fill"></i></div>
          <div class="hms-bubble-wrap">
            <div class="hms-bubble">${replyHtml}${extrasHtml}</div>
            <div class="hms-msg-time">${entry.timestamp ? formatTimestamp(entry.timestamp) : ""}</div>
          </div>
        `;
        body.appendChild(row);
      }
    });
    scrollToBottom();
  }

  function clearConversation() {
    history = [];
    saveHistory();
    body.innerHTML = "";
    renderWelcomeMessage();
  }

  // ------------------------------------------------------------------
  // AJAX: send message to Flask API
  // ------------------------------------------------------------------
  async function sendMessage(text) {
    const message = (text || "").trim();
    if (!message || isSending) return;

    appendUserMessage(message);
    input.value = "";
    setSending(true);
    showTypingIndicator();

    try {
      const response = await fetch(CHAT_ENDPOINT, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          message,
          user: CONFIG.user || {},
        }),
      });

      hideTypingIndicator();

      if (!response.ok) {
        throw new Error(`Server responded with status ${response.status}`);
      }

      const data = await response.json();
      appendBotMessage(data, { animate: true });
    } catch (err) {
      hideTypingIndicator();
      appendBotMessage({
        type: "text",
        reply:
          "Sorry, I couldn't reach the hospital assistant service right now. " +
          "Please try again in a moment, or contact the front desk for urgent help.",
      }, { animate: true });
      console.error("[hms-chatbot] Chat request failed:", err);
    } finally {
      setSending(false);
    }
  }

  function setSending(state) {
    isSending = state;
    sendBtn.disabled = state;
  }

  // ------------------------------------------------------------------
  // Event wiring
  // ------------------------------------------------------------------
  toggleBtn.addEventListener("click", togglePanel);
  closeBtn.addEventListener("click", closePanel);
  minimizeBtn.addEventListener("click", toggleMinimize);
  clearBtn.addEventListener("click", clearConversation);
  themeBtn.addEventListener("click", toggleTheme);

  form.addEventListener("submit", (e) => {
    e.preventDefault();
    sendMessage(input.value);
  });

  suggestionsWrap.addEventListener("click", (e) => {
    const chip = e.target.closest(".hms-chip");
    if (!chip) return;
    sendMessage(chip.dataset.message || chip.textContent);
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && isOpen) {
      closePanel();
    }
  });

  // ------------------------------------------------------------------
  // Init
  // ------------------------------------------------------------------
  initTheme();
  setToggleIcon(false);
  if (history.length > 0) {
    replayHistory();
  }
})();
