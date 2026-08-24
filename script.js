const form = document.querySelector('#booking-form');
const success = document.querySelector('#form-success');
const reset = document.querySelector('#form-reset');
const submit = form?.querySelector('button[type="submit"]');

function addSafeAdminAccess() {
  const nav = document.querySelector('.desktop-nav');
  const header = document.querySelector('.site-header');
  const cta = document.querySelector('.header-cta');

  if (nav && !nav.querySelector('.admin-menu-link')) {
    const adminLink = document.createElement('a');
    adminLink.className = 'admin-menu-link';
    adminLink.href = 'admin/';
    adminLink.textContent = 'Administración';
    adminLink.setAttribute('aria-label', 'Entrar a la administración de Solution SPA');
    nav.appendChild(adminLink);
  }

  if (header && cta && !header.querySelector('.admin-mobile-link')) {
    const adminMobile = document.createElement('a');
    adminMobile.className = 'admin-mobile-link';
    adminMobile.href = 'admin/';
    adminMobile.textContent = 'Admin';
    adminMobile.setAttribute('aria-label', 'Entrar a la administración de Solution SPA');
    cta.insertAdjacentElement('beforebegin', adminMobile);
  }

  if (!document.querySelector('#safe-admin-access-style')) {
    const style = document.createElement('style');
    style.id = 'safe-admin-access-style';
    style.textContent = `
      .admin-mobile-link{display:none;justify-self:end;margin-right:18px;border:1px solid rgba(43,35,35,.18);padding:8px 11px;font-size:9px;letter-spacing:.08em;text-transform:uppercase}
      .desktop-nav .admin-menu-link{opacity:.72}
      @media(max-width:1080px){
        .admin-mobile-link{display:inline-flex;align-items:center}
      }
      @media(max-width:760px){
        .site-header{grid-template-columns:1fr auto auto;gap:10px}
        .admin-mobile-link{margin-right:0;padding:7px 9px;font-size:8px}
        .header-cta{justify-self:end}
      }
      @media(max-width:430px){
        .brand-copy small{display:none}
        .admin-mobile-link{padding:7px 8px}
      }
    `;
    document.head.appendChild(style);
  }
}

addSafeAdminAccess();

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
