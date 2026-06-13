/**
 * SHIFAA DZ — Laboratory Dashboard Controller
 * Professional Lab Panel
 * Version: 4.0 — Functional Sidebar & Enhanced Stats
 */

'use strict';

let labUser = null;
let editingAnalysisId = null;

(function () {
    try { labUser = JSON.parse(localStorage.getItem('shifaa_user')); } catch (e) {}
    if (!labUser || labUser.role !== 'lab') {
        window.location.replace('../login.html');
        return;
    }
})();

document.addEventListener('DOMContentLoaded', async function () {
    updateDisplay();
    loadLabStats();
    loadAnalyses();
    initLabNav();
    initEventListeners();
});

function updateDisplay() {
    if (!labUser) return;
    document.querySelectorAll('.lab-name-display, .brand-text strong, .user-info strong').forEach(el => el.textContent = labUser.name || 'المخبر');
    document.querySelectorAll('.user-avatar, .pro-topbar-avatar').forEach(el => el.textContent = (labUser.name || 'م')[0].toUpperCase());
    setEl('lab-welcome-name', labUser.name);
}

async function loadLabStats() {
    try {
        const res = await api.get('/labs/my-analyses.php');
        const items = res.data || [];
        setEl('stat-analyses', items.length);
        setEl('stat-analyses-count', items.length);
        setEl('stat-views', '124'); // Mock or from real meta if available
        setEl('stat-sub-plan', 'مهني');
        setEl('stat-sub-expiry', '31-12-2025');
        setEl('lab-welcome-sub', `${items.length} تحليل مدرج حالياً`);
    } catch (err) {
        console.error('[Lab Stats] Failed:', err);
    }
}

function initLabNav() {
    const navItems = document.querySelectorAll('.pro-nav-item');
    const analysesBlock = document.getElementById('lab-analyses-block');
    
    navItems.forEach(item => {
        item.addEventListener('click', (e) => {
            const section = item.dataset.section;
            if (!section) return;
            e.preventDefault();
            
            navItems.forEach(n => n.classList.remove('active'));
            item.classList.add('active');

            if (section === 'section-overview') {
                window.scrollTo({ top: 0, behavior: 'smooth' });
                analysesBlock.style.display = 'block';
            } else if (section === 'section-analyses') {
                analysesBlock.scrollIntoView({ behavior: 'smooth', block: 'start' });
                analysesBlock.style.display = 'block';
            }
        });
    });
}

async function loadAnalyses() {
    const tbody = document.getElementById('analyses-tbody');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:2rem"><div class="spinner"></div></td></tr>';

    try {
        const res = await api.get('/labs/my-analyses.php');
        const items = res.data || [];

        if (!items.length) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--muted)">لا توجد تحاليل مضافة. أضف أول تحليل!</td></tr>';
            return;
        }

        tbody.innerHTML = items.map(a => `
            <tr>
                <td><strong>${esc(a.name)}</strong><br><small style="color:var(--muted)">${esc(a.name_ar || '')}</small></td>
                <td><span class="pro-badge">${esc(a.category || '--')}</span></td>
                <td>${a.price ? Number(a.price).toLocaleString() + ' دج' : '--'}</td>
                <td>${esc(a.preparation_time || '--')}</td>
                <td>
                    <button class="pro-btn pro-btn-ghost pro-btn-sm" onclick='openEditAnalysis(${JSON.stringify(a).replace(/'/g, "&#39;")})'>تعديل</button>
                    <button class="pro-btn pro-btn-ghost pro-btn-sm" style="color:var(--red)" onclick="deleteAnalysis(${a.id})">حذف</button>      
                </td>
            </tr>
        `).join('');
    } catch (err) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--red)">فشل التحميل</td></tr>';
    }
}

function initEventListeners() {
    document.getElementById('btn-add-analysis')?.addEventListener('click', openAddModal);
    document.getElementById('analysis-form')?.addEventListener('submit', submitAnalysis);
    document.getElementById('modal-close')?.addEventListener('click', () => closeModal('analysis-modal'));
}

function openAddModal() {
    editingAnalysisId = null;
    document.getElementById('modal-title').textContent = 'إضافة تحليل جديد';
    document.getElementById('analysis-form').reset();
    openModal('analysis-modal');
}

window.openEditAnalysis = function(analysis) {
    editingAnalysisId = analysis.id;
    document.getElementById('modal-title').textContent = 'تعديل التحليل';
    document.getElementById('f-name').value = analysis.name || '';
    document.getElementById('f-namear').value = analysis.name_ar || '';
    document.getElementById('f-category').value = analysis.category || '';
    document.getElementById('f-price').value = analysis.price || '';
    document.getElementById('f-time').value = analysis.preparation_time || '';
    document.getElementById('f-desc').value = analysis.description || '';
    openModal('analysis-modal');
};

async function submitAnalysis(e) {
    e.preventDefault();
    const btn = e.target.querySelector('[type="submit"]');
    btn.disabled = true;
    btn.textContent = 'جاري الحفظ...';

    const payload = {
        action: editingAnalysisId ? 'update' : 'create',
        id: editingAnalysisId,
        name: document.getElementById('f-name').value,
        name_ar: document.getElementById('f-namear').value,
        category: document.getElementById('f-category').value,
        price: document.getElementById('f-price').value,
        preparation_time: document.getElementById('f-time').value,
        description: document.getElementById('f-desc').value
    };

    try {
        await api.post('/labs/my-analyses.php', payload);
        showToast(editingAnalysisId ? 'تم التحديث بنجاح' : 'تمت الإضافة بنجاح', 'success');
        closeModal('analysis-modal');
        loadAnalyses();
        loadLabStats();
    } catch (err) {
        showToast('فشل الحفظ', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'حفظ';
    }
}

window.deleteAnalysis = async function(id) {
    if (!confirm('هل تريد حذف هذا التحليل؟')) return;
    try {
        await api.post('/labs/my-analyses.php', { action: 'delete', id });
        showToast('تم الحذف بنجاح', 'success');
        loadAnalyses();
        loadLabStats();
    } catch (err) {
        showToast('فشل الحذف', 'error');
    }
};

function setEl(id, val) { const el = document.getElementById(id); if (el) el.textContent = val; }
function esc(s) { return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
