const navButtons=document.querySelectorAll('[data-view]');
const views=document.querySelectorAll('.view');
const addButton=document.getElementById('contextAdd');
const addLabel=addButton.querySelector('span');
const modal=document.getElementById('modal');
const form=document.getElementById('contextForm');
const modalEyebrow=document.getElementById('modalEyebrow');
const modalTitle=document.getElementById('modalTitle');
const modalFields=document.getElementById('modalFields');
const modalSave=document.getElementById('modalSave');
const modalStatus=document.getElementById('modalStatus');
const serviceCards=document.querySelector('.servicecards');
const homeAgenda=document.getElementById('homeAgenda');
const agendaList=document.getElementById('agendaList');
const calendarGrid=document.getElementById('calendarGrid');
const calendarMonth=document.getElementById('calendarMonth');
const selectedDayTitle=document.getElementById('selectedDayTitle');
const selectedDayCount=document.getElementById('selectedDayCount');
let currentView='home';
let currentAction='appointment';
let selectedDate=localDate();
const nowForCalendar=new Date();
let calendarCursor=new Date(nowForCalendar.getFullYear(),nowForCalendar.getMonth(),1);
let monthAppointments=[];

const actionByView={
  home:{type:'appointment',label:'Nueva cita',eyebrow:'NUEVA RESERVA',title:'Crear cita'},
  agenda:{type:'appointment',label:'Nueva cita',eyebrow:'NUEVA RESERVA',title:'Crear cita'},
  clients:{type:'client',label:'Nuevo cliente',eyebrow:'NUEVO CLIENTE',title:'Registrar cliente'},
  services:{type:'service',label:'Nuevo servicio',eyebrow:'NUEVO SERVICIO',title:'Crear servicio'}
};

function escapeHtml(value=''){
  return String(value).replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt',"'":'&#039;','"':'&quot;'}[c]));
}

function localDate(date=new Date()){
  const y=date.getFullYear();
  const m=String(date.getMonth()+1).padStart(2,'0');
  const day=String(date.getDate()).padStart(2,'0');
  return `${y}-${m}-${day}`;
}

function localDateTime(date=new Date()){
  const h=String(date.getHours()).padStart(2,'0');
  const min=String(date.getMinutes()).padStart(2,'0');
  const sec=String(date.getSeconds()).padStart(2,'0');
  return `${localDate(date)} ${h}:${min}:${sec}`;
}

function monthKey(date){
  return `${date.getFullYear()}-${String(date.getMonth()+1).padStart(2,'0')}`;
}

function dateFromKey(key){
  const [y,m,d]=key.split('-').map(Number);
  return new Date(y,m-1,d);
}

function appointmentDateKey(a){
  return String(a.starts_at||'').slice(0,10);
}

function setCurrentDate(){
  const text=new Intl.DateTimeFormat('es-MX',{weekday:'long',day:'numeric',month:'long'}).format(new Date());
  document.getElementById('currentDate').textContent=text.toUpperCase().replace(' DE ',' · ');
}

function updateAddButton(animate=false){
  const action=actionByView[currentView]||actionByView.home;
  addLabel.textContent=action.label;
  addButton.dataset.kind=action.type;
  addButton.setAttribute('aria-label',action.label);
  addButton.title=action.label;
  if(animate){
    addButton.classList.remove('context-change');
    void addButton.offsetWidth;
    addButton.classList.add('context-change');
    window.setTimeout(()=>addButton.classList.remove('context-change'),380);
  }
}

function show(id){
  currentView=id;
  views.forEach(v=>v.classList.toggle('active',v.id===id));
  navButtons.forEach(b=>b.classList.toggle('active',b.dataset.view===id));
  updateAddButton(true);
  if(id==='agenda')loadCalendar();
  window.scrollTo({top:0,behavior:'smooth'});
}

navButtons.forEach(b=>b.addEventListener('click',()=>show(b.dataset.view)));
document.querySelectorAll('[data-go]').forEach(b=>b.addEventListener('click',()=>show(b.dataset.go)));

async function apiJson(url){
  const r=await fetch(url,{cache:'no-store'});
  const data=await r.json().catch(()=>({}));
  if(!r.ok)throw new Error(data.error||'No se pudo consultar');
  return data;
}

async function apiData(url){
  const data=await apiJson(url);
  return Array.isArray(data)?data:(data.data||[]);
}

async function postJson(url,payload){
  const r=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
  const data=await r.json().catch(()=>({}));
  if(!r.ok)throw new Error(data.error||'No se pudo guardar');
  return data;
}

async function openContextModal(){
  const action=actionByView[currentView]||actionByView.home;
  currentAction=action.type;
  modalEyebrow.textContent=action.eyebrow;
  modalTitle.textContent=action.title;
  modalStatus.textContent='';
  modalStatus.className='pilot';
  modalSave.hidden=false;
  modalSave.disabled=false;
  modalSave.textContent='Guardar';

  if(currentAction==='client'){
    modalFields.innerHTML=`
      <label>Nombre<input name="name" type="text" placeholder="Nombre completo" maxlength="120" required></label>
      <label>WhatsApp<input name="phone" type="tel" placeholder="Número con lada" maxlength="30" required></label>
      <label>Correo <small>(opcional)</small><input name="email" type="email" maxlength="160" placeholder="correo@ejemplo.com"></label>`;
  }

  if(currentAction==='service'){
    modalFields.innerHTML=`
      <label>Nombre del servicio<input name="name" type="text" maxlength="140" placeholder="Ej. Radiofrecuencia facial" required></label>
      <div class="row"><label>Duración<input name="duration" type="number" min="5" max="1440" step="5" value="60" required></label><label>Precio<input name="price" type="number" min="0" max="99999999.99" step="0.01" placeholder="0.00"></label></div>
      <label>Descripción <small>(opcional)</small><textarea name="description" placeholder="Una descripción breve del tratamiento"></textarea></label>`;
  }

  if(currentAction==='appointment'){
    modalFields.innerHTML='<p class="loading">Preparando clientes y servicios…</p>';
    try{
      const [clientRows,serviceRows]=await Promise.all([
        apiData('../backend/api/clients.php?per_page=200'),
        apiData('../backend/api/services.php')
      ]);
      const activeServices=serviceRows.filter(s=>Number(s.active)!==0);
      const canSave=clientRows.length>0&&activeServices.length>0;
      const clientOptions=clientRows.map(c=>`<option value="${Number(c.id)}">${escapeHtml(c.name)}</option>`).join('');
      const serviceOptions=activeServices.map(s=>`<option value="${Number(s.id)}">${escapeHtml(s.name)}</option>`).join('');
      const defaultDate=currentView==='agenda'?selectedDate:localDate();
      modalFields.innerHTML=`
        ${!clientRows.length?'<div class="empty-note">Aún no hay clientes registrados. Entra a <b>Clientes</b> y usa el botón + para crear el primero.</div>':''}
        ${!activeServices.length?'<div class="empty-note">Aún no hay servicios registrados. Entra a <b>Servicios</b> y usa el botón + para crear el primero.</div>':''}
        <label>Cliente<select name="client_id" required ${!clientRows.length?'disabled':''}><option value="">Selecciona una clienta</option>${clientOptions}</select></label>
        <label>Servicio<select name="service_id" required ${!activeServices.length?'disabled':''}><option value="">Selecciona un tratamiento</option>${serviceOptions}</select></label>
        <div class="row"><label>Fecha<input name="date" type="date" value="${defaultDate}" required></label><label>Hora<input name="time" type="time" required></label></div>`;
      modalSave.disabled=!canSave;
    }catch(e){
      modalFields.innerHTML=`<div class="empty-note">${escapeHtml(e.message||'No pude cargar los datos ahora mismo.')}</div>`;
      modalSave.disabled=true;
    }
  }

  modal.classList.add('open');
  setTimeout(()=>modal.querySelector('input,select,textarea')?.focus(),60);
}

function openClientCard(client){
  currentAction='client_view';
  modalEyebrow.textContent='FICHA DE CLIENTE';
  modalTitle.textContent=client.name||'Cliente';
  modalStatus.textContent='';
  modalSave.hidden=true;
  const created=client.created_at?new Intl.DateTimeFormat('es-MX',{day:'numeric',month:'long',year:'numeric'}).format(new Date(String(client.created_at).replace(' ','T'))):'—';
  modalFields.innerHTML=`
    <div class="client-detail-grid">
      <div class="detail-item"><small>WHATSAPP</small><strong>${escapeHtml(client.phone||'Sin teléfono')}</strong></div>
      <div class="detail-item"><small>CORREO</small><strong>${escapeHtml(client.email||'Sin correo')}</strong></div>
      <div class="detail-item"><small>NACIMIENTO</small><strong>${escapeHtml(client.birth_date||'No registrado')}</strong></div>
      <div class="detail-item"><small>CLIENTE DESDE</small><strong>${escapeHtml(created)}</strong></div>
    </div>
    ${client.notes?`<div class="detail-notes"><small>NOTAS</small><p>${escapeHtml(client.notes)}</p></div>`:''}`;
  modal.classList.add('open');
}

addButton.addEventListener('click',openContextModal);
document.querySelector('.close').addEventListener('click',()=>modal.classList.remove('open'));
modal.addEventListener('click',e=>{if(e.target===modal)modal.classList.remove('open')});

form.addEventListener('submit',async e=>{
  e.preventDefault();
  if(currentAction==='client_view'||modalSave.disabled)return;
  const fd=new FormData(form);
  modalSave.disabled=true;
  modalSave.textContent='Guardando…';
  modalStatus.textContent='';
  modalStatus.className='pilot';
  try{
    if(currentAction==='client'){
      await postJson('../backend/api/clients.php',{name:fd.get('name'),phone:fd.get('phone'),email:fd.get('email')||null});
      await clients();
    }
    if(currentAction==='service'){
      await postJson('../backend/api/services.php',{name:fd.get('name'),description:fd.get('description')||null,duration_minutes:Number(fd.get('duration')||60),price:fd.get('price')===''?null:Number(fd.get('price')),active:true});
      await services();
    }
    if(currentAction==='appointment'){
      const newDate=String(fd.get('date'));
      await postJson('../backend/api/appointments.php',{client_id:Number(fd.get('client_id')),service_id:Number(fd.get('service_id')),starts_at:`${newDate} ${fd.get('time')}:00`,status:'confirmed'});
      selectedDate=newDate;
      const d=dateFromKey(newDate);
      calendarCursor=new Date(d.getFullYear(),d.getMonth(),1);
      await Promise.all([appointments(),loadCalendar()]);
    }
    modalStatus.textContent='Guardado correctamente ✓';
    modalStatus.className='pilot success';
    modalSave.textContent='Guardado';
    setTimeout(()=>modal.classList.remove('open'),750);
  }catch(err){
    modalStatus.textContent=err.message||'No se pudo guardar';
    modalSave.disabled=false;
    modalSave.textContent='Guardar';
    modalStatus.className='pilot error';
  }
});

async function clients(){
  const list=document.getElementById('clientsList');
  try{
    const payload=await apiJson('../backend/api/clients.php?per_page=200');
    const rows=Array.isArray(payload)?payload:(payload.data||[]);
    const total=Number(payload?.meta?.total??rows.length);
    document.getElementById('clientCount').textContent=total;
    if(!rows.length){list.innerHTML='<p class="empty-note">Aún no hay clientes registrados.</p>';return}
    list.innerHTML=rows.slice(0,50).map((c,i)=>{
      const name=c.name||`Cliente ${i+1}`;
      const phone=c.phone||'Sin teléfono';
      const initials=name.split(' ').slice(0,2).map(x=>x[0]).join('').toUpperCase();
      return `<button type="button" class="client client-open" data-client-index="${i}"><i>${escapeHtml(initials)}</i><div><b>${escapeHtml(name)}</b><span>${escapeHtml(phone)}</span></div><small>Ver ficha →</small></button>`;
    }).join('');
    list.querySelectorAll('[data-client-index]').forEach(button=>button.addEventListener('click',()=>{
      const client=rows[Number(button.dataset.clientIndex)];
      if(client)openClientCard(client);
    }));
  }catch(e){
    document.getElementById('clientCount').textContent='0';
    list.innerHTML='<p class="empty-note">No pude consultar clientes ahora mismo.</p>';
  }
}

async function services(){
  try{
    const rows=await apiData('../backend/api/services.php');
    const activeRows=rows.filter(s=>Number(s.active)!==0);
    document.getElementById('serviceCount').textContent=activeRows.length;
    if(!rows.length){serviceCards.innerHTML='<p class="empty-note">Aún no hay servicios registrados.</p>';return}
    serviceCards.innerHTML=rows.map(s=>`<button type="button" class="service-card-manage${Number(s.active)===0?' inactive':''}" data-service-id="${Number(s.id)}"><i>✦</i><b>${escapeHtml(s.name)}</b><span>${Number(s.duration_minutes)||60} min${s.price!==null&&s.price!==undefined?` · $${Number(s.price).toLocaleString('es-MX')}`:''}</span><small>${Number(s.active)!==0?'Activo · Toca para editar':'Inactivo · Toca para editar'}</small></button>`).join('');
    serviceCards.querySelectorAll('[data-service-id]').forEach((el,i)=>el._serviceData=rows[i]);
  }catch(e){
    document.getElementById('serviceCount').textContent='0';
    serviceCards.innerHTML='<p class="empty-note">No pude consultar los servicios ahora mismo.</p>';
  }
}

function appointmentHtml(a,includeDuration=true){
  const raw=String(a.starts_at||'');
  const time=(raw.split(' ')[1]||raw.split('T')[1]||'').slice(0,5)||'—';
  const status=a.status==='confirmed'?'Confirmada':a.status==='completed'?'Realizada':a.status==='cancelled'?'Cancelada':'Pendiente';
  const duration=Number(a.duration_minutes)||0;
  return `<div class="appointment${a.status==='pending'?' muted':''}"><time>${escapeHtml(time)}${includeDuration&&duration?`<small>${duration} min</small>`:''}</time><i></i><div><b>${escapeHtml(a.client_name||'Cliente')}</b><span>${escapeHtml(a.service_name||'Servicio')}</span></div><mark>${status}</mark></div>`;
}

function upcomingAppointmentHtml(a){
  const dateKey=appointmentDateKey(a);
  const when=dateKey===localDate()?'Hoy':new Intl.DateTimeFormat('es-MX',{day:'numeric',month:'short'}).format(dateFromKey(dateKey));
  return appointmentHtml({...a,service_name:`${a.service_name||'Servicio'} · ${when}`},true);
}

async function appointments(){
  const today=document.getElementById('todayCount');
  const pending=document.getElementById('todayPending');
  const nextTime=document.getElementById('nextTime');
  const nextClient=document.getElementById('nextClient');
  try{
    const [todayRows,nextRows]=await Promise.all([
      apiData(`../backend/api/appointments.php?date=${localDate()}`),
      apiData(`../backend/api/appointments.php?next_from=${encodeURIComponent(localDateTime())}&limit=3`)
    ]);
    const rows=todayRows.filter(a=>a.status!=='cancelled');
    today.textContent=rows.length;
    const pendingCount=rows.filter(a=>a.status!=='completed').length;
    pending.textContent=rows.length?`${pendingCount} por atender`:'Sin citas registradas';
    homeAgenda.innerHTML=nextRows.length?nextRows.map(upcomingAppointmentHtml).join(''):'<p class="empty-note">No hay próximas citas registradas.</p>';

    const upcoming=nextRows[0];
    if(upcoming){
      const raw=String(upcoming.starts_at||'');
      const time=(raw.split(' ')[1]||raw.split('T')[1]||'').slice(0,5)||'—';
      const dateKey=appointmentDateKey(upcoming);
      const when=dateKey===localDate()?'Hoy':new Intl.DateTimeFormat('es-MX',{day:'numeric',month:'short'}).format(dateFromKey(dateKey));
      nextTime.textContent=time;
      nextClient.textContent=`${upcoming.client_name||'Cliente'} · ${when}`;
    }else{
      nextTime.textContent='—';
      nextClient.textContent='Sin cita próxima';
    }
  }catch(e){
    today.textContent='0';
    pending.textContent='No se pudo consultar';
    homeAgenda.innerHTML='<p class="empty-note">No pude consultar la agenda ahora mismo.</p>';
    nextTime.textContent='—';
    nextClient.textContent='Sin datos';
  }
}

function selectedDayRows(){
  return monthAppointments.filter(a=>a.status!=='cancelled'&&appointmentDateKey(a)===selectedDate);
}

function renderSelectedDay(){
  const date=dateFromKey(selectedDate);
  selectedDayTitle.textContent=new Intl.DateTimeFormat('es-MX',{weekday:'long',day:'numeric',month:'long'}).format(date);
  const rows=selectedDayRows();
  selectedDayCount.textContent=`${rows.length} ${rows.length===1?'cita':'citas'}`;
  agendaList.innerHTML=rows.length?rows.map(a=>appointmentHtml(a,true)).join(''):'<p class="empty-note">No hay citas registradas para este día.</p>';
}

function renderCalendar(){
  const year=calendarCursor.getFullYear();
  const month=calendarCursor.getMonth();
  calendarMonth.textContent=new Intl.DateTimeFormat('es-MX',{month:'long',year:'numeric'}).format(calendarCursor);
  const firstDay=new Date(year,month,1);
  const daysInMonth=new Date(year,month+1,0).getDate();
  const leading=(firstDay.getDay()+6)%7;
  const counts={};
  monthAppointments.filter(a=>a.status!=='cancelled').forEach(a=>{
    const key=appointmentDateKey(a);
    counts[key]=(counts[key]||0)+1;
  });

  const cells=[];
  for(let i=0;i<leading;i++)cells.push('<div class="calendar-blank" aria-hidden="true"></div>');
  for(let day=1;day<=daysInMonth;day++){
    const key=`${year}-${String(month+1).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
    const count=counts[key]||0;
    const classes=['calendar-day'];
    if(count>0)classes.push('has-appointments');
    if(key===localDate())classes.push('is-today');
    if(key===selectedDate)classes.push('is-selected');
    const countIndicator=count>0?`<span class="day-count">${count}</span>`:'';
    cells.push(`<button type="button" class="${classes.join(' ')}" data-date="${key}" aria-label="${day}${count>0?`: ${count} ${count===1?'cita':'citas'}`:''}"><span class="day-number">${day}</span>${countIndicator}</button>`);
  }
  calendarGrid.innerHTML=cells.join('');
  calendarGrid.querySelectorAll('[data-date]').forEach(button=>button.addEventListener('click',()=>{
    selectedDate=button.dataset.date;
    renderCalendar();
    renderSelectedDay();
    document.querySelector('.day-detail')?.scrollIntoView({behavior:'smooth',block:'nearest'});
  }));
}

async function loadCalendar(){
  const month=monthKey(calendarCursor);
  calendarGrid.innerHTML='<p class="loading calendar-loading">Cargando mes…</p>';
  try{
    monthAppointments=await apiData(`../backend/api/appointments.php?month=${month}`);
    if(!selectedDate.startsWith(month))selectedDate=`${month}-01`;
    renderCalendar();
    renderSelectedDay();
  }catch(e){
    monthAppointments=[];
    calendarGrid.innerHTML='<p class="empty-note calendar-loading">No pude consultar este mes ahora mismo.</p>';
    selectedDayCount.textContent='0 citas';
    agendaList.innerHTML='<p class="empty-note">No pude consultar las citas de este día.</p>';
  }
}

document.getElementById('prevMonth').addEventListener('click',()=>{
  calendarCursor=new Date(calendarCursor.getFullYear(),calendarCursor.getMonth()-1,1);
  selectedDate=`${monthKey(calendarCursor)}-01`;
  loadCalendar();
});

document.getElementById('nextMonth').addEventListener('click',()=>{
  calendarCursor=new Date(calendarCursor.getFullYear(),calendarCursor.getMonth()+1,1);
  selectedDate=`${monthKey(calendarCursor)}-01`;
  loadCalendar();
});

setCurrentDate();
updateAddButton();