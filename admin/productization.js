(()=>{
const esc=window.spaEscape||(v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c])));
let managersCache=null;
async function managers(){if(managersCache)return managersCache;try{managersCache=await apiData('../backend/api/managers.php')}catch(e){managersCache=[]}return managersCache}
function managerSelect(rows,current=''){return `<label>Gestor asignado<select name="manager_user_id"><option value="">Sin asignar</option>${rows.map(m=>`<option value="${Number(m.id)}" ${Number(current)===Number(m.id)?'selected':''}>${esc(m.name)}</option>`).join('')}</select></label>`}

if(typeof openContextModal==='function'){
 const base=openContextModal;openContextModal=async function(){await base();if(currentAction==='appointment'){const rows=await managers();modalFields.insertAdjacentHTML('beforeend',managerSelect(rows,''));}}
}
if(typeof openAppointmentCard==='function'){
 const base=openAppointmentCard;openAppointmentCard=async function(id){await base(id);if(currentAction!=='appointment_edit')return;const rows=await managers();let a=[];try{a=await apiData(`../backend/api/appointments.php?id=${Number(id)}`)}catch(e){}const current=a[0]?.manager_user_id||'';const actions=modalFields.querySelector('.entity-actions');(actions||modalFields).insertAdjacentHTML(actions?'beforebegin':'beforeend',managerSelect(rows,current));
   const pay=document.createElement('div');pay.className='client-history';pay.innerHTML='<small>PAGO DE ESTA CITA</small><div class="payment-box"><input type="number" min="0" step="0.01" placeholder="Importe" data-pay-amount><select data-pay-method><option value="cash">Efectivo</option><option value="card">Tarjeta</option><option value="transfer">Transferencia</option><option value="other">Otro</option></select><button type="button" data-register-payment>Registrar pago</button><span data-pay-status></span></div>';modalFields.appendChild(pay);pay.querySelector('[data-register-payment]').onclick=async()=>{const amount=Number(pay.querySelector('[data-pay-amount]').value);if(!(amount>=0)){pay.querySelector('[data-pay-status]').textContent='Importe inválido';return}try{await postJson('../backend/api/payments.php',{client_id:Number(a[0]?.client_id),appointment_id:Number(id),amount,method:pay.querySelector('[data-pay-method]').value,status:'paid'});pay.querySelector('[data-pay-status]').textContent='Pago registrado ✓'}catch(e){pay.querySelector('[data-pay-status]').textContent=e.message}};
 }
}
if(typeof openServiceCard==='function'){
 const base=openServiceCard;openServiceCard=async function(service,id){await base(service,id);if(currentAction!=='service_edit')return;const value=service?.image_url||'';const toggle=modalFields.querySelector('.toggle-line');(toggle||modalFields).insertAdjacentHTML(toggle?'beforebegin':'beforeend',`<label>Foto del servicio <small>(URL)</small><input name="image_url" type="url" maxlength="500" value="${esc(value)}" placeholder="https://..."></label>`)}
}
const basePatch=window.patchJson;window.patchJson=async function(url,payload){if(url.includes('appointments.php')&&currentAction==='appointment_edit'){const f=new FormData(form);payload={...payload,manager_user_id:f.get('manager_user_id')||null}}if(url.includes('services.php')&&currentAction==='service_edit'){const f=new FormData(form);payload={...payload,image_url:String(f.get('image_url')||'')}}return basePatch(url,payload)};
const basePost=window.postJson;window.postJson=async function(url,payload){if(url.includes('appointments.php')&&currentAction==='appointment'){const f=new FormData(form);payload={...payload,manager_user_id:f.get('manager_user_id')||null}}return basePost(url,payload)};
})();
