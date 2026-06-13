/**
 * Single source of truth for role → dashboard URLs.
 * Never fall back to admin — unknown roles go to login/home.
 */

function _basePath() {
  const path = window.location.pathname;
  const idx  = path.indexOf('/frontend/');
  if (idx !== -1) return path.substring(0, idx) + '/frontend';
  const idx2 = path.indexOf('/pages/');
  if (idx2 !== -1) return path.substring(0, idx2);
  return '';
}

const ROLE_DASHBOARDS = {
  admin:            _basePath() + '/pages/admin/dashboard.html',
  pharmacist:       _basePath() + '/pages/professional/pharmacy-dashboard.html',
  med_rep:          _basePath() + '/pages/professional/medrep-dashboard.html',
  lab:              _basePath() + '/pages/professional/laboratory-dashboard.html',
  medical_services: _basePath() + '/pages/professional/medical-services-dashboard.html',
};

const LOGIN_URL       = _basePath() + '/pages/login.html';
const ADMIN_LOGIN_URL = _basePath() + '/pages/admin-login.html';

/** subscription role_type → users.role */
const ROLE_TYPE_TO_USER = {
  pharmacy:         'pharmacist',
  med_rep:          'med_rep',
  lab:              'lab',
  medical_services: 'medical_services',
};

function getDashboardUrl(role) {
  if (!role) return LOGIN_URL;
  const mapped = ROLE_TYPE_TO_USER[role] || role;
  return ROLE_DASHBOARDS[mapped] || LOGIN_URL;
}

function redirectAfterAuth(userOrRole, delayMs = 700) {
  const role = typeof userOrRole === 'string' ? userOrRole : userOrRole?.role;
  const url  = getDashboardUrl(role);
  if (delayMs > 0) {
    setTimeout(() => { window.location.href = url; }, delayMs);
  } else {
    window.location.replace(url);
  }
  return url;
}

function resolveLoginRedirect(user, queryRedirect) {
  const roleUrl = getDashboardUrl(user?.role);
  if (!queryRedirect) return roleUrl;
  const r = String(queryRedirect).trim();
  if (!r) return roleUrl;
  return roleUrl;
}