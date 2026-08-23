(()=>{
if(typeof window.openClientCard!=='function')return;
window.openClientCard=async function(client){
  try{
    currentAction='client_view';
    modalEyebrow.textContent='FICHA DE CLIENTE';
    modalTitle.textContent=client?.name||'Cliente';
    modalStatus.textContent='';
    modalStatus.className='pilot';
    modalSave.hidden=true;
    modalSave.disabled=false;

    const created=client?.created_at?new Intl.DateTimeFormat('es-MX',{day:'numeric',month:'long',year:'numeric'}).format(new Date(String(client.created_at).replace(' ','T'))):'—';
    modalFields.innerHTML=`
      <div class="entity-actions client-card-actions">
        <button type="button" data-edit-client>Editar ficha</button>
        <button type="button" class="danger" data-archive-client>Archivar cliente</button>
      </div>
      <div class="client-detail-grid">
        <div class="detail-item"><small>WHATSAPP</small><strong>${escapeHtml(client?.phone||'Sin teléfono')}</strong></div>
        <div class="detail-item"><small>CORREO</small><strong>${escapeHtml(client?.email||'Sin correo')}</strong></div>
        <div class="detail-item"><small>NACIMIENTO</small><strong>${escapeHtml(client?.birth_date||'No registrado')}</strong></div>
        <div class="detail-item"><small>CLIENTE DESDE</small><strong>${escapeHtml(created)}</strong></div>
      </div>
      ${client?.notes?`<div class="detail-notes"><small>NOTAS</small><p>${escapeHtml(client.notes)}</p></div>`:''}
      <div class="client-history" id="safeClientHistory"><small>HISTORIAL DE CITAS</small><p class="loading">Consultando historial…</p></div>`;

    modal.classList.add('open');

    modalFields.querySelector('[data-edit-client]')?.addEventListener('click',()=>openClientEdit(client));
    modalFields.querySelector('[data-archive-client]')?.addEventListener('click',async()=>{
      if(!confirm(`¿Archivar a ${client.name}? Sus citas e historial se conservarán.`))return;
      const btn=modalFields.querySelector('[data-archive-client]');
      if(btn)btn.disabled=true;
      try{
        await deleteJson('../backend/api/clients.php',{id:Number(client.id)});
        modal.classList.remove('open');
        notify('Cliente archivado');
        await Promise.all([clients(),appointments(),loadCalendar()]);
      }catch(e){
        modalStatus.textContent=e.message||'No se pudo archivar';
        modalStatus.className='pilot error';
        if(btn)btn.disabled=false;
      }
    });

    const history=modalFields.querySelector('#safeClientHistory');
    try{
      const rows=await apiData(`../backend/api/appointments.php?client_id=${Number(client.id)}`);
      if(!history||!modal.classList.contains('open'))return;
      history.innerHTML=`<small>HISTORIAL DE CITAS</small>${rows.length?rows.slice(0,10).map(a=>`<button type="button" class="history-row" data-appointment-id="${Number(a.id)}"><b>${escapeHtml(String(a.starts_at||'').slice(0,10))}</b><span>${escapeHtml(a.service_name||'Servicio')} · ${escapeHtml(a.status||'')}</span></button>`).join(''):'<p class="empty-note">Este cliente aún no tiene citas.</p>'}`;
    }catch(e){
      if(history)history.innerHTML='<small>HISTORIAL DE CITAS</small><p class="empty-note">No pude consultar el historial ahora mismo.</p>';
    }
  }catch(e){
    console.error('client-card-hotfix',e);
    notify('No pude abrir la ficha del cliente','error');
  }
};
})();