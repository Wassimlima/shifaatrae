// ── Shared Utilities ──────────────────────────────────────────────────────────

function formatNum(n) {
  if (n == null || isNaN(n)) return '--';
  return Number(n).toLocaleString('ar-DZ');
}

function formatDate(dateStr) {
  if (!dateStr) return '';
  try {
    return new Date(dateStr).toLocaleDateString('ar-DZ', { year: 'numeric', month: 'short', day: 'numeric' });
  } catch { return dateStr; }
}

function showToast(msg, type = 'info') {
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'toast-container';
    document.body.appendChild(container);
  }
  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.textContent = msg;
  container.appendChild(toast);
  setTimeout(() => toast.classList.add('show'), 50);
  setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 300); }, 3500);
}

function openModal(id) {
  const el = document.getElementById(id);
  if (el) {
    el.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    document.documentElement.style.overflow = 'hidden';
  }
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el) {
    el.classList.add('hidden');
    document.body.style.overflow = '';
    document.documentElement.style.overflow = '';
  }
}

function setEl(id, val) {
  const el = document.getElementById(id);
  if (el) el.textContent = val;
}

document.addEventListener('click', (e) => {
  if (e.target.classList.contains('modal-overlay')) {
    // If it's the welcome modal, save visited state
    if (e.target.id === 'welcome-modal') {
      localStorage.setItem('shifaa_visited', '1');
    }
    e.target.classList.add('hidden');
    document.body.style.overflow = '';
    document.documentElement.style.overflow = '';
  }
  if (e.target.dataset.modalClose) closeModal(e.target.dataset.modalClose);
});

// ── Navbar: show Dashboard when logged in, Login when not ────────────────────
function updateNavbar() {
  const user = (() => { try { return JSON.parse(localStorage.getItem('shifaa_user')); } catch { return null; } })();
  const loginBtn = document.getElementById('nav-login-btn');
  if (!loginBtn) return;

  if (user && user.role) {
    loginBtn.textContent = 'لوحة التحكم';
    loginBtn.href = (typeof getDashboardUrl === 'function') ? getDashboardUrl(user.role) : '#';
    loginBtn.style.display = '';
  } else {
    loginBtn.textContent = 'تسجيل الدخول';
    loginBtn.href = loginBtn.href.includes('login') ? loginBtn.href : 'login.html';
    loginBtn.style.display = '';
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.getElementById('navbar-toggle');
  const menu   = document.getElementById('mobile-menu');
  if (toggle && menu) {
    toggle.addEventListener('click', () => menu.classList.toggle('open'));
  }

  updateNavbar();

  // ── First-visit welcome modal ────────────────────────────────────────────
  const VISITED_KEY  = 'shifaa_visited';
  const welcomeModal = document.getElementById('welcome-modal');

  if (welcomeModal && !localStorage.getItem(VISITED_KEY)) {
    // Don't show if already logged in
    const user = (() => { try { return JSON.parse(localStorage.getItem('shifaa_user')); } catch { return null; } })();
    if (!user) {
      setTimeout(() => {
        welcomeModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
      }, 600);
    }
  }

  // Helper to resolve base path for navigation
  function basePath() {
    return window.location.pathname.includes('/pages/') ? '' : 'pages/';
  }

  // ✨ إنشاء حساب → login.html?tab=register
  document.getElementById('welcome-register')?.addEventListener('click', () => {
    localStorage.setItem(VISITED_KEY, '1');
    welcomeModal.classList.add('hidden');
    document.body.style.overflow = '';
    window.location.href = basePath() + 'login.html?tab=register';
  });

  // 🔐 تسجيل الدخول → login.html
  document.getElementById('welcome-login')?.addEventListener('click', () => {
    localStorage.setItem(VISITED_KEY, '1');
    welcomeModal.classList.add('hidden');
    document.body.style.overflow = '';
    window.location.href = basePath() + 'login.html';
  });

  // متابعة كزائر ← → close modal, save guest mode, never show again
  document.getElementById('welcome-guest')?.addEventListener('click', () => {
    localStorage.setItem(VISITED_KEY, '1');
    localStorage.setItem('shifaa_guest', '1');
    if (welcomeModal) {
      welcomeModal.classList.add('hidden');
      welcomeModal.style.display = 'none';
    }
    document.body.style.overflow = '';
    document.documentElement.style.overflow = '';
    document.querySelectorAll('.modal-overlay:not(.hidden)').forEach((el) => {
      el.classList.add('hidden');
    });
  });
});
