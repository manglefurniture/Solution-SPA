async function patchJson(url,payload){
  const r=await fetch(url,{method:'PATCH',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
  const data=await r.json().catch(()=>({}));
  if(!r.ok)throw new Error(data.error||'No se pudo actualizar');
  return data;
}

const baseAppointmentHtml=appointmentHtml;
appointmentHtml=function(a,includeDuration=true){
  const inner=baseAppointmentHtml(a,includeDuration);
  return `<button type="button" class="appointment-open" data-appointment-id="${Number(a.id)}" aria-label="Abrir cita de ${escapeHtml(a.client_name||'cliente')}">${inner}</button>`;
};

async function openAppointmentCard(id){
  try{
    const [rows,clientRows,serviceRows]=await Promise.all([
      apiData(`../backend/api/appointments.php?id=${Number(id)}`),
      apiData('../backend/api/clients.php'),
      apiData('../backend/api/services.php')
    ]);
    const a=rows[0];
    if(!a)throw new Error('No encontré esa cita');
    currentAction='appointment_edit';
    modalEyebrow.textContent='GESTIÓN DE CITA';
    modalTitle.textContent=a.client_name||'Cita';
    modalStatus.textContent='';modalStatus.className='pilot';
    modalSave.hidden=false;modalSave.disabled=false;modalSave.textContent='Guardar cambios';
    const date=String(a.starts_at||'').slice(0,10),time=String(a.starts_at||'').slice(11,16);
    const clientOptions=clientRows.map(c=>`<option value="${Number(c.id)}" ${Number(c.id)===Number(a.client_id)?'selected':''}>${escapeHtml(c.name)}</option>`).join('');
    const serviceOptions=serviceRows.filter(s=>Number(s.active)!==0||Number(s.id)===Number(a.service_id)).map(s=>`<option value="${Number(s.id)}" ${Number(s.id)===Number(a.service_id)?'selected':''}>${escapeHtml(s.name)}</option>`).join('');
    modalFields.innerHTML=`
      <input type="hidden" name="appointment_id" value="${Number(a.id)}">
      <label>Cliente<select name="client_id" required>${clientOptions}</select></label>
      <label>Servicio<select name="service_id" required>${serviceOptions}</select></label>
      <div class="row"><label>Fecha<input name="date" type="date" value="${date}" required></label><label>Hora<input name="time" type="time" value="${time}" required></label></div>
      <label>Estado<select name="status"><option value="pending" ${a.status==='pending'?'selected':''}>Pendiente</option><option value="confirmed" ${a.status==='confirmed'?'selected':''}>Confirmada</option><option value="completed" ${a.status==='completed'?'selected':''}>Realizada</option><option value="cancelled" ${a.status==='cancelled'?'selected':''}>Cancelada</option></select></label>
      <label>Notas <small>(opcional)</small><textarea name="notes" placeholder="Observaciones de la cita">${escapeHtml(a.notes||'')}</textarea></label>
      <div class="appointment-quick-actions">
        <button type="button" data-quick-status="completed">✓ Marcar realizada</button>
        <button type="button" data-quick-status="cancelled" class="danger-soft">Cancelar cita</button>
      </div>`;
    modal.classList.add('open');
    modalFields.querySelectorAll('[data-quick-status]').forEach(btn=>btn.addEventListener('click',async()=>{
      const status=btn.dataset.quickStatus;
      if(status==='cancelled'&&!confirm('¿Cancelar esta cita?'))return;
      btn.disabled=true;
      try{await patchJson('../backend/api/appointments.php',{id:Number(a.id),status});modal.classList.remove('open');await Promise.all([appointments(),loadCalendar()]);}
      catch(e){modalStatus.textContent=e.message;modalStatus.className='pilot error';btn.disabled=false;}
    }));
  }catch(e){alert(e.message||'No pude abrir la cita');}
}

document.addEventListener('click',e=>{
  const btn=e.target.closest('[data-appointment-id]');
  if(btn){e.preventDefault();openAppointmentCard(btn.dataset.appointmentId);}
});

form.addEventListener('submit',async e=>{
  if(currentAction!=='appointment_edit')return;
  e.preventDefault();e.stopImmediatePropagation();
  const fd=new FormData(form);modalSave.disabled=true;modalSave.textContent='Guardando…';modalStatus.textContent='';
  try{
    const newDate=String(fd.get('date'));
    await patchJson('../backend/api/appointments.php',{
      id:Number(fd.get('appointment_id')),client_id:Number(fd.get('client_id')),service_id:Number(fd.get('service_id')),
      starts_at:`${newDate} ${fd.get('time')}:00`,status:String(fd.get('status')),notes:String(fd.get('notes')||'')
    });
    selectedDate=newDate;const d=dateFromKey(newDate);calendarCursor=new Date(d.getFullYear(),d.getMonth(),1);
    modalStatus.textContent='Cita actualizada ✓';modalStatus.className='pilot success';modalSave.textContent='Guardado';
    await Promise.all([appointments(),loadCalendar()]);setTimeout(()=>modal.classList.remove('open'),650);
  }catch(err){modalStatus.textContent=err.message||'No se pudo actualizar';modalStatus.className='pilot error';modalSave.disabled=false;modalSave.textContent='Guardar cambios';}
},true);

const baseOpenClientCard=openClientCard;
openClientCard=async function(client){
  baseOpenClientCard(client);
  const history=document.createElement('div');
  history.className='client-history';
  history.innerHTML='<small>HISTORIAL DE CITAS</small><p class="loading">Consultando historial…</p>';
  modalFields.appendChild(history);
  try{
    const rows=await apiData(`../backend/api/appointments.php?client_id=${Number(client.id)}`);
    history.innerHTML=`<small>HISTORIAL DE CITAS</small>${rows.length?rows.slice(0,12).map(a=>`<button type="button" class="history-row" data-appointment-id="${Number(a.id)}"><b>${escapeHtml(String(a.starts_at).slice(0,10))}</b><span>${escapeHtml(a.service_name||'Servicio')} · ${escapeHtml(a.status||'')}</span></button>`).join(''):'<p class="empty-note">Este cliente aún no tiene citas.</p>'}`;
  }catch(e){history.innerHTML='<small>HISTORIAL DE CITAS</small><p class="empty-note">No pude consultar el historial ahora mismo.</p>';}
};

function ensureClientSearch(){
  const panel=document.getElementById('clients')?.querySelector('.panel');
  if(!panel||panel.querySelector('.client-search'))return;
  const box=document.createElement('div');box.className='client-search';
  box.innerHTML='<input type="search" placeholder="Buscar por nombre o teléfono…" aria-label="Buscar clientes">';
  panel.prepend(box);
  box.querySelector('input').addEventListener('input',e=>{
    const q=e.target.value.trim().toLowerCase();
    document.querySelectorAll('#clientsList .client-open').forEach(row=>row.hidden=q&&!row.textContent.toLowerCase().includes(q));
  });
}

ensureClientSearch();
setTimeout(()=>{appointments();loadCalendar();},80);
