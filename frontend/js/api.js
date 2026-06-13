// ── Fetch Utility ────────────────────────────────────────────────────────────
async function apiFetch(path, options = {}) {
  const url = `${API_BASE}${path}`;

  const hasBody = options.body !== undefined;
  const defaultHeaders = hasBody ? { 'Content-Type': 'application/json' } : {};

  try {
    const res = await fetch(url, {
      credentials: 'include',
      headers: { ...defaultHeaders, ...options.headers },
      ...options,
    });

    let data;
    try {
      data = await res.json();
    } catch {
      throw new Error(`HTTP ${res.status} — réponse non-JSON`);
    }

    if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`);
    return data;
  } catch (err) {
    console.error('[API]', path, err.message);
    throw err;
  }
}

const api = {
  get: (path, params = {}) => {
    const clean = Object.fromEntries(
      Object.entries(params).filter(([, v]) => v !== '' && v != null && v !== undefined)
    );
    const qs = new URLSearchParams(clean).toString();
    return apiFetch(qs ? `${path}?${qs}` : path);
  },
  post:   (path, body) => apiFetch(path, { method: 'POST',   body: JSON.stringify(body) }),
  put:    (path, body) => apiFetch(path, { method: 'PUT',    body: JSON.stringify(body) }),
  patch:  (path, body) => apiFetch(path, { method: 'PATCH',  body: JSON.stringify(body) }),
  delete: (path)       => apiFetch(path, { method: 'DELETE' }),
};