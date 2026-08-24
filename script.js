const form = document.querySelector('#booking-form');
const success = document.querySelector('#form-success');
const reset = document.querySelector('#form-reset');
const submit = form?.querySelector('button[type="submit"]');

function normalizeAdminAccess() {
  const mobile = window.matchMedia('(max-width: 1080px)').matches;
  const allAdminLinks = Array.from(document.querySelectorAll('a[href="admin/"], a[href="/admin/"]'));
  const headerAdmin = document.querySelector('.header-actions .admin-link');
  const desktopAdmin = document.querySelector('.desktop-nav a[href="admin/"], .desktop-nav a[href="/admin/"]');

  // Borra cualquier acceso legado/injectado que no sea uno de los dos declarados en el HTML.
  allAdminLinks.forEach((link) => {
    if (link !== headerAdmin && link !== desktopAdmin) link.remove();
  });

  if (mobile) {
    // En móvil/tablet solo queda el botón Admin junto a Agendar valoración.
    if (desktopAdmin) desktopAdmin.style.display = 'none';
    if (headerAdmin) headerAdmin.style.display = 'inline-flex';
  } else {
    // En escritorio solo queda Administración dentro del menú principal.
    if (desktopAdmin) desktopAdmin.style.display = '';
    if (headerAdmin) headerAdmin.style.display = 'none';
  }
}

normalizeAdminAccess();
window.addEventListener('resize', normalizeAdminAccess);

form?.addEventListener('submit', async (event) => {
  event.preventDefault();
  if (!form.reportValidity()) return;

  const data = new FormData(form);
  const original = submit?.innerHTML;
  if (submit) {
    submit.disabled = true;
    submit.textContent = 'Enviando…';
  }

  try {
    const response = await fetch('backend/api/public_request.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        name: String(data.get('name') || '').trim(),
        phone: String(data.get('phone') || '').trim(),
        interest: String(data.get('interest') || '').trim(),
        website: String(data.get('website') || '').trim(),
      }),
    });

    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.error || 'No pudimos enviar tu solicitud');

    const title = success?.querySelector('strong');
    const text = success?.querySelector('p');
    if (title) title.textContent = 'Solicitud recibida';
    if (text) text.textContent = 'Tus datos ya quedaron registrados. Solution SPA podrá ver esta solicitud desde su panel de gestión.';

    form.hidden = true;
    success.hidden = false;
  } catch (error) {
    alert(error.message || 'No pudimos enviar tu solicitud. Intenta de nuevo.');
  } finally {
    if (submit) {
      submit.disabled = false;
      submit.innerHTML = original;
    }
  }
});

reset?.addEventListener('click', () => {
  form.reset();
  success.hidden = true;
  form.hidden = false;
  form.querySelector('input')?.focus();
});
