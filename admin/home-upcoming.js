(async()=>{
  const container=document.getElementById('homeAgenda');
  if(!container)return;

  function esc(v=''){
    return String(v).replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));
  }

  function dateLabel(value){
    const raw=String(value||'');
    const dateKey=raw.slice(0,10);
    const [y,m,d]=dateKey.split('-').map(Number);
    if(!y||!m||!d)return '';
    const dt=new Date(y,m-1,d);
    const today=new Date();
    const todayKey=`${today.getFullYear()}-${String(today.getMonth()+1).padStart(2,'0')}-${String(today.getDate()).padStart(2,'0')}`;
    if(dateKey===todayKey)return 'Hoy';
    return new Intl.DateTimeFormat('es-MX',{weekday:'short',day:'numeric',month:'short'}).format(dt);
  }

  try{
    const r=await fetch('../backend/api/upcoming.php?limit=3',{cache:'no-store'});
    const payload=await r.json().catch(()=>({}));
    if(!r.ok)throw new Error(payload.error||'No se pudo consultar');
    const rows=payload.data||[];

    if(!rows.length){
      container.innerHTML='<p class="empty-note">No hay próximas citas registradas.</p>';
      return;
    }

    container.innerHTML=rows.map(a=>{
      const raw=String(a.starts_at||'');
      const time=(raw.split(' ')[1]||raw.split('T')[1]||'').slice(0,5)||'—';
      const duration=Number(a.duration_minutes)||0;
      const status=a.status==='confirmed'?'Confirmada':'Pendiente';
      return `<div class="appointment${a.status==='pending'?' muted':''}"><time>${esc(time)}${duration?`<small>${duration} min</small>`:''}</time><i></i><div><b>${esc(a.client_name||'Cliente')}</b><span>${esc(a.service_name||'Servicio')} · ${esc(dateLabel(a.starts_at))}</span></div><mark>${status}</mark></div>`;
    }).join('');
  }catch(e){
    container.innerHTML='<p class="empty-note">No pude consultar las próximas citas ahora mismo.</p>';
  }
})();
