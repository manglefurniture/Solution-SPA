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
    const submit=form?.querySelector('button[type="submit"]');
    if(!section||!form||!client||!appt||!list||!status||!nav||!submit)return;

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
    function setFormState(){
      const enabled=Boolean(client.value&&appt.value);
      submit.disabled=!enabled;
      submit.title=enabled?'Registrar pago':'Primero selecciona una cita realizada';
    }
    async function loadClients(){
      const rows=await getData('../backend/api/clients.php?per_page=200');
      client.innerHTML='<option value="">Selecciona cliente</option>'+rows.map(c=>`<option value="${Number(c.id)}">${esc(c.name)}</option>`).join('');
      appt.innerHTML='<option value="">Selecciona primero un cliente</option>';
      setFormState();
    }
    async function loadAppointments(){
      appt.innerHTML='<option value="">Consultando citas realizadas…</option>';
      if(!client.value){appt.innerHTML='<option value="">Selecciona primero un cliente</option>';setFormState();return;}
      try{
        const rows=await getData(`../backend/api/appointments.php?client_id=${Number(client.value)}`);
        const completed=rows.filter(a=>a.status==='completed');
        appt.innerHTML=completed.length?'<option value="">Selecciona una cita realizada</option>'+completed.slice(0,50).map(a=>`<option value="${Number(a.id)}">${esc(String(a.starts_at).slice(0,16))} · ${esc(a.service_name||'Servicio')}</option>`).join(''):'<option value="">Este cliente no tiene citas realizadas</option>';
        if(!completed.length)status.textContent='Para registrar un pago, primero marca una cita como realizada.';
        else status.textContent='';
      }catch(e){appt.innerHTML='<option value="">No pude cargar las citas</option>';status.textContent=e.message||'No pude cargar las citas';}
      setFormState();
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
    appt.addEventListener('change',setFormState);
    refresh?.addEventListener('click',loadPayments);
    nav.addEventListener('click',()=>{void initializePayments();});
    setFormState();
    form.addEventListener('submit',async e=>{
      e.preventDefault();
      if(!window.solutionCan('payments.update')){status.textContent='No tienes permiso para registrar pagos';return;}
      if(!client.value){status.textContent='Selecciona un cliente';return;}
      if(!appt.value){status.textContent='Selecciona una cita realizada';return;}
      const amount=Number(document.getElementById('paymentAmount')?.value||0);
      if(!(amount>0)){status.textContent='Escribe un importe válido';return;}
      status.textContent='Registrando…';
      try{
        await sendJson('../backend/api/payments.php',{
          client_id:Number(client.value),
          appointment_id:Number(appt.value),
          amount,
          method:document.getElementById('paymentMethod')?.value||'other',
          status:'paid',
          reference:document.getElementById('paymentReference')?.value||''
        });
        status.textContent='Pago registrado ✓';
        document.getElementById('paymentAmount').value='';
        document.getElementById('paymentReference').value='';
        appt.value='';
        setFormState();
        await loadPayments();
      }catch(err){status.textContent=err.message||'No se pudo registrar el pago';}
    });
  }catch(e){
    console.error('Payments module disabled:',e);
  }
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',safeInit,{once:true});else safeInit();
})();
