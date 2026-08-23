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
    window.solutionUser=data.user||null;
    window.addEventListener('DOMContentLoaded',()=>{
      const bar=document.querySelector('.topbar');
      if(!bar)return;
      const logout=document.createElement('button');
      logout.type='button';
      logout.className='spa-logout';
      logout.textContent='Salir';
      logout.title='Cerrar sesión';
      logout.setAttribute('aria-label','Cerrar sesión');
      logout.style.cssText='border:0;background:transparent;color:#746964;font:500 11px DM Sans,sans-serif;cursor:pointer;padding:8px 10px;position:absolute;right:22px;top:18px;z-index:4';
      logout.addEventListener('click',async()=>{
        logout.disabled=true;
        await fetch('../backend/api/auth.php?action=logout',{method:'POST',headers:{'Content-Type':'application/json'},body:'{}'}).catch(()=>{});
        window.location.replace('login.html');
      });
      bar.appendChild(logout);
    });
  }catch(e){
    window.location.replace('login.html');
  }
})();
