// ── API Configuration ──────────────────────────────────────────────────────
const API_BASE = '/shifaa_dizad/backend/api';

// Resolved after login from server (see syncAuthContext)
let PHARMACY_ID = null;
let REP_ID = null;

function syncAuthContext() {
  try {
    const user = JSON.parse(localStorage.getItem('shifaa_user') || 'null');
    if (!user) return;
    if (user.pharmacy_id) PHARMACY_ID = user.pharmacy_id;
    if (user.rep_id) REP_ID = user.rep_id;
  } catch (e) {
    /* ignore */
  }
}

async function syncAuthContextFromServer() {
  try {
    const res = await fetch(`${API_BASE}/auth/check.php`, { credentials: 'include' });
    const data = await res.json();
    if (!data.logged_in) return;
    const stored = JSON.parse(localStorage.getItem('shifaa_user') || '{}');
    const merged = { ...stored, pharmacy_id: data.pharmacy_id, rep_id: data.rep_id, name: data.name, role: data.role };
    localStorage.setItem('shifaa_user', JSON.stringify(merged));
    if (data.pharmacy_id) PHARMACY_ID = data.pharmacy_id;
    if (data.rep_id) REP_ID = data.rep_id;
  } catch (e) { /* offline */ }
}

syncAuthContext();
document.addEventListener('DOMContentLoaded', () => syncAuthContextFromServer());

// ── 69 Wilayas of Algeria ───────────────────────────────────────────────────
const WILAYAS = [
  'أدرار','الشلف','الأغواط','أم البواقي','باتنة','بجاية','بسكرة','بشار',
  'البليدة','البويرة','تمنراست','تبسة','تلمسان','تيارت','تيزي وزو',
  'الجزائر','الجلفة','جيجل','سطيف','سعيدة','سكيكدة','سيدي بلعباس',
  'عنابة','قالمة','قسنطينة','المدية','مستغانم','المسيلة','معسكر',
  'ورقلة','وهران','البيض','إليزي','برج بوعريريج','بومرداس','الطارف',
  'تندوف','تيسمسيلت','الوادي','خنشلة','سوق أهراس','تيبازة','ميلة',
  'عين الدفلى','النعامة','عين تموشنت','غرداية','غليزان','تيميمون',
  'برج باجي مختار','أولاد جلال','بني عباس','عين صالح','عين قزام',
  'تقرت','جانت','المغير','المنيعة','الجزائر - حسين داي',
  'الجزائر - باب الوادي','الجزائر - بابا علي','الجزائر - دار البيضاء',
  'الجزائر - القبة','الجزائر - بئر توتة','الجزائر - بن عكنون',
  'الجزائر - الحراش','الجزائر - حيدرة'
];