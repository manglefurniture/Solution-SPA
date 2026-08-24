(()=>{
  if(window.solutionUser?.role!=='admin')return;
  const nav=document.querySelector('.topnav');
  const main=document.querySelector('main');
  if(!nav||!main)return;

  const navButton=document.createElement('button');
  navButton.type='button';navButton.dataset.usersView='1';navButton.textContent='Usuarios';nav.appendChild(navButton);
  const view=document.createElement('section');
  view.id='users';view.className='view';
  view.innerHTML='<div class="title"><span>ACCESOS</span><h2>Usuarios</h2><p>Administradores, operarios y clientes con acceso al sistema.</p></div><div class="panel" style="padding:18px"><div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:14px"><strong style="font:28px Instrument Serif,serif;font-weight:400">Cuentas de acceso</strong><button type="button" id="newUser" style="border:0;background:#302626;color:white;padding:10px 14px;cursor:pointer">＋ Nuevo usuario</button></div><div id="usersList"><p class="empty-note">Consultando usuarios…</p></div></div>';
  main.appendChild(view);

  function esc(v=''){return String(v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]))}
  async function json(url,options={}){const r=await fetch(url,{cache:'no-store',...options});const d=await r.json().catch(()=>({}));if(!r.ok)throw new Error(d.error||'No se pudo completar');return d}
  async function load(){
    const list=document.getElementById('usersList');
    try{const d=await json('../backend/api/users.php');const rows=d.data||[];list.innerHTML=rows.length?rows.map(u=>`<div style="display:grid;grid-template-columns:1.2fr 1.2fr .7fr .7fr auto;gap:10px;align-items:center;padding:13px 4px;border-top:1px solid #eadfd7"><b style="font-size:11px">${esc(u.name)}</b><span style="font-size:9px;color:#81736d">${esc(u.email)}</span><span style="font-size:9px">${u.role==='admin'?'Administrador':u.role==='operator'?'Operario':'Cliente'}</span><span style="font-size:9px;color:#81736d">${Number(u.active)===1?'Activo':'Inactivo'}</span><button type="button" data-user-toggle="${Number(u.id)}" data-user-active="${Number(u.active)}" style="border:1px solid #dfd2ca;background:#fff;padding:7px 9px;font-size:8px;cursor:pointer">${Number(u.active)===1?'Desactivar':'Activar'}</button></div>`).join(''):'<p class="empty-note">No hay usuarios.</p>'}catch(e){list.innerHTML=`<p class="empty-note">${esc(e.message)}</p>`}
  }

  navButton.addEventListener('click',()=>{
    document.querySelectorAll('.view').forEach(v=>v.classList.remove('active'));
    document.querySelectorAll('[data-view]').forEach(b=>b.classList.remove('active'));
    view.classList.add('active');navButton.classList.add('active');document.getElementById('contextAdd').hidden=true;window.scrollTo({top:0,behavior:'smooth'});load();
  });
  document.querySelectorAll('[data-view]').forEach(b=>b.addEventListener('click',()=>{navButton.classList.remove('active');view.classList.remove('active')}));

  document.getElementById('newUser').addEventListener('click',async()=>{
    let clients=[];try{const d=await json('../backend/api/clients.php?per_page=200');clients=d.data||[]}catch(e){}
    const wrapper=document.createElement('div');wrapper.style.cssText='position:fixed;inset:0;background:rgba(48,38,38,.28);display:grid;place-items:center;z-index:9999;padding:20px';
    wrapper.innerHTML=`<form style="width:min(480px,100%);background:#fffdf9;border-radius:22px;padding:24px;border:1px solid #e5ddd5"><small style="letter-spacing:.14em;color:#8d7a72">NUEVO ACCESO</small><h2 style="font:34px Instrument Serif,serif;font-weight:400;margin:8px 0 18px">Crear usuario</h2><label style="display:grid;gap:6px;font-size:9px;margin:10px 0">Nombre<input name="name" required style="padding:12px;border:1px solid #e5ddd5"></label><label style="display:grid;gap:6px;font-size:9px;margin:10px 0">Correo<input name="email" type="email" required style="padding:12px;border:1px solid #e5ddd5"></label><label style="display:grid;gap:6px;font-size:9px;margin:10px 0">Contraseña<input name="password" type="password" minlength="8" required style="padding:12px;border:1px solid #e5ddd5"></label><label style="display:grid;gap:6px;font-size:9px;margin:10px 0">Rol<select name="role" style="padding:12px;border:1px solid #e5ddd5"><option value="operator">Operario</option><option value="client">Cliente</option><option value="admin">Administrador</option></select></label><label data-client-link style="display:none;gap:6px;font-size:9px;margin:10px 0">Ficha de cliente<select name="client_id" style="padding:12px;border:1px solid #e5ddd5"><option value="">Selecciona cliente</option>${clients.map(c=>`<option value="${Number(c.id)}">${esc(c.name)}</option>`).join('')}</select></label><p data-status style="font-size:9px;color:#a05d52;min-height:14px"></p><div style="display:flex;gap:8px"><button type="button" data-cancel style="flex:1;padding:11px;border:1px solid #ddd;background:white">Cancelar</button><button type="submit" style="flex:1;padding:11px;border:0;background:#302626;color:white">Crear</button></div></form>`;
    document.body.appendChild(wrapper);const f=wrapper.querySelector('form'),role=f.elements.role,clientLabel=wrapper.querySelector('[data-client-link]');
    const sync=()=>clientLabel.style.display=role.value==='client'?'grid':'none';role.addEventListener('change',sync);sync();
    wrapper.querySelector('[data-cancel]').addEventListener('click',()=>wrapper.remove());
    f.addEventListener('submit',async e=>{e.preventDefault();const fd=new FormData(f);const status=wrapper.querySelector('[data-status]');try{await json('../backend/api/users.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({name:fd.get('name'),email:fd.get('email'),password:fd.get('password'),role:fd.get('role'),client_id:fd.get('client_id')||null})});wrapper.remove();load()}catch(err){status.textContent=err.message}});
  });

  view.addEventListener('click',async e=>{
    const b=e.target.closest('[data-user-toggle]');if(!b)return;
    const id=Number(b.dataset.userToggle),active=Number(b.dataset.userActive)!==1;
    try{await json('../backend/api/users.php',{method:'PATCH',headers:{'Content-Type':'application/json'},body:JSON.stringify({id,active})});load()}catch(err){alert(err.message)}
  });
})();
