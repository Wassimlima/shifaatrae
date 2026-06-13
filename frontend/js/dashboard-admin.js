/**
 * SHIFAA DZ — Admin Dashboard Controller
 * Version: 6.1 — Fixed API paths & working savePlatformSettings
 */

'use strict';

let adminUser = null;

(function () {
        try { adminUser = JSON.parse(localStorage.getItem('shifaa_user')); } catch (e) {}
        if (!adminUser || adminUser.role !== 'admin') {
                window.location.replace('../admin-login.html');
                return;
        }
})();

const ROLE_LABELS = {
        admin: '🛡️ مدير',
        pharmacy: '💊 صيدلي',
        pharmacist: '💊 صيدلي',
        med_rep: '💊 مزود أدوية',
        lab: '🧪 مخبر',
        medical_services: '🏥 معدات طبية',
        patient: '👤 مريض',
};

document.addEventListener('DOMContentLoaded', function () {
        updateAdminDisplay();
        loadAdminStats();
        loadRecentUsers();
        initAdminNav();
        initUserManagement();

        document.getElementById('btn-save-settings')?.addEventListener('click', savePlatformSettings);
});

function updateAdminDisplay() {
        if (!adminUser) return;
        document.querySelectorAll('.admin-name-display, .user-info strong').forEach(el => el.textContent = adminUser.name || 'Admin');
        document.querySelectorAll('.pro-topbar-avatar, .user-avatar').forEach(el => el.textContent = (adminUser.name || 'A')[0].toUpperCase());
}

async function loadAdminStats() {
        try {
                const res = await api.get('/admin/stats.php');
                const d = res.data || res;
                const t = d.totals || {};

                setEl('stat-users', fmtNum(t.users));
                setEl('stat-pharmacies', fmtNum(t.pharmacies));
                setEl('stat-labs', fmtNum(t.labs));
                setEl('stat-medreps', fmtNum(t.med_reps));
                setEl('stat-medicines', fmtNum(t.medicines));
                setEl('stat-subscriptions', fmtNum(t.subscriptions));
                setEl('stat-donations', fmtNum(t.donations));

                setEl('ov-users', fmtNum(t.users));
                setEl('ov-pharmacies', fmtNum(t.pharmacies));
                setEl('ov-labs', fmtNum(t.labs));
                setEl('ov-medreps', fmtNum(t.med_reps));
                setEl('ov-medservices', fmtNum(t.med_services));
                setEl('ov-donations', fmtNum(t.donations));
                setEl('ov-equipment', fmtNum(t.equipment_count || t.med_services || 0));

                if (d.role_distribution) renderRoleDist(d.role_distribution);
                if (d.recent_users) renderRecentTable(d.recent_users);

        } catch (err) {
                console.error('[Admin] Stats failed:', err);
        }
}

function renderRoleDist(dist) {
        const el = document.getElementById('role-dist-list');
        if (!el) return;
        el.innerHTML = dist.map(r => `
                <div style="display:flex;justify-content:space-between;padding:.35rem 0;border-bottom:1px solid var(--border)">
                        <span>${ROLE_LABELS[r.role] || r.role}</span>
                        <strong>${fmtNum(r.count)}</strong>
                </div>
        `).join('');
}

function initAdminNav() {
        const navItems = document.querySelectorAll('.pro-nav-item, .admin-menu-item');
        const sections = document.querySelectorAll('.pro-section');

        const sectionMap = {
                'Dashboard': 'section-overview',
                'المستخدمون': 'section-users-manage',
                'الصيدليات': 'section-pharmacies',
                'المخابر': 'section-labs',
                'مزودو الأدوية': 'section-medreps',
                'الخدمات': 'section-medservices',
                'الإعلانات': 'section-ads',
                'الاشتراكات': 'section-subscriptions',
                'التبرعات': 'section-donations',
                'التحليلات': 'section-analytics',
                'إعدادات': 'section-settings'
        };

        function switchSection(sectionId) {
                if (!sectionId) return;
                sections.forEach(s => s.style.display = 'none');
                const target = document.getElementById(sectionId);
                if (target) target.style.display = 'block';

                if (sectionId === 'section-pharmacies') loadAllPharmacies();
                if (sectionId === 'section-labs') loadAllLabs();
                if (sectionId === 'section-donations') loadAllDonations();
                if (sectionId === 'section-medreps') loadAllMedReps();
        }

        navItems.forEach(item => {
                item.addEventListener('click', (e) => {
                        const text = item.textContent.trim();
                        let targetId = null;
                        for (const [key, id] of Object.entries(sectionMap)) {
                                if (text.includes(key)) { targetId = id; break; }
                        }
                        if (targetId) {
                                e.preventDefault();
                                navItems.forEach(n => n.classList.remove('active'));
                                item.classList.add('active');
                                switchSection(targetId);
                        }
                });
        });

        switchSection('section-overview');
}

async function loadAllPharmacies() {
        const tbody = document.getElementById('pharmacies-tbody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center">جاري التحميل...</td></tr>';
        try {
                const res = await api.get('/pharmacies/index.php', { limit: 100 });
                const list = res.pharmacies || [];
                tbody.innerHTML = list.length ? list.map(p => `
                        <tr>
                                <td><strong>${esc(p.name)}</strong></td>
                                <td>${esc(p.wilaya)}</td>
                                <td>${statusBadge(p.is_verified, 'موثق', 'معلق', '#d1fae5', '#059669', '#fef3c7', '#d97706')}</td>
                                <td><span class="pro-badge green">نشط</span></td>
                                <td><button class="pro-btn pro-btn-ghost pro-btn-sm">إدارة</button></td>
                        </tr>
                `).join('') : '<tr><td colspan="5" style="text-align:center;color:var(--muted)">لا توجد صيدليات</td></tr>';
        } catch { tbody.innerHTML = '<tr><td colspan="5" style="text-align:center">فشل التحميل</td></tr>'; }
}

async function loadAllLabs() {
        const tbody = document.getElementById('labs-tbody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center">جاري التحميل...</td></tr>';
        try {
                const res = await api.get('/labs/index.php', { limit: 100 });
                const list = res.labs || res.data || [];
                tbody.innerHTML = list.length ? list.map(l => `
                        <tr>
                                <td><strong>${esc(l.name_ar || l.name)}</strong></td>
                                <td>${esc(l.wilaya)}</td>
                                <td>${esc(l.phone)}</td>
                                <td><button class="pro-btn pro-btn-ghost pro-btn-sm">إدارة</button></td>
                        </tr>
                `).join('') : '<tr><td colspan="4" style="text-align:center;color:var(--muted)">لا توجد مخابر</td></tr>';
        } catch { tbody.innerHTML = '<tr><td colspan="4" style="text-align:center">فشل التحميل</td></tr>'; }
}

async function loadAllDonations() {
        const tbody = document.getElementById('donations-tbody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center">جاري التحميل...</td></tr>';
        try {
                const res = await api.get('/admin/donations-manage.php');
                const list = res.donations || res.data || [];
                tbody.innerHTML = list.length ? list.map(d => {
                        const st = d.status || (d.is_available ? 'approved' : 'pending');
                        const stColor = {approved:'#059669',rejected:'#dc2626',pending:'#d97706'}[st] || '#374151';
                        const stBg    = {approved:'#dcfce7',rejected:'#fee2e2',pending:'#fef3c7'}[st] || '#f3f4f6';
                        const stLabel = {approved:'مقبول',rejected:'مرفوض',pending:'قيد المراجعة'}[st] || st;
                        return `<tr>
                                <td><strong>${esc(d.item_name_ar || d.item_name)}</strong><br><small style="color:var(--muted)">${esc(d.donor_name||'')}</small></td>
                                <td>${esc(d.wilaya||'')}</td>
                                <td><span style="font-size:.75rem;padding:.15rem .6rem;border-radius:999px;font-weight:800;background:${stBg};color:${stColor}">${stLabel}</span></td>
                                <td style="font-size:.8rem">${formatDate(d.created_at)}</td>
                                <td>
                                  <button onclick="donationAction('approve',${d.id})" class="pro-btn pro-btn-ghost pro-btn-sm" style="color:#059669" title="قبول">✅ قبول</button>
                                  <button onclick="donationAction('reject',${d.id})"  class="pro-btn pro-btn-ghost pro-btn-sm" style="color:#d97706" title="رفض">⛔ رفض</button>
                                  <button onclick="donationAction('delete',${d.id})"  class="pro-btn pro-btn-ghost pro-btn-sm" style="color:#dc2626" title="حذف">🗑 حذف</button>
                                </td>
                        </tr>`;
                }).join('') : '<tr><td colspan="6" style="text-align:center;color:var(--muted)">لا توجد تبرعات</td></tr>';
        } catch { tbody.innerHTML = '<tr><td colspan="6" style="text-align:center">فشل التحميل</td></tr>'; }
}

window.donationAction = async function(action, id) {
        if (action === 'delete' && !confirm('هل أنت متأكد من حذف هذا التبرع؟')) return;
        try {
                const res = await api.post('/admin/donations-manage.php', { action, id });
                showAdminToast(res.message || 'تم', 'success');
                loadAllDonations();
        } catch (err) {
                showAdminToast('فشل الإجراء', 'error');
        }
};

async function loadAllMedReps() {
        const tbody = document.getElementById('medreps-tbody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center">جاري التحميل...</td></tr>';
        try {
                const res = await api.get('/admin/users.php', { role: 'med_rep' });
                const list = res.data || [];
                tbody.innerHTML = list.length ? list.map(u => `
                        <tr>
                                <td><strong>${esc(u.full_name)}</strong></td>
                                <td>${esc(u.phone || '—')}</td>
                                <td>${esc(u.email)}</td>
                                <td>${statusBadge(u.is_active, 'نشط', 'معطل', '#dbeafe', '#1d4ed8', '#fee2e2', '#dc2626')}</td>
                                <td><button class="pro-btn pro-btn-ghost pro-btn-sm">إدارة</button></td>
                        </tr>
                `).join('') : '<tr><td colspan="5" style="text-align:center;color:var(--muted)">لا يوجد مزودو أدوية</td></tr>';
        } catch { tbody.innerHTML = '<tr><td colspan="5" style="text-align:center">فشل التحميل</td></tr>'; }
}

function renderRecentTable(users) {
        const tbody = document.getElementById('recent-users-tbody');
        if (!tbody) return;
        tbody.innerHTML = users.map(u => `
                <tr>
                        <td>
                                <div style="display:flex;align-items:center;gap:.75rem">
                                        <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#4f46e5);display:flex;align-items:center;justify-content:center;color:white;font-weight:800">
                                                ${esc((u.full_name || 'U')[0].toUpperCase())}
                                        </div>
                                        <div>
                                                <strong>${esc(u.full_name)}</strong><br>
                                                <small style="color:#64748b">${esc(u.email)}</small>
                                        </div>
                                </div>
                        </td>
                        <td><span class="pro-badge">${esc(ROLE_LABELS[u.role] || u.role)}</span></td>
                        <td>${statusBadge(u.is_verified, 'موثّق', 'معلق', '#d1fae5', '#059669', '#fef3c7', '#d97706')}</td>
                        <td>${statusBadge(u.is_active, 'نشط', 'معطل', '#dbeafe', '#1d4ed8', '#fee2e2', '#dc2626')}</td>
                        <td style="font-size:.8rem;color:#64748b">${u.created_at ? new Date(u.created_at).toLocaleDateString('ar-DZ') : '--'}</td>
                        <td>
                                <button onclick="adminUserAction('toggle_verified', ${u.id})" class="pro-btn pro-btn-ghost pro-btn-sm">${u.is_verified ? 'إلغاء' : 'توثيق'}</button>
                                ${u.role !== 'admin' ? `<button onclick="adminUserAction('toggle_active', ${u.id})" class="pro-btn pro-btn-ghost pro-btn-sm">${u.is_active ? 'تعطيل' : 'تفعيل'}</button>` : ''}
                        </td>
                </tr>
        `).join('');
}

window.adminUserAction = async function(action, userId) {
        try {
                const res = await api.post('/admin/users.php', { action, user_id: userId });
                showAdminToast(res.message || 'تم التحديث', 'success');
                loadAdminStats();
                loadRecentUsers();
        } catch (err) {
                showAdminToast('فشل الإجراء', 'error');
        }
};

function initUserManagement() {
        document.getElementById('user-search-btn')?.addEventListener('click', async () => {
                const q = document.getElementById('user-search-input').value;
                const role = document.getElementById('user-role-filter').value;
                const tbody = document.getElementById('users-manage-tbody');
                if (!tbody) return;
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center">جاري البحث...</td></tr>';
                try {
                        const res = await api.get('/admin/users.php', { search: q, role });
                        const list = res.data || [];
                        tbody.innerHTML = list.length ? list.map(u => `
                                <tr>
                                        <td><strong>${esc(u.full_name)}</strong></td>
                                        <td>${esc(u.email)}</td>
                                        <td><span class="pro-badge">${esc(ROLE_LABELS[u.role] || u.role)}</span></td>
                                        <td>${statusBadge(u.is_verified, 'نعم', 'لا', '#d1fae5', '#059669', '#fef3c7', '#d97706')}</td>
                                        <td>${statusBadge(u.is_active, 'نشط', 'معطل', '#dbeafe', '#1d4ed8', '#fee2e2', '#dc2626')}</td>
                                        <td><button onclick="adminUserAction('toggle_active', ${u.id})" class="pro-btn pro-btn-ghost pro-btn-sm">عكس الحالة</button></td>
                                </tr>
                        `).join('') : '<tr><td colspan="6" style="text-align:center;color:var(--muted)">لا نتائج</td></tr>';
                } catch { tbody.innerHTML = '<tr><td colspan="6" style="text-align:center">خطأ في البحث</td></tr>'; }
        });
}

async function loadRecentUsers() {
        const tbody = document.getElementById('recent-users-tbody');
        if (!tbody) return;
        try {
                const res = await api.get('/admin/users.php', { limit: 10 });
                if (res.data) renderRecentTable(res.data);
        } catch (_) {}
}

async function savePlatformSettings() {
        try {
                await api.post('/platform/settings.php', {
                        platform_name: document.getElementById('s-platform-name')?.value,
                        contact_email: document.getElementById('s-contact-email')?.value,
                        contact_phone: document.getElementById('s-contact-phone')?.value,
                });
                showAdminToast('تم حفظ الإعدادات بنجاح', 'success');
        } catch (err) {
                showAdminToast(err.message || 'فشل الحفظ', 'error');
        }
}

function statusBadge(ok, okL, noL, okBg, okC, noBg, noC) {
        return `<span style="font-size:.75rem;padding:.15rem .6rem;border-radius:999px;font-weight:800;background:${ok ? okBg : noBg};color:${ok ? okC : noC}">${ok ? okL : noL}</span>`;
}

function setEl(id, val) { const el = document.getElementById(id); if (el) el.textContent = val; }
function fmtNum(n) { return Number(n || 0).toLocaleString('ar-DZ'); }
function esc(s) { return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

function showAdminToast(msg, type = 'info') {
        const t = document.createElement('div');
        t.style.cssText = `position:fixed;bottom:1.5rem;right:1.5rem;padding:.75rem 1.5rem;border-radius:12px;font-weight:700;z-index:9999;font-size:.875rem;background:${type==='success'?'#10b981':type==='error'?'#ef4444':'#3b82f6'};color:white;box-shadow:0 4px 20px rgba(0,0,0,.2)`;
        t.textContent = msg;
        document.body.appendChild(t);
        setTimeout(() => t.remove(), 3000);
}
