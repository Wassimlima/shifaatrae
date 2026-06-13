/**
 * SHIFAA DZ — Medical Equipment Dashboard Controller
 * Professional Medical Equipment Panel
 * Version: 4.0 — Functional Sidebar & Enhanced Stats
 */

'use strict';

let msUser = null;
let _msEditId = null;

(function () {
    try { msUser = JSON.parse(localStorage.getItem('shifaa_user')); } catch (e) {}
    if (!msUser || msUser.role !== 'medical_services') {
        window.location.replace('../login.html');
        return;
    }
})();

document.addEventListener('DOMContentLoaded', async function () {
    updateDisplay();
    loadMSStats();
    loadServices();
    initMSNav();
    initEventListeners();
});

function updateDisplay() {
    if (!msUser) return;
    document.querySelectorAll('.ms-name-display, .user-info strong').forEach(el => el.textContent = msUser.name || 'مزود معدات طبية');
    document.querySelectorAll('.user-avatar, .pro-topbar-avatar').forEach(el => el.textContent = (msUser.name || 'م')[0].toUpperCase());
}

async function loadMSStats() {
    try {
        const res = await api.get('/medical-services/my-services.php');
        const items = res.data || [];
        setEl('stat-products', items.length);
        setEl('ms-welcome-meta', `${items.length} معدة مفعلة حالياً`);
        setEl('stat-orders', '0');
        setEl('stat-notifs', '142');
        setEl('stat-sub-plan', 'موثق');
    } catch (err) {
        console.error('[MS Stats] Failed:', err);
    }
}

function initMSNav() {
    const navItems = document.querySelectorAll('.pro-nav-item');
    const productsBlock = document.getElementById('ms-products-block');
    
    navItems.forEach(item => {
        item.addEventListener('click', (e) => {
            const section = item.dataset.section;
            if (!section) return;
            e.preventDefault();
            
            navItems.forEach(n => n.classList.remove('active'));
            item.classList.add('active');

            if (section === 'section-overview') {
                window.scrollTo({ top: 0, behavior: 'smooth' });
                productsBlock.style.display = 'block';
            } else if (section === 'section-services') {
                productsBlock.scrollIntoView({ behavior: 'smooth', block: 'start' });
                productsBlock.style.display = 'block';
            }
        });
    });
}

async function loadServices() {
    const tbody = document.getElementById('ms-inventory-tbody');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:2rem"><div class="spinner"></div></td></tr>';

    try {
        const res = await api.get('/medical-services/my-services.php');
        const items = res.data || [];

        if (!items.length) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--muted)">لا توجد معدات مضافة. أضف معدتك الأولى!</td></tr>';
            return;
        }

        tbody.innerHTML = items.map(s => `
            <tr>
                <td><strong>${esc(s.name)}</strong><br><small style="color:var(--muted)">${esc(s.name_ar || '')}</small></td>
                <td><span class="pro-badge">${esc(s.category || 'عام')}</span></td>
                <td>${s.price ? Number(s.price).toLocaleString() + ' دج' : '--'}</td>
                <td><span class="pro-badge green">نشط</span></td>
                <td>
                    <button class="pro-btn pro-btn-ghost pro-btn-sm" onclick='openMsEdit(${JSON.stringify(s).replace(/'/g, "&#39;")})'>تعديل</button>
                    <button class="pro-btn pro-btn-ghost pro-btn-sm" style="color:var(--red)" onclick="deleteService(${s.id})">حذف</button>       
                </td>
            </tr>
        `).join('');
    } catch (err) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--red)">فشل تحميل المعدات</td></tr>';
    }
}

function initEventListeners() {
    document.getElementById('ms-btn-add')?.addEventListener('click', openMsAdd);
    document.getElementById('ms-inv-form')?.addEventListener('submit', submitService);
    document.querySelectorAll('[data-modal-close="ms-inv-modal"]').forEach(b => b.addEventListener('click', () => closeModal('ms-inv-modal')));
}

function openMsAdd() {
    _msEditId = null;
    document.getElementById('ms-inv-title').textContent = 'إضافة معدة جديدة';
    document.getElementById('ms-inv-form').reset();
    openModal('ms-inv-modal');
}

window.openMsEdit = function(service) {
    _msEditId = service.id;
    document.getElementById('ms-inv-title').textContent = 'تعديل المعدة';
    document.getElementById('ms-f-name').value = service.name || '';
    document.getElementById('ms-f-name-ar').value = service.name_ar || '';
    document.getElementById('ms-f-price').value = service.price || '';
    document.getElementById('ms-f-category').value = service.category || '';
    document.getElementById('ms-f-desc').value = service.description || '';
    openModal('ms-inv-modal');
};

async function submitService(e) {
    e.preventDefault();
    const btn = e.target.querySelector('[type="submit"]');
    btn.disabled = true;
    btn.textContent = 'جاري الحفظ...';

    const payload = {
        action: _msEditId ? 'update' : 'create',
        id: _msEditId,
        name: document.getElementById('ms-f-name').value,
        name_ar: document.getElementById('ms-f-name-ar').value,
        price: document.getElementById('ms-f-price').value,
        category: document.getElementById('ms-f-category').value,
        description: document.getElementById('ms-f-desc').value
    };

    try {
        await api.post('/medical-services/my-services.php', payload);
        showToast(_msEditId ? 'تم تحديث المعدة بنجاح' : 'تمت إضافة المعدة بنجاح', 'success');
        closeModal('ms-inv-modal');
        loadServices();
        loadMSStats();
    } catch (err) {
        showToast('فشل الحفظ', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'حفظ';
    }
}

window.deleteService = async function(id) {
    if (!confirm('هل تريد حذف هذه المعدة؟')) return;
    try {
        await api.post('/medical-services/my-services.php', { action: 'delete', id });
        showToast('تم حذف المعدة بنجاح', 'success');
        loadServices();
        loadMSStats();
    } catch (err) {
        showToast('فشل الحذف', 'error');
    }
};

function setEl(id, val) { const el = document.getElementById(id); if (el) el.textContent = val; }
function esc(s) { return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
