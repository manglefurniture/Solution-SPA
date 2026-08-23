async function patchJson(url,payload){const r=await fetch(url,{method:'PATCH',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});const data=await r.json().catch(()=>({}));if(!r.ok)throw new Error(data.error||'No se pudo actualizar');return data}
async function deleteJson(url,payload){const r=await fetch(url,{method:'DELETE',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});const data=await r.json().catch(()=>({}));if(!r.ok)throw new Error(data.error||'No se pudo completar la operación');return data}
function notify(msg,type='ok'){if(window.uxToast)window.uxToast(msg,type);else alert(msg)}
async function refreshSchedule(){await Promise.all([appointments(),loadCalendar()])}

const baseAppointmentHtml=appointmentHtml;
appointmentHtml=function(a,includeDuration=true){const inner=baseAppointmentHtml(a,includeDuration);return `<button type="button" class="appointment-open" data-appointment-id="${Number(a.id)}" aria-label="Abrir cita de ${escapeHtml(a.client_name||'cliente')}">${inner}</button>`};

async function openAppointmentCard(id){
  try{
    const [rows,clientRows,serviceRows]=await Promise.all([
      apiData(`../backend/api/appointments.php?id=${Number(id)}`),
      apiData('../backend/api/clients.php?include_archived=1&per_page=200'),
      apiData('../backend/api/services.php')
    ]);
    const a=rows[0];if(!a)throw new Error('No encontré esa cita');
    currentAction='appointment_edit';
    modalEyebrow.textContent='GESTIÓN DE CITA';
    modalTitle.textContent=a.client_name||'Cita';
    modalStatus.textContent='';modalStatus.className='pilot';
    modalSave.hidden=false;modalSave.disabled=false;modalSave.textContent='Guardar cambios';
    const date=String(a.starts_at||'').slice(0,10),time=String(a.starts_at||'').slice(11,16);
    const clientOptions=clientRows.filter(c=>Number(c.active)!==0||Number(c.id)===Number(a.client_id)).map(c=>`<option value="${Number(c.id)}" ${Number(c.id)===Number(a.client_id)?'selected':''}>${escapeHtml(c.name)}${Number(c.active)===0?' (archivado)':''}</option>`).join('');
    const serviceOptions=serviceRows.filter(s=>Number(s.active)!==0||Number(s.id)===Number(a.service_id)).map(s=>`<option value="${Number(s.id)}" ${Number(s.id)===Number(a.service_id)?'selected':''}>${escapeHtml(s.name)}${Number(s.active)===0?' (inactivo)':''}</option>`).join('');
    modalFields.innerHTML=`<input type="hidden" name="appointment_id" value="${Number(a.id)}"><label>Cliente<select name="client_id" required>${clientOptions}</select></label><label>Servicio<select name="service_id" required>${serviceOptions}</select></label><div class="row"><label>Fecha<input name="date" type="date" value="${date}" required></label><label>Hora<input name="time" type="time" value="${time}" required></label></div><label>Estado<select name="status"><option value="pending" ${a.status==='pending'?'selected':''}>Pendiente</option><option value="confirmed" ${a.status==='confirmed'?'selected':''}>Confirmada</option><option value="completed" ${a.status==='completed'?'selected':''}>Realizada</option><option value="cancelled" ${a.status==='cancelled'?'selected':''}>Cancelada</option></select></label><label>Notas <small>(opcional)</small><textarea name="notes" placeholder="Observaciones de la cita">${escapeHtml(a.notes||'')}</textarea></label><div class="entity-actions"><button type="button" data-quick-status="completed">✓ Marcar realizada</button><button type="button" data-quick-status="cancelled">Cancelar cita</button><button type="button" class="danger" data-delete-appointment>Eliminar cita</button></div>`;
    modal.classList.add('open');

    modalFields.querySelectorAll('[data-quick-status]').forEach(btn=>btn.addEventListener('click',async()=>{
      const status=btn.dataset.quickStatus;
      if(status==='cancelled'&&!confirm('¿Cancelar esta cita?'))return;
      btn.disabled=true;
      try{
        await patchJson('../backend/api/appointments.php',{id:Number(a.id),status});
        modal.classList.remove('open');
        notify(status==='completed'?'Cita marcada como realizada':'Cita cancelada');
        await refreshSchedule();
      }catch(e){modalStatus.textContent=e.message;modalStatus.className='pilot error';btn.disabled=false}
    }));

    modalFields.querySelector('[data-delete-appointment]').addEventListener('click',async()=>{
      if(!confirm('¿Eliminar esta cita definitivamente? Esta acción no se puede deshacer.'))return;
      if(!confirm('Última confirmación: ¿borrar la cita y su registro asociado?'))return;
      try{
        await deleteJson('../backend/api/appointments.php',{id:Number(a.id)});
        modal.classList.remove('open');
        notify('Cita eliminada');
        await refreshSchedule();
      }catch(e){modalStatus.textContent=e.message;modalStatus.className='pilot error'}
    });
  }catch(e){notify(e.message||'No pude abrir la cita','error')}
}

document.addEventListener('click',e=>{
  const btn=e.target.closest('[data-appointment-id]');
  if(btn){e.preventDefault();openAppointmentCard(btn.dataset.appointmentId);return}
  const service=e.target.closest('[data-service-id]');
  if(service){e.preventDefault();openServiceCard(service._serviceData||null,Number(service.dataset.serviceId))}
});

function clientForm(client){return `<input type="hidden" name="client_id" value="${Number(client.id)}"><label>Nombre<input name="name" type="text" maxlength="120" value="${escapeHtml(client.name||'')}" required></label><label>WhatsApp<input name="phone" type="tel" maxlength="30" value="${escapeHtml(client.phone||'')}" required></label><label>Correo <small>(opcional)</small><input name="email" type="email" maxlength="160" value="${escapeHtml(client.email||'')}"></label><label>Fecha de nacimiento <small>(opcional)</small><input name="birth_date" type="date" value="${escapeHtml(client.birth_date||'')}"></label><label>Notas <small>(opcional)</small><textarea name="notes">${escapeHtml(client.notes||'')}</textarea></label>`}

async function archiveClient(client){
  if(!confirm(`¿Archivar a ${client.name}? Sus citas futuras pendientes o confirmadas se cancelarán; el historial se conservará.`))return;
  await deleteJson('../backend/api/clients.php',{id:Number(client.id)});
  modal.classList.remove('open');
  notify('Cliente archivado');
  await Promise.all([clients(),appointments(),loadCalendar()]);
}

async function openClientEdit(client){
  currentAction='client_edit';
  modalEyebrow.textContent='EDITAR CLIENTE';modalTitle.textContent=client.name||'Cliente';
  modalStatus.textContent='';modalStatus.className='pilot';
  modalSave.hidden=false;modalSave.disabled=false;modalSave.textContent='Guardar cambios';
  modalFields.innerHTML=clientForm(client)+`<div class="entity-actions"><button type="button" class="danger" data-archive-client>Archivar cliente</button></div>`;
  modal.classList.add('open');
  modalFields.querySelector('[data-archive-client]').addEventListener('click',async()=>{
    try{await archiveClient(client)}catch(e){modalStatus.textContent=e.message;modalStatus.className='pilot error'}
  });
}

const baseOpenClientCard=openClientCard;
openClientCard=async function(client){
  baseOpenClientCard(client);
  const actions=document.createElement('div');
  actions.className='entity-actions';
  actions.innerHTML='<button type="button" data-edit-client>Editar ficha</button><button type="button" class="danger" data-archive-client>Archivar cliente</button>';
  modalFields.prepend(actions);
  actions.querySelector('[data-edit-client]').addEventListener('click',()=>openClientEdit(client));
  actions.querySelector('[data-archive-client]').addEventListener('click',async()=>{
    try{await archiveClient(client)}catch(e){modalStatus.textContent=e.message;modalStatus.className='pilot error'}
  });

  const history=document.createElement('div');
  history.className='client-history';
  history.innerHTML='<small>HISTORIAL DE CITAS</small><p class="loading">Consultando historial…</p>';
  modalFields.appendChild(history);
  try{
    const rows=await apiData(`../backend/api/appointments.php?client_id=${Number(client.id)}`);
    history.innerHTML=`<small>HISTORIAL DE CITAS</small>${rows.length?rows.slice(0,12).map(a=>`<button type="button" class="history-row" data-appointment-id="${Number(a.id)}"><b>${escapeHtml(String(a.starts_at).slice(0,10))}</b><span>${escapeHtml(a.service_name||'Servicio')} · ${escapeHtml(a.status||'')}</span></button>`).join(''):'<p class="empty-note">Este cliente aún no tiene citas.</p>'}`;
  }catch(e){history.innerHTML='<small>HISTORIAL DE CITAS</small><p class="empty-note">No pude consultar el historial ahora mismo.</p>'}
};

async function archiveService(service){
  if(!confirm(`¿Archivar “${service.name}”? Dejará de estar disponible para nuevas citas; su historial y las citas existentes se conservarán.`))return;
  await deleteJson('../backend/api/services.php',{id:Number(service.id)});
  modal.classList.remove('open');
  notify('Servicio archivado');
  await Promise.all([services(),appointments(),loadCalendar()]);
}

async function openServiceCard(service,id){
  try{
    if(!service){const rows=await apiData('../backend/api/services.php');service=rows.find(s=>Number(s.id)===Number(id))}
    if(!service)throw new Error('No encontré ese servicio');
    currentAction='service_edit';
    modalEyebrow.textContent='EDITAR SERVICIO';modalTitle.textContent=service.name;
    modalStatus.textContent='';modalStatus.className='pilot';
    modalSave.hidden=false;modalSave.disabled=false;modalSave.textContent='Guardar cambios';
    modalFields.innerHTML=`<input type="hidden" name="service_id" value="${Number(service.id)}"><label>Nombre<input name="name" type="text" maxlength="140" value="${escapeHtml(service.name||'')}" required></label><div class="row"><label>Duración<input name="duration" type="number" min="5" max="1440" step="5" value="${Number(service.duration_minutes)||60}" required></label><label>Precio<input name="price" type="number" min="0" step="0.01" value="${service.price??''}"></label></div><label>Descripción <small>(opcional)</small><textarea name="description">${escapeHtml(service.description||'')}</textarea></label><label class="toggle-line"><input name="active" type="checkbox" ${Number(service.active)!==0?'checked':''}> Servicio activo</label><div class="entity-actions"><button type="button" class="danger" data-archive-service>Archivar servicio</button></div>`;
    modal.classList.add('open');
    modalFields.querySelector('[data-archive-service]').addEventListener('click',async()=>{
      try{await archiveService(service)}catch(e){modalStatus.textContent=e.message;modalStatus.className='pilot error'}
    });
  }catch(e){notify(e.message||'No pude abrir el servicio','error')}
}

form.addEventListener('submit',async e=>{
  if(!['appointment_edit','client_edit','service_edit'].includes(currentAction))return;
  e.preventDefault();e.stopImmediatePropagation();
  const fd=new FormData(form);
  modalSave.disabled=true;modalSave.textContent='Guardando…';modalStatus.textContent='';modalStatus.className='pilot';
  try{
    if(currentAction==='appointment_edit'){
      const newDate=String(fd.get('date'));
      await patchJson('../backend/api/appointments.php',{id:Number(fd.get('appointment_id')),client_id:Number(fd.get('client_id')),service_id:Number(fd.get('service_id')),starts_at:`${newDate} ${fd.get('time')}:00`,status:String(fd.get('status')),notes:String(fd.get('notes')||'')});
      selectedDate=newDate;const d=dateFromKey(newDate);calendarCursor=new Date(d.getFullYear(),d.getMonth(),1);
      await refreshSchedule();notify('Cita actualizada');
    }
    if(currentAction==='client_edit'){
      await patchJson('../backend/api/clients.php',{id:Number(fd.get('client_id')),name:String(fd.get('name')),phone:String(fd.get('phone')),email:String(fd.get('email')||''),birth_date:String(fd.get('birth_date')||''),notes:String(fd.get('notes')||'')});
      await clients();notify('Cliente actualizado');
    }
    if(currentAction==='service_edit'){
      await patchJson('../backend/api/services.php',{id:Number(fd.get('service_id')),name:String(fd.get('name')),description:String(fd.get('description')||''),duration_minutes:Number(fd.get('duration')||60),price:fd.get('price')===''?null:Number(fd.get('price')),active:fd.get('active')==='on'});
      await Promise.all([services(),appointments(),loadCalendar()]);notify('Servicio actualizado');
    }
    modalStatus.textContent='Cambios guardados ✓';modalStatus.className='pilot success';modalSave.textContent='Guardado';
    setTimeout(()=>modal.classList.remove('open'),450);
  }catch(err){modalStatus.textContent=err.message||'No se pudo actualizar';modalStatus.className='pilot error';modalSave.disabled=false;modalSave.textContent='Guardar cambios'}
},true);

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
Promise.all([clients(),services(),appointments(),loadCalendar()]).catch(()=>{});