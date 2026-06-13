// ── Contact page ──────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('contact-form');
  if (!form) return;
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = form.querySelector('[type=submit]');
    btn.disabled = true; btn.textContent = 'جارٍ الإرسال...';
    try {
      await api.post('/contact/create.php', {
        name:    document.getElementById('c-name').value,
        email:   document.getElementById('c-email').value,
        message: document.getElementById('c-message').value,
      });
      document.getElementById('success-msg').classList.add('show');
      form.reset();
    } catch {
      showToast('حدث خطأ أثناء الإرسال', 'error');
    } finally {
      btn.disabled = false; btn.textContent = 'إرسال الرسالة';
    }
  });
});
