// ── Pharmacy Dashboard ────────────────────────────────────────────────────────
let _editItemId = null;

async function loadStats() {
  try {
    const d = await api.get('/dashboard/stats.php', { pharmacyId: PHARMACY_ID });
    document.getElementById('st-total').textContent    = d.totalProducts;
    document.getElementById('st-low').textContent      = d.lowStockCount;
    document.getElementById('st-today').textContent    = d.todayReservations;
    document.getElementById('st-rate').textContent     = d.availabilityRate + '%';
  } catch {}
}

async function loadInventory() {
  const tbody = document.getElementById('inventory-tbody');
  tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:2rem"><div class="spinner"></div></td></tr>';
  try {
    const data = await api.get('/inventory/index.php', { pharmacyId: PHARMACY_ID, limit: 50 });
    if (!data.items.length) {
      tbody.innerHTML = '<tr><td colspan="6" class="empty-state" style="text-align:center;padding:2rem">لا توجد منتجات</td></tr>';
      return;
    }
    const catLbl = { medicine:'دواء', device:'جهاز طبي', parapharmacy:'شبه صيدلاني', other:'أخرى' };
    const stBadge = {
      available: '<span class="badge badge-secondary">متوفر</span>',
      limited:   '<span class="badge badge-orange">محدود</span>',
      unavailable:'<span class="badge badge-red">غير متوفر</span>',
    };
    tbody.innerHTML = data.items.map(item => `
      <tr>
        <td><strong>${item.product_name}</strong></td>
        <td>${catLbl[item.category] || item.category}</td>
        <td>${item.quantity}</td>
        <td>${item.price ? Number(item.price).toLocaleString('ar') + ' DA' : '—'}</td>
        <td>${stBadge[item.status] || item.status}</td>
        <td>
          <div class="td-actions">
            <button class="btn btn-outline btn-icon btn-sm" onclick="openEdit(${JSON.stringify(item).replace(/"/g,'&quot;')})" title="تعديل">✏️</button>
            <button class="btn btn-danger btn-icon btn-sm" onclick="deleteItem(${item.id})" title="حذف">🗑️</button>
          </div>
        </td>
      </tr>`).join('');
  } catch {}
}

function openAdd() {
  _editItemId = null;
  document.getElementById('inv-modal-title').textContent = 'إضافة منتج جديد';
  document.getElementById('inv-form').reset();
  openModal('inv-modal');
}

function openEdit(item) {
  _editItemId = item.id;
  document.getElementById('inv-modal-title').textContent = 'تعديل المنتج';
  document.getElementById('f-product-name').value = item.product_name;
  document.getElementById('f-qty').value           = item.quantity;
  document.getElementById('f-price').value         = item.price || '';
  document.getElementById('f-category').value      = item.category;
  document.getElementById('f-status').value        = item.status;
  openModal('inv-modal');
}

async function submitInventory(e) {
  e.preventDefault();
  const btn = e.target.querySelector('[type=submit]');
  btn.disabled = true; btn.textContent = 'جارٍ الحفظ...';
  const body = {
    productName: document.getElementById('f-product-name').value,
    quantity:    parseInt(document.getElementById('f-qty').value),
    price:       parseFloat(document.getElementById('f-price').value) || null,
    category:    document.getElementById('f-category').value,
    status:      document.getElementById('f-status').value,
    pharmacyId:  PHARMACY_ID,
  };
  try {
    if (_editItemId) {
      await api.put(`/inventory/update.php?id=${_editItemId}`, body);
      showToast('تم تعديل المنتج', 'success');
    } else {
      await api.post('/inventory/create.php', body);
      showToast('تم إضافة المنتج', 'success');
    }
    closeModal('inv-modal');
    loadInventory(); loadStats();
  } catch { showToast('حدث خطأ', 'error'); }
  finally { btn.disabled = false; btn.textContent = 'حفظ'; }
}

async function deleteItem(id) {
  if (!confirm('هل تريد حذف هذا المنتج؟')) return;
  try {
    await api.delete(`/inventory/delete.php?id=${id}`);
    showToast('تم الحذف', 'success');
    loadInventory(); loadStats();
  } catch { showToast('حدث خطأ', 'error'); }
}

// ── Partnerships ──────────────────────────────────────────────────────────────
async function loadPartnerships() {
  const container = document.getElementById('partnerships-content');
  container.innerHTML = '<div class="spinner" style="margin:2rem auto"></div>';
  try {
    const data = await api.get('/partnerships/pharmacy.php', { pharmacyId: PHARMACY_ID });
    // Update notification badge
    if (data.pending.length > 0) {
      document.getElementById('tab-partner-badge').textContent = data.pending.length;
      document.getElementById('tab-partner-badge').style.display = 'flex';
    }
    renderPartnerships(data);
  } catch { container.innerHTML = '<p style="color:var(--muted);text-align:center">تعذر التحميل</p>'; }
}

function renderPartnerships(data) {
  const container = document.getElementById('partnerships-content');
  let html = `
    <div class="partner-summary">
      <div class="partner-summary-card pending"><strong>${data.pending.length}</strong><span>طلب معلق</span></div>
      <div class="partner-summary-card active"><strong>${data.accepted.length}</strong><span>شريك نشط</span></div>
      <div class="partner-summary-card revoked"><strong>${data.revoked.length}</strong><span>تم الإلغاء</span></div>
    </div>`;

  if (data.pending.length) {
    html += `<div class="section-title"><span class="icon">🔔</span> طلبات معلقة <span class="badge badge-orange">${data.pending.length}</span></div>`;
    html += data.pending.map(req => `
      <div class="partner-req-card animate-in">
        <div class="partner-card-row">
          <div class="partner-rep-info">
            <div class="partner-rep-icon">👤</div>
            <div>
              <div class="partner-rep-name">${req.rep_name}</div>
              <div class="partner-rep-meta">
                <span>📍 ${req.rep_region}</span>
                ${req.rep_phone ? `<span>📞 ${req.rep_phone}</span>` : ''}
                ${req.rep_email ? `<span>📧 ${req.rep_email}</span>` : ''}
              </div>
              ${req.message ? `<div class="partner-message">"${req.message}"</div>` : ''}
              <div style="font-size:0.75rem;color:var(--muted);margin-top:0.4rem">🕐 ${formatDate(req.created_at)}</div>
            </div>
          </div>
          <div class="partner-actions">
            <button class="btn btn-secondary btn-sm" onclick="updatePartner(${req.id},'accepted')">✓ قبول</button>
            <button class="btn btn-danger btn-sm" onclick="updatePartner(${req.id},'rejected')">✗ رفض</button>
          </div>
        </div>
      </div>`).join('');
  }

  html += `<div class="section-title" style="margin-top:1.5rem"><span class="icon">🛡️</span> الشركاء النشطون</div>`;
  if (!data.accepted.length) {
    html += '<div class="empty-state"><div class="empty-icon">🤝</div><p>لا يوجد شركاء نشطون حالياً</p></div>';
  } else {
    html += data.accepted.map(req => `
      <div class="partner-active-card animate-in">
        <div class="partner-card-row">
          <div class="partner-rep-info">
            <div class="partner-rep-icon" style="background:var(--secondary-light)">🛡️</div>
            <div>
              <div class="partner-rep-name">${req.rep_name}</div>
              <div class="partner-rep-meta">
                <span>📍 ${req.rep_region}</span>
                ${req.rep_phone ? `<span>📞 ${req.rep_phone}</span>` : ''}
              </div>
              <span class="badge badge-secondary" style="margin-top:0.35rem">✓ شراكة نشطة</span>
            </div>
          </div>
          <button class="btn btn-danger btn-sm" onclick="updatePartner(${req.id},'revoked')">إلغاء الشراكة</button>
        </div>
      </div>`).join('');
  }

  if (data.revoked.length) {
    html += `<div class="section-title" style="margin-top:1.5rem;color:var(--muted)"><span class="icon">🚫</span> شراكات ملغاة</div>`;
    html += data.revoked.map(req => `
      <div style="background:var(--muted-bg);border:1px solid var(--border);border-radius:var(--radius);padding:0.85rem 1rem;margin-bottom:0.5rem;opacity:0.7;display:flex;justify-content:space-between;align-items:center">
        <span style="font-weight:600">${req.rep_name} — ${req.rep_region}</span>
        <span class="badge badge-muted">ملغاة</span>
      </div>`).join('');
  }

  container.innerHTML = html;
}

async function updatePartner(id, status) {
  try {
    await api.patch(`/partnerships/update.php?id=${id}`, { status });
    showToast(status === 'accepted' ? 'تم قبول الشراكة' : status === 'rejected' ? 'تم رفض الطلب' : 'تم إلغاء الشراكة', 'success');
    loadPartnerships();
  } catch { showToast('حدث خطأ', 'error'); }
}

// ── Resupply requests (incoming) ──────────────────────────────────────────────
async function loadResupplyRequests() {
  const container = document.getElementById('resupply-content');
  container.innerHTML = '<div class="spinner" style="margin:2rem auto"></div>';
  try {
    const list = await api.get('/resupply/requests.php', { pharmacyId: PHARMACY_ID });
    const pending = list.filter(r => r.status === 'pending');
    if (pending.length) {
      document.getElementById('tab-resupply-badge').textContent = pending.length;
      document.getElementById('tab-resupply-badge').style.display = 'flex';
    }
    if (!list.length) {
      container.innerHTML = '<div class="empty-state"><div class="empty-icon">🔄</div><p>لا توجد طلبات تموين</p></div>';
      return;
    }
    const stBadge = {
      pending:   '<span class="badge badge-orange">معلق</span>',
      confirmed: '<span class="badge badge-primary">مؤكد</span>',
      sent:      '<span class="badge badge-secondary">تم الإرسال</span>',
      rejected:  '<span class="badge badge-red">مرفوض</span>',
    };
    container.innerHTML = list.map(req => `
      <div class="resupply-card animate-in">
        <div class="resupply-card-row">
          <div>
            <div style="display:flex;gap:0.5rem;align-items:center;margin-bottom:0.35rem">
              ${stBadge[req.status] || ''}
              <strong>${req.product_name}</strong>
            </div>
            <div style="font-size:0.82rem;color:var(--muted);margin-bottom:0.25rem">
              مزود الأدوية: <strong style="color:var(--text)">${req.rep_name}</strong>
              &nbsp;·&nbsp; الكمية: <strong style="color:var(--text)">${req.requested_quantity} وحدة</strong>
            </div>
            ${req.message ? `<div style="background:var(--muted-bg);border-radius:var(--radius-sm);padding:0.4rem 0.65rem;font-size:0.82rem;margin-top:0.35rem">"${req.message}"</div>` : ''}
            <div style="font-size:0.75rem;color:var(--muted);margin-top:0.35rem">🕐 ${formatDate(req.created_at)}${req.rep_phone ? ` &nbsp;·&nbsp; 📞 ${req.rep_phone}` : ''}</div>
          </div>
          ${req.status === 'pending' ? `
            <div style="display:flex;gap:0.4rem;align-items:flex-start;flex-shrink:0;flex-wrap:wrap">
              <button class="btn btn-secondary btn-sm" onclick="updateResupply(${req.id},'confirmed')">✓ تأكيد</button>
              <button class="btn btn-primary btn-sm" onclick="updateResupply(${req.id},'sent')">📦 إرسال</button>
              <button class="btn btn-danger btn-sm" onclick="updateResupply(${req.id},'rejected')">✗ رفض</button>
            </div>` : req.status === 'confirmed' ? `
            <button class="btn btn-primary btn-sm" onclick="updateResupply(${req.id},'sent')">📦 تأكيد الإرسال</button>` : ''}
        </div>
      </div>`).join('');
  } catch { container.innerHTML = '<p style="color:var(--muted);text-align:center">تعذر التحميل</p>'; }
}

async function updateResupply(id, status) {
  try {
    await api.patch(`/resupply/update.php?id=${id}`, { status });
    showToast(status === 'confirmed' ? 'تم التأكيد' : status === 'sent' ? 'تم تأكيد الإرسال' : 'تم الرفض', 'success');
    loadResupplyRequests(); loadStats();
  } catch { showToast('حدث خطأ', 'error'); }
}

// ── Init ──────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  loadStats();
  loadInventory();
  loadPartnerships();
  loadResupplyRequests();
  document.getElementById('inv-form')?.addEventListener('submit', submitInventory);
  document.getElementById('btn-add-item')?.addEventListener('click', openAdd);

  // Wire tabs manually (dashboard has custom tab panels by id)
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
      btn.classList.add('active');
      document.getElementById('panel-' + btn.dataset.tab)?.classList.add('active');
    });
  });
});
