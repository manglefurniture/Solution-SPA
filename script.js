const form = document.querySelector('#booking-form');
const success = document.querySelector('#form-success');
const reset = document.querySelector('#form-reset');
const submit = form?.querySelector('button[type="submit"]');

// Cleanup de compatibilidad: versiones anteriores añadían accesos Admin por JS.
// Dejamos una sola fuente visible por breakpoint y retiramos cualquier nodo legado.
document.querySelectorAll('.admin-mobile-link, .admin-menu-link').forEach((node) => node.remove());

if (!document.querySelector('#admin-access-cleanup-style')) {
  const style = document.createElement('style');
  style.id = 'admin-access-cleanup-style';
  style.textContent = `
    /* Desktop usa el enlace Administración del menú principal. */
    .header-actions .admin-link { display: none; }
    /* En tablet/móvil el menú principal se oculta; mostramos solo Admin. */
    @media (max-width: 1080px) {
      .header-actions .admin-link { display: inline-flex; }
    }
  `;
  document.head.appendChild(style);
}

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
