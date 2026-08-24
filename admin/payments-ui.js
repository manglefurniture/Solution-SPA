(()=>{
'use strict';
function safeInit(){
  try{
    if(typeof window.solutionCan!=='function'||!window.solutionCan('payments.view')){
      document.querySelector('[data-view="payments"]')?.remove();
      return;
    }
    const section=document.getElementById('payments');
    const form=document.getElementById('paymentForm');
    const client=document.getElementById('paymentClient');
    const appt=document.getElementById('paymentAppointment');
    const list=document.getElementById('adminPayments');
    const status=document.getElementById('paymentStatus');
    const refresh=document.getElementById('refreshPayments');
    const nav=document.querySelector('[data-view="payments"]');
    if(!section||!form||!client||!appt||!list||!status||!nav)return;

    const esc=window.spaEscape||(v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c])));
    const method=v=>({cash:'Efectivo',card:'Tarjeta',transfer:'Transferencia',other:'Otro'}[v]||v);
    const state=v=>({pending:'Pendiente',paid:'Pagado',refunded:'Reembolsado',cancelled:'Cancelado'}[v]||v);
    let initialized=false;

    async function getData(url){
      const r=await fetch(url,{cache:'no-store'});
      const d=await r.json().catch(()=>({}));
      if(!r.ok)throw new Error(d.error||'No se pudo consultar');
      return Array.isArray(d)?d:(d.data||[]);
    }
    async function sendJson(url,payload){
      const r=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
      const d=await r.json().catch(()=>({}));
      if(!r.ok)throw new Error(d.error||'No se pudo registrar el pago');
      return d;
    }
    async function loadClients(){
      const rows=await getData('../backend/api/clients.php?per_page=200');
      client.innerHTML='<option value="">Selecciona cliente</option>'+rows.map(c=>`<option value="${Number(c.id)}">${esc(c.name)}</option>`).join('');
    }
    async function loadAppointments(){
      appt.innerHTML='<option value="">Sin asociar a una cita</option>';
      if(!client.value)return;
      try{
        const rows=await getData(`../backend/api/appointments.php?client_id=${Number(client.value)}`);
        appt.innerHTML+=rows.slice(0,50).map(a=>`<option value="${Number(a.id)}">${esc(String(a.starts_at).slice(0,16))} · ${esc(a.service_name||'Servicio')}</option>`).join('');
      }catch(e){}
    }
    async function loadPayments(){
      list.innerHTML='<p class="empty-note">Consultando pagos…</p>';
      try{
        const rows=await getData('../backend/api/payments.php');
        list.innerHTML=rows.length?rows.map(p=>`<div class="payment-admin-row"><div><b>${esc(p.client_name||'Cliente')}</b><small>${esc(p.service_name||'Sin cita asociada')}</small></div><b>$${Number(p.amount).toLocaleString('es-MX',{minimumFractionDigits:2,maximumFractionDigits:2})}</b><small>${esc(method(p.method))} · ${esc(state(p.status))}</small><small>${esc(String(p.paid_at||p.created_at||'').slice(0,16))}</small></div>`).join(''):'<p class="empty-note">No hay pagos registrados.</p>';
      }catch(e){
        list.innerHTML=`<p class="empty-note">${esc(e.message||'No pude consultar los pagos.')}</p>`;
      }
    }
    async function initializePayments(){
      if(initialized){await loadPayments();return;}
      initialized=true;
      try{await Promise.all([loadClients(),loadPayments()]);}
      catch(e){initialized=false;list.innerHTML=`<p class="empty-note">${esc(e.message||'No pude cargar Pagos.')}</p>`;}
    }

    client.addEventListener('change',loadAppointments);
    refresh?.addEventListener('click',loadPayments);
    nav.addEventListener('click',()=>{void initializePayments();});
    form.addEventListener('submit',async e=>{
      e.preventDefault();
      if(!window.solutionCan('payments.update')){status.textContent='No tienes permiso para registrar pagos';return;}
      if(!client.value){status.textContent='Selecciona un cliente';return;}
      const amount=Number(document.getElementById('paymentAmount')?.value||0);
      if(!(amount>0)){status.textContent='Escribe un importe válido';return;}
      status.textContent='Registrando…';
      try{
        await sendJson('../backend/api/payments.php',{
          client_id:Number(client.value),
          appointment_id:appt.value?Number(appt.value):null,
          amount,
          method:document.getElementById('paymentMethod')?.value||'other',
          status:'paid',
          reference:document.getElementById('paymentReference')?.value||''
        });
        status.textContent='Pago registrado ✓';
        document.getElementById('paymentAmount').value='';
        document.getElementById('paymentReference').value='';
        await loadPayments();
      }catch(err){status.textContent=err.message||'No se pudo registrar el pago';}
    });
  }catch(e){
    console.error('Payments module disabled:',e);
  }
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',safeInit,{once:true});else safeInit();
})();
