const form = document.querySelector('#booking-form');
const success = document.querySelector('#form-success');
const reset = document.querySelector('#form-reset');
const submit = form?.querySelector('button[type="submit"]');

function enhanceHeaderNavigation() {
  const nav = document.querySelector('.desktop-nav');
  const header = document.querySelector('.site-header');
  const cta = document.querySelector('.header-cta');

  if (nav && !nav.querySelector('[data-booking-link]')) {
    const booking = document.createElement('a');
    booking.href = '#contacto';
    booking.textContent = 'Reservar';
    booking.dataset.bookingLink = '1';
    nav.appendChild(booking);
  }

  if (nav && !nav.querySelector('[data-admin-link]')) {
    const admin = document.createElement('a');
    admin.href = 'admin/';
    admin.textContent = 'Administración';
    admin.dataset.adminLink = '1';
    admin.setAttribute('aria-label', 'Entrar a la administración de Solution SPA');
    nav.appendChild(admin);
  }

  if (header && cta && !header.querySelector('.header-actions')) {
    const actions = document.createElement('div');
    actions.className = 'header-actions';

    const admin = document.createElement('a');
    admin.className = 'admin-access';
    admin.href = 'admin/';
    admin.innerHTML = '<span aria-hidden="true">⌁</span><b>Admin</b>';
    admin.setAttribute('aria-label', 'Entrar a administración');

    cta.parentNode.insertBefore(actions, cta);
    actions.appendChild(admin);
    actions.appendChild(cta);
  }

  const contactIntro = document.querySelector('.contact-intro');
  if (contactIntro) {
    contactIntro.textContent = 'Cuéntanos qué quieres trabajar. Tu solicitud llegará directamente a nuestro sistema y te contactaremos para confirmar tratamiento, fecha y horario.';
  }
}

enhanceHeaderNavigation();

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
    if (text) text.textContent = 'Ya quedó registrada en Solution SPA. Te contactaremos para confirmar tratamiento, fecha y horario.';

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
