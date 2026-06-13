// ── Search Page ───────────────────────────────────────────────────────────────

const TYPE_LABELS = {
  medicine:      'دواء',
  device:        'جهاز طبي',
  special_needs: 'احتياجات خاصة',
  parapharmacy:  'باراصيدلانية',
};

const AVAIL_LABELS = { available: 'متوفر', limited: 'محدود', unavailable: 'غير متوفر' };
const AVAIL_BADGE  = { available: 'badge-secondary', limited: 'badge-orange', unavailable: 'badge-red' };

function esc(s) {
  return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function populateWilayas() {
  const sel = document.getElementById('s-wilaya');
  if (!sel || typeof WILAYAS === 'undefined') return;
  WILAYAS.forEach(w => { const o = document.createElement('option'); o.value = w; o.textContent = w; sel.appendChild(o); });
}

async function doSearch() {
  const query  = document.getElementById('s-query')?.value.trim() || '';
  const wilaya = document.getElementById('s-wilaya')?.value || '';
  const type   = document.getElementById('s-type')?.value || '';
  const avail  = document.getElementById('s-availability')?.value || '';

  const results = document.getElementById('results');
  const countEl = document.getElementById('results-count');
  if (results) results.innerHTML = '<div style="text-align:center;padding:3rem"><div class="spinner" style="margin:0 auto"></div></div>';

  try {
    const params = {};
    if (query)  params.q = query;
    if (wilaya) params.wilaya = wilaya;
    if (type)   params.type = type;
    if (avail)  params.availability = avail;

    const data  = await api.get('/medicines/search.php', params);
    const items = Array.isArray(data) ? data : (data.results || data.items || data.data || []);
    const total = data.total || items.length;
    if (countEl) countEl.textContent = `${formatNum(total)} نتيجة`;

    if (!items.length) {
      results.innerHTML = '<div class="empty-state"><div class="empty-icon">🔍</div><p>لا توجد نتائج لهذا البحث</p></div>';
      return;
    }

    results.innerHTML = `<div class="medicine-list">${items.map((m, i) => {
      const avBadge = AVAIL_BADGE[m.availability] || 'badge-muted';
      const avLabel = AVAIL_LABELS[m.availability] || (m.availability || '');
      return `
      <div class="search-med-card animate-up anim-delay-${(i % 5) + 1}">
        <div class="med-main">
          <h3>${esc(m.name || m.product_name || '')}</h3>
          ${m.name_ar ? `<div style="font-size:.82rem;color:var(--muted);margin-top:.15rem">${esc(m.name_ar)}</div>` : ''}
          <div class="med-meta">
            ${m.type       ? `<span>📦 ${TYPE_LABELS[m.type] || esc(m.type)}</span>` : ''}
            ${m.pharmacy_name ? `<span>🏥 ${esc(m.pharmacy_name)}</span>` : ''}
            ${m.wilaya     ? `<span>📍 ${esc(m.wilaya)}</span>` : ''}
            ${m.city       ? `<span>${esc(m.city)}</span>` : ''}
          </div>
        </div>
        <div class="med-right">
          ${avLabel ? `<span class="badge ${avBadge}">${avLabel}</span>` : ''}
        </div>
      </div>`;
    }).join('')}</div>`;
  } catch (err) {
    if (results) results.innerHTML = '<div class="empty-state"><div class="empty-icon">⚠️</div><p>تعذر تحميل النتائج</p></div>';
    console.error('[Search]', err);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  populateWilayas();

  const form = document.getElementById('search-form');
  if (form) form.addEventListener('submit', (e) => { e.preventDefault(); doSearch(); });

  const params = new URLSearchParams(window.location.search);
  const q        = params.get('q');
  const type     = params.get('type');
  const category = params.get('category');
  const wilaya   = params.get('wilaya');

  if (q)        { const el = document.getElementById('s-query');  if (el) el.value = q; }
  if (wilaya)   { const el = document.getElementById('s-wilaya'); if (el) el.value = wilaya; }
  if (type || category) {
    const val = type || category;
    const el  = document.getElementById('s-type');
    if (el) el.value = val;
  }

  doSearch();
});
