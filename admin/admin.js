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
const defaultServiceCards=serviceCards.innerHTML;
let currentView='home';
let currentAction='appointment';

const actionByView={
  home:{type:'appointment',label:'Nueva cita',eyebrow:'NUEVA RESERVA',title:'Crear cita'},
  agenda:{type:'appointment',label:'Nueva cita',eyebrow:'NUEVA RESERVA',title:'Crear cita'},
  clients:{type:'client',label:'Nuevo cliente',eyebrow:'NUEVO CLIENTE',title:'Registrar cliente'},
  services:{type:'service',label:'Nuevo servicio',eyebrow:'NUEVO SERVICIO',title:'Crear servicio'}
};

function escapeHtml(value=''){
  return String(value).replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));
}

function localDate(){
  const d=new Date();
  const y=d.getFullYear();
  const m=String(d.getMonth()+1).padStart(2,'0');
  const day=String(d.getDate()).padStart(2,'0');
  return `${y}-${m}-${day}`;
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
  window.scrollTo({top:0,behavior:'smooth'});
}

navButtons.forEach(b=>b.addEventListener('click',()=>show(b.dataset.view)));
document.querySelectorAll('[data-go]').forEach(b=>b.addEventListener('click',()=>show(b.dataset.go)));

async function apiData(url){
  const r=await fetch(url,{cache:'no-store'});
  if(!r.ok)throw new Error('API error');
  const data=await r.json();
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
  modalSave.disabled=false;
  modalSave.textContent='Guardar';

  if(currentAction==='client'){
    modalFields.innerHTML=`
      <label>Nombre<input name="name" type="text" placeholder="Nombre completo" required></label>
      <label>WhatsApp<input name="phone" type="tel" placeholder="Número con lada" required></label>
      <label>Correo <small>(opcional)</small><input name="email" type="email" placeholder="correo@ejemplo.com"></label>`;
  }

  if(currentAction==='service'){
    modalFields.innerHTML=`
      <label>Nombre del servicio<input name="name" type="text" placeholder="Ej. Radiofrecuencia facial" required></label>
      <div class="row"><label>Duración<input name="duration" type="number" min="5" step="5" value="60" required></label><label>Precio<input name="price" type="number" min="0" step="0.01" placeholder="0.00"></label></div>
      <label>Descripción <small>(opcional)</small><textarea name="description" placeholder="Una descripción breve del tratamiento"></textarea></label>`;
  }

  if(currentAction==='appointment'){
    modalFields.innerHTML='<p class="loading">Preparando clientes y servicios…</p>';
    try{
      const [clientRows,serviceRows]=await Promise.all([
        apiData('../backend/api/clients.php'),
        apiData('../backend/api/services.php')
      ]);
      const canSave=clientRows.length>0&&serviceRows.length>0;
      const clientOptions=clientRows.map(c=>`<option value="${Number(c.id)}">${escapeHtml(c.name)}</option>`).join('');
      const serviceOptions=serviceRows.filter(s=>Number(s.active)!==0).map(s=>`<option value="${Number(s.id)}">${escapeHtml(s.name)}</option>`).join('');
      modalFields.innerHTML=`
        ${!clientRows.length?'<div class="empty-note">Aún no hay clientes registrados. Entra a <b>Clientes</b> y usa el botón + para crear el primero.</div>':''}
        ${!serviceRows.length?'<div class="empty-note">Aún no hay servicios registrados. Entra a <b>Servicios</b> y usa el botón + para crear el primero.</div>':''}
        <label>Cliente<select name="client_id" required ${!clientRows.length?'disabled':''}><option value="">Selecciona una clienta</option>${clientOptions}</select></label>
        <label>Servicio<select name="service_id" required ${!serviceRows.length?'disabled':''}><option value="">Selecciona un tratamiento</option>${serviceOptions}</select></label>
        <div class="row"><label>Fecha<input name="date" type="date" value="${localDate()}" required></label><label>Hora<input name="time" type="time" required></label></div>`;
      modalSave.disabled=!canSave;
    }catch(e){
      modalFields.innerHTML='<div class="empty-note">No pude cargar los datos ahora mismo. Intenta de nuevo.</div>';
      modalSave.disabled=true;
    }
  }

  modal.classList.add('open');
  setTimeout(()=>modal.querySelector('input,select,textarea')?.focus(),60);
}

addButton.addEventListener('click',openContextModal);
document.querySelector('.close').addEventListener('click',()=>modal.classList.remove('open'));
modal.addEventListener('click',e=>{if(e.target===modal)modal.classList.remove('open')});

form.addEventListener('submit',async e=>{
  e.preventDefault();
  if(modalSave.disabled)return;
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
      await postJson('../backend/api/appointments.php',{client_id:Number(fd.get('client_id')),service_id:Number(fd.get('service_id')),starts_at:`${fd.get('date')} ${fd.get('time')}:00`,status:'confirmed'});
      await appointments();
    }
    modalStatus.textContent='Guardado correctamente ✓';
    modalStatus.className='pilot success';
    modalSave.textContent='Guardado';
    setTimeout(()=>modal.classList.remove('open'),750);
  }catch(err){
    modalStatus.textContent=err.message||'No se pudo guardar';
    modalStatus.className='pilot error';
    modalSave.disabled=false;
    modalSave.textContent='Guardar';
  }
});

async function clients(){
  const list=document.getElementById('clientsList');
  try{
    const rows=await apiData('../backend/api/clients.php');
    document.getElementById('clientCount').textContent=rows.length;
    if(!rows.length){list.innerHTML='<p class="loading">La base está lista. El primer cliente aparecerá aquí.</p>';return}
    list.innerHTML=rows.slice(0,50).map((c,i)=>{
      const name=c.name||`Cliente ${i+1}`;
      const phone=c.phone||'Sin teléfono';
      const initials=name.split(' ').slice(0,2).map(x=>x[0]).join('').toUpperCase();
      return `<div class="client"><i>${escapeHtml(initials)}</i><div><b>${escapeHtml(name)}</b><span>${escapeHtml(phone)}</span></div><small>Ver ficha →</small></div>`;
    }).join('');
  }catch(e){
    document.getElementById('clientCount').textContent='0';
    list.innerHTML='<p class="loading">No pude consultar clientes ahora mismo.</p>';
  }
}

async function services(){
  try{
    const rows=await apiData('../backend/api/services.php');
    if(!rows.length){serviceCards.innerHTML=defaultServiceCards;return}
    serviceCards.innerHTML=rows.map(s=>`<article><i>✦</i><b>${escapeHtml(s.name)}</b><span>${Number(s.duration_minutes)||60} min${s.price!==null&&s.price!==undefined?` · $${Number(s.price).toLocaleString('es-MX')}`:''}</span></article>`).join('');
  }catch(e){serviceCards.innerHTML=defaultServiceCards}
}

async function appointments(){
  try{
    const rows=await apiData(`../backend/api/appointments.php?date=${localDate()}`);
    if(!rows.length)return;
    const list=document.querySelector('#agenda .list');
    list.innerHTML=rows.map(a=>{
      const raw=String(a.starts_at||'');
      const time=(raw.split(' ')[1]||raw.split('T')[1]||'').slice(0,5)||'—';
      const status=a.status==='confirmed'?'Confirmada':a.status==='completed'?'Realizada':a.status==='cancelled'?'Cancelada':'Pendiente';
      return `<div class="appointment${a.status==='pending'?' muted':''}"><time>${escapeHtml(time)}</time><i></i><div><b>${escapeHtml(a.client_name||'Cliente')}</b><span>${escapeHtml(a.service_name||'Servicio')}</span></div><mark>${status}</mark></div>`;
    }).join('');
  }catch(e){}
}

updateAddButton();
clients();
services();
appointments();