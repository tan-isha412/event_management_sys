// ============================================
// app.js — Shared Utilities & Helpers
// Smart Event Management System
// FIX: Removed nested duplicate apiFetch definition
// ============================================

const API_BASE = '../backend';

// ---- Session helpers ----
const Session = {
  set(data) {
    localStorage.setItem('sem_user', JSON.stringify(data));
  },
  get() {
    const d = localStorage.getItem('sem_user');
    return d ? JSON.parse(d) : null;
  },
  clear() {
    localStorage.removeItem('sem_user');
  },
  require(role) {
    const user = this.get();
    if (!user) {
      window.location.href = 'login.html';
      return null;
    }
    if (role && user.role !== role) {
      window.location.href = 'login.html';
      return null;
    }
    return user;
  }
};

function normalizeRegistration(r) {
  return {
    EVENT_ID: r.event_id,
    EVENT_NAME: r.event_name,
    EVENT_DATE: r.date,
    VENUE: r.venue,
    CATEGORY_NAME: r.category,
    ATTENDANCE: (r.attendance || '').toUpperCase(),
    EVENT_STATUS: 'UPCOMING',
    FEEDBACK_GIVEN: r.feedback_given || 0
  };
}

function normalizeRecommendation(r) {
  return {
    EVENT_ID: r.event_id,
    EVENT_NAME: r.event_name,
    CATEGORY_NAME: r.category,
    RECOMMENDATION_SCORE: r.score,
    EVENT_DATE: new Date(),
    STATUS: 'UPCOMING'
  };
}

function normalizeEvent(e) {
  return {
    EVENT_ID: e.event_id,
    EVENT_NAME: e.name,
    EVENT_DATE: e.date,
    VENUE: e.venue,
    CATEGORY_NAME: e.category,
    REG_COUNT: e.registrations,
    STATUS: 'UPCOMING',
    ORGANIZER_NAME: 'College',
    MAX_CAPACITY: 100,
    REGISTRATION_FEE: 0
  };
}

// ---- Toast notifications ----
function showToast(message, type = 'info') {
  const container = document.getElementById('toast-container');
  if (!container) return;
  const icons = { success: '✅', error: '❌', info: 'ℹ️' };
  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  toast.innerHTML = `<span>${icons[type]}</span><span>${message}</span>`;
  container.appendChild(toast);
  setTimeout(() => {
    toast.style.animation = 'fadeOut 0.3s ease forwards';
    setTimeout(() => toast.remove(), 300);
  }, 3500);
}

// ---- API fetch wrapper (FIXED: was nested/duplicate, now single correct definition) ----
async function apiFetch(endpoint, options = {}) {
  try {
    const fullURL = `${API_BASE}/${endpoint}`;
    console.log("CALLING:", fullURL);   // 👈 ADD THIS

    const res = await fetch(fullURL, {
      headers: { 'Content-Type': 'application/json', ...options.headers },
      ...options
    });

    console.log("STATUS:", res.status); // 👈 ADD THIS

    const text = await res.text();
    console.log("RAW RESPONSE:", text);

    return JSON.parse(text);

  } catch (err) {
    console.error("FETCH FAILED:", err);
    alert("REAL ERROR: " + err.message); // 👈 THIS IS KEY
    return { success: false, message: err.message };
  }
}
// ---- Format date ----
function formatDate(dateStr) {
  if (!dateStr) return '—';
  const d = new Date(dateStr);
  return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatDateTime(dateStr) {
  if (!dateStr) return '—';
  const d = new Date(dateStr);
  return d.toLocaleDateString('en-IN', {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit'
  });
}

// ---- Status badge HTML ----
function statusBadge(status) {
  if (!status) return '';
  const s = status.toUpperCase();
  const cls = {
    'UPCOMING': 'badge-upcoming',
    'ONGOING': 'badge-ongoing',
    'COMPLETED': 'badge-completed',
    'CANCELLED': 'badge-cancelled',
    'PRESENT': 'badge-present',
    'ABSENT': 'badge-absent',
  }[s] || 'badge-upcoming';
  return `<span class="badge ${cls}">${status}</span>`;
}

// ---- Populate sidebar user info ----
function initSidebar() {
  const user = Session.get();
  if (!user) return;
  const nameEl = document.getElementById('sidebar-user-name');
  const roleEl = document.getElementById('sidebar-user-role');
  const avatarEl = document.getElementById('sidebar-avatar');
  if (nameEl) nameEl.textContent = user.name || 'User';
  if (roleEl) roleEl.textContent = user.role || '';
  if (avatarEl) avatarEl.textContent = (user.name || 'U')[0].toUpperCase();
}

// ---- Logout ----
function logout() {
  Session.clear();
  window.location.href = 'login.html';
}

// ---- Mobile sidebar toggle ----
function toggleSidebar() {
  document.querySelector('.sidebar')?.classList.toggle('open');
}

// ---- Active nav item ----
function setActiveNav(id) {
  document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
  document.getElementById(id)?.classList.add('active');
}

// ---- Modal helpers ----
function openModal(id) { document.getElementById(id)?.classList.add('active'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('active'); }

document.addEventListener('click', (e) => {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('active');
  }
});

// ---- Show/hide loading ----
function showLoading(containerId) {
  const el = document.getElementById(containerId);
  if (el) el.innerHTML = `<div class="loading-area"><div class="spinner"></div></div>`;
}

function showEmpty(containerId, message = 'No data found.') {
  const el = document.getElementById(containerId);
  if (el) el.innerHTML = `<div class="empty-state"><div class="icon">📭</div><h3>${message}</h3></div>`;
}

// ---- Star rating component ----
function initStarRating(containerId, inputId) {
  const container = document.getElementById(containerId);
  if (!container) return;
  let selected = 0;
  container.innerHTML = [1, 2, 3, 4, 5].map(i => `<span class="star" data-val="${i}">★</span>`).join('');
  container.querySelectorAll('.star').forEach(star => {
    star.addEventListener('click', () => {
      selected = parseInt(star.dataset.val);
      document.getElementById(inputId).value = selected;
      updateStars(selected);
    });
    star.addEventListener('mouseenter', () => updateStars(parseInt(star.dataset.val)));
    star.addEventListener('mouseleave', () => updateStars(selected));
  });
  function updateStars(val) {
    container.querySelectorAll('.star').forEach((s, i) => s.classList.toggle('active', i < val));
  }
}

// ---- Capacity progress ----
function capacityBar(registered, max) {
  const pct = Math.min(100, Math.round((registered / max) * 100));
  const cls = pct >= 90 ? 'red' : pct >= 70 ? 'amber' : '';
  return `
    <div style="display:flex;align-items:center;gap:8px">
      <div class="progress-bar" style="flex:1">
        <div class="progress-fill ${cls}" style="width:${pct}%"></div>
      </div>
      <span style="font-size:12px;color:var(--text-muted);white-space:nowrap">${registered}/${max}</span>
    </div>`;
}