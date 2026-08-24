(()=>{
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
window.spaEscape=esc;
try{escapeHtml=esc}catch(e){}
let managersCache=null;
async function managers(){if(managersCache)return managersCache;try{managersCache=await apiData('../backend/api/managers.php')}catch(e){managersCache=[]}return managersCache}
function managerSelect(rows,current=''){return `<label>Gestor asignado<select name="manager_user_id"><option value="">Sin asignar</option>${rows.map(m=>`<option value="${Number(m.id)}" ${Number(current)===Number(m.id)?'selected':''}>${esc(m.name)}</option>`).join('')}</select></label>`}
async function injectNewManager(){if(currentAction!=='appointment'||modalFields.querySelector('[name="manager_user_id"]'))return;const rows=await managers();modalFields.insertAdjacentHTML('beforeend',managerSelect(rows,''));}
document.getElementById('contextAdd')?.addEventListener('click',()=>setTimeout(()=>injectNewManager(),0));

const methodLabel=v=>({cash:'Efectivo',card:'Tarjeta',transfer:'Transferencia',other:'Otro'}[v]||v||'Otro');
const money=v=>'$'+Number(v||0).toLocaleString('es-MX',{minimumFractionDigits:2,maximumFractionDigits:2});
async function paymentForAppointment(clientId,appointmentId){try{const rows=await apiData(`../backend/api/payments.php?client_id=${Number(clientId)}`);return rows.find(p=>Number(p.appointment_id)===Number(appointmentId)&&p.status==='paid')||null}catch(e){return null}}
async function servicePrice(serviceId){try{const rows=await apiData('../backend/api/services.php');const s=rows.find(x=>Number(x.id)===Number(serviceId));return s&&s.price!==null&&s.price!==undefined&&Number(s.price)>0?Number(s.price):''}catch(e){return ''}}

function paidMarkup(amount,method,paidAt=''){return `<div class="appointment-payment-head"><div><small>COBRO DE LA CITA</small><h3>Pago registrado</h3></div><span class="payment-check">✓</span></div><div class="payment-receipt"><strong>${money(amount)}</strong><span>${esc(methodLabel(method))}${paidAt?` · ${esc(String(paidAt).slice(0,16))}`:''}</span></div>`}

async function appendPaymentCard(a,id){
  if(!a||a.status!=='completed')return;
  const existing=await paymentForAppointment(a.client_id,id);
  const card=document.createElement('section');card.className='appointment-payment-card';
  if(existing){card.innerHTML=paidMarkup(existing.amount,existing.method,existing.paid_at);modalFields.appendChild(card);return;}
  const suggested=await servicePrice(a.service_id);
  card.innerHTML=`<div class="appointment-payment-head"><div><small>COBRO DE LA CITA</small><h3>Registrar pago</h3></div><span class="payment-ready">Cita realizada</span></div><p class="payment-help">La cita ya fue realizada. Registra ahora el cobro correspondiente.</p><div class="payment-box"><label>Importe<input type="number" min="0.01" step="0.01" value="${suggested!==''?suggested:''}" placeholder="0.00" data-pay-amount></label><label>Método<select data-pay-method><option value="cash">Efectivo</option><option value="card">Tarjeta</option><option value="transfer">Transferencia</option><option value="other">Otro</option></select></label><button type="button" data-register-payment>Registrar pago</button><span class="payment-inline-status" data-pay-status></span></div>`;
  modalFields.appendChild(card);
  const button=card.querySelector('[data-register-payment]');
  button.onclick=async()=>{
    const amount=Number(card.querySelector('[data-pay-amount]').value),method=card.querySelector('[data-pay-method]').value,status=card.querySelector('[data-pay-status]');
    if(!(amount>0)){status.textContent='Escribe un importe válido';status.classList.add('error');return}
    button.disabled=true;button.textContent='Registrando…';status.textContent='';status.classList.remove('error');
    try{await postJson('../backend/api/payments.php',{client_id:Number(a.client_id),appointment_id:Number(id),amount,method,status:'paid'});card.innerHTML=paidMarkup(amount,method);if(window.uxToast)window.uxToast('Pago registrado ✓')}
    catch(e){button.disabled=false;button.textContent='Registrar pago';status.textContent=e.message||'No se pudo registrar el pago';status.classList.add('error')}
  };
}

if(typeof openAppointmentCard==='function'){
  const base=openAppointmentCard;
  openAppointmentCard=async function(id){
    await base(id);if(currentAction!=='appointment_edit')return;
    const rows=await managers();let a=[];try{a=await apiData(`../backend/api/appointments.php?id=${Number(id)}`)}catch(e){}
    const appointment=a[0];if(!appointment)return;
    const current=appointment.manager_user_id||'';
    if(!modalFields.querySelector('[name="manager_user_id"]')){const actions=modalFields.querySelector('.entity-actions');(actions||modalFields).insertAdjacentHTML(actions?'beforebegin':'beforeend',managerSelect(rows,current));}
    const completedButton=modalFields.querySelector('[data-quick-status="completed"]');
    if(completedButton&&appointment.status!=='completed'){
      const clean=completedButton.cloneNode(true);completedButton.replaceWith(clean);
      clean.addEventListener('click',async()=>{
        clean.disabled=true;clean.textContent='Marcando…';
        try{await patchJson('../backend/api/appointments.php',{id:Number(id),status:'completed'});await refreshSchedule();if(window.uxToast)window.uxToast('Cita marcada como realizada');await openAppointmentCard(id)}
        catch(e){modalStatus.textContent=e.message||'No se pudo marcar la cita';modalStatus.className='pilot error';clean.disabled=false;clean.textContent='✓ Marcar realizada'}
      });
    }else if(completedButton&&appointment.status==='completed')completedButton.remove();
    await appendPaymentCard(appointment,id);
  }
}

if(typeof openServiceCard==='function'){const base=openServiceCard;openServiceCard=async function(service,id){await base(service,id);if(currentAction!=='service_edit')return;const value=service?.image_url||'';const toggle=modalFields.querySelector('.toggle-line');(toggle||modalFields).insertAdjacentHTML(toggle?'beforebegin':'beforeend',`<label>Foto del servicio <small>(URL)</small><input name="image_url" type="url" maxlength="500" value="${esc(value)}" placeholder="https://..."></label>`)}}
const basePatch=window.patchJson;window.patchJson=async function(url,payload){if(url.includes('appointments.php')&&currentAction==='appointment_edit'){const f=new FormData(form);payload={...payload,manager_user_id:f.get('manager_user_id')||null}}if(url.includes('services.php')&&currentAction==='service_edit'){const f=new FormData(form);payload={...payload,image_url:String(f.get('image_url')||'')}}return basePatch(url,payload)};
const basePost=window.postJson;window.postJson=async function(url,payload){if(url.includes('appointments.php')&&currentAction==='appointment'){const f=new FormData(form);payload={...payload,manager_user_id:f.get('manager_user_id')||null}}return basePost(url,payload)};
})();
