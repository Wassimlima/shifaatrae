// ── Home page ─────────────────────────────────────────────────────────────────
async function loadPlatformStats() {
  try {
    const data = await api.get('/dashboard/platform-stats.php');
    document.getElementById('stat-pharmacies').textContent = formatNum(data.totalPharmacies);
    document.getElementById('stat-wilayas').textContent    = formatNum(data.totalWilayas);
    document.getElementById('stat-medicines').textContent  = formatNum(data.totalMedicines);
    document.getElementById('stat-searches').textContent   = '+' + formatNum(data.dailySearches);
  } catch {}
}

async function loadCategories() {
  try {
    const cats = await api.get('/categories/index.php');
    const grid = document.getElementById('categories-grid');
    grid.innerHTML = cats.map((c, i) => `
      <a href="pages/search.html?category=${c.slug}" class="category-card animate-up anim-delay-${i+1}" style="color:${c.color}">
        <div class="cat-icon">${c.icon}</div>
        <div class="cat-name" style="color:var(--text)">${c.name_ar}</div>
        <div class="cat-count">${formatNum(c.product_count)} منتج</div>
      </a>`).join('');
  } catch {
    document.getElementById('categories-grid').innerHTML = '<p class="text-muted">تعذر التحميل</p>';
  }
}

async function loadPopularMedicines() {
  try {
    const meds = await api.get('/medicines/popular.php');
    const grid = document.getElementById('popular-grid');
    const availMap = {
      available: { label: 'متوفر', cls: 'badge-secondary' },
      limited: { label: 'محدود', cls: 'badge-orange' },
      unavailable: { label: 'غير متوفر', cls: 'badge-red' },
    };
    grid.innerHTML = meds.map((m, i) => `
      <div class="medicine-card animate-up anim-delay-${(i%5)+1}">
        <div class="med-header">
          <div>
            <div class="med-name">${m.name}</div>
            ${m.name_ar ? `<div class="med-ingredient">${m.name_ar}</div>` : ''}
          </div>
          ${(() => {
            const key = m.availability || (Number(m.quantity) > 5 ? 'available' : Number(m.quantity) > 0 ? 'limited' : 'unavailable');
            const badge = availMap[key] || availMap.available;
            return `<span class="badge ${badge.cls}">${badge.label}</span>`;
          })()}
        </div>
        <div class="med-info">
          ${m.pharmacy_name ? `<span>🏥 ${m.pharmacy_name}</span>` : ''}
          ${m.wilaya ? `<span>📍 ${m.wilaya}</span>` : ''}
        </div>
        <div class="med-footer">
          <span class="med-price">توفر فقط بدون أسعار</span>
          <a href="pages/search.html?q=${encodeURIComponent(m.name)}" class="btn btn-outline btn-sm">بحث</a>
        </div>
      </div>`).join('');
  } catch {}
}

async function loadDonationsPreview() {
  try {
    const data = await api.get('/donations/index.php', { limit: 3 });
    const grid = document.getElementById('donations-preview');
    const condLabel = { new:'جديد', good:'جيد', fair:'مقبول' };
    grid.innerHTML = data.donations.slice(0,3).map(d => `
      <div class="donation-card">
        <div class="donation-img">🎁</div>
        <div class="donation-info">
          <p>${d.item_name_ar}</p>
          <small>📍 ${d.city}، ${d.wilaya}</small>
          <small>👤 ${d.donor_name}</small>
          <span class="badge badge-primary" style="margin-top:0.35rem;font-size:0.7rem">${condLabel[d.condition]||d.condition}</span>
        </div>
      </div>`).join('');
  } catch {}
}

// Search from hero
function initHeroSearch() {
  const form = document.getElementById('hero-search-form');
  const input = document.getElementById('hero-search-input');
  const type  = document.getElementById('hero-search-type');
  const suggestions = document.getElementById('search-suggestions');

  if (!form) return;

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const q = input.value.trim();
    const t = type.value;
    const params = new URLSearchParams();
    if (q) params.set('q', q);
    if (t) params.set('type', t);
    window.location.href = `pages/search.html?${params}`;
  });

  let timeout;
  input.addEventListener('input', () => {
    clearTimeout(timeout);
    const q = input.value.trim();
    if (q.length < 2) { suggestions.innerHTML = ''; suggestions.hidden = true; return; }
    timeout = setTimeout(async () => {
      try {
        const list = await api.get('/medicines/suggestions.php', { q });
        if (!list.length) { suggestions.hidden = true; return; }
        suggestions.hidden = false;
        suggestions.innerHTML = list.map(s => `<li class="suggestion-item" data-val="${s}">${s}</li>`).join('');
        suggestions.querySelectorAll('li').forEach(li => {
          li.addEventListener('click', () => {
            input.value = li.dataset.val;
            suggestions.hidden = true;
          });
        });
      } catch {}
    }, 300);
  });
  document.addEventListener('click', (e) => {
    if (!e.target.closest('#hero-search-form')) { suggestions.hidden = true; }
  });
}

document.addEventListener('DOMContentLoaded', () => {
  initHeroSearch();
  loadPlatformStats();
  loadCategories();
  loadPopularMedicines();
  loadDonationsPreview();
});
