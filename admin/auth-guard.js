(()=>{
  try{
    const xhr=new XMLHttpRequest();
    xhr.open('GET','../backend/api/auth.php?action=me',false);
    xhr.setRequestHeader('Cache-Control','no-store');
    xhr.send(null);
    if(xhr.status!==200){
      window.location.replace('login.html');
      return;
    }
    const data=JSON.parse(xhr.responseText||'{}');
    const user=data.user||null;
    if(!user){window.location.replace('login.html');return}
    if(user.role==='client'){
      window.location.replace('../client/');
      return;
    }
    if(!['admin','operator'].includes(user.role)){
      window.location.replace('login.html');
      return;
    }
    window.solutionUser=user;
    window.solutionCan=(permission)=>Array.isArray(user.permissions)&&(user.permissions.includes('*')||user.permissions.includes(permission));
    window.addEventListener('DOMContentLoaded',()=>{
      document.body.dataset.role=user.role;
      const slot=document.getElementById('sessionSlot');
      if(slot){
        const session=document.createElement('div');
        session.className='spa-session-actions';

        const role=document.createElement('span');
        role.className='spa-role-badge';
        role.textContent=user.role==='admin'?'Administrador':'Operario';
        role.style.cssText='font:600 9px DM Sans,sans-serif;letter-spacing:.08em;text-transform:uppercase;color:#9a8b84';

        const logout=document.createElement('button');
        logout.type='button';
        logout.className='spa-logout';
        logout.textContent='Salir';
        logout.title='Cerrar sesión';
        logout.setAttribute('aria-label','Cerrar sesión');
        logout.style.cssText='border:0;background:transparent;color:#746964;font:500 11px DM Sans,sans-serif;cursor:pointer;padding:8px 10px';
        logout.addEventListener('click',async()=>{
          logout.disabled=true;
          await fetch('../backend/api/auth.php?action=logout',{method:'POST',headers:{'Content-Type':'application/json'},body:'{}'}).catch(()=>{});
          window.location.replace('login.html');
        });

        session.append(role,logout);
        slot.replaceChildren(session);
      }
      const roleUi=document.createElement('script');
      roleUi.src='role-ui.js?v=20260823-rbac1';
      document.body.appendChild(roleUi);
      if(user.role==='admin'){
        const usersUi=document.createElement('script');
        usersUi.src='users-ui.js?v=20260823-rbac1';
        document.body.appendChild(usersUi);
      }
    });
  }catch(e){
    window.location.replace('login.html');
  }
})();
