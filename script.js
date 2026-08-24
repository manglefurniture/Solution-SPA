const form = document.querySelector('#booking-form');
const success = document.querySelector('#form-success');
const reset = document.querySelector('#form-reset');
const submit = form?.querySelector('button[type="submit"]');

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
