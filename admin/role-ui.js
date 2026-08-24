(()=>{
  const can=(permission)=>typeof window.solutionCan==='function'&&window.solutionCan(permission);
  const add=document.getElementById('contextAdd');

  function syncAddButton(){
    if(!add)return;
    const active=document.querySelector('[data-view].active')?.dataset.view||'home';
    const permission=active==='clients'?'clients.create':active==='services'?'services.create':'appointments.create';
    add.hidden=!can(permission);
  }

  function stripForbiddenActions(root=document){
    if(!can('clients.delete')) root.querySelectorAll('[data-archive-client]').forEach(el=>el.remove());
    if(!can('appointments.delete')) root.querySelectorAll('[data-delete-appointment]').forEach(el=>el.remove());
    if(!can('services.delete')) root.querySelectorAll('[data-archive-service]').forEach(el=>el.remove());
    if(!can('services.update')){
      root.querySelectorAll('.service-card-manage').forEach(el=>{
        el.title='Consulta de servicio';
        const small=el.querySelector('small');
        if(small)small.textContent=small.textContent.replace('Toca para editar','Solo lectura');
      });
    }
  }

  document.querySelectorAll('[data-view]').forEach(btn=>btn.addEventListener('click',()=>setTimeout(syncAddButton,0)));

  document.addEventListener('click',e=>{
    if(!can('services.update')&&e.target.closest('.service-card-manage')){
      e.preventDefault();
      e.stopImmediatePropagation();
    }
  },true);

  const observer=new MutationObserver(records=>{
    records.forEach(record=>record.addedNodes.forEach(node=>{
      if(node.nodeType===1)stripForbiddenActions(node);
    }));
    syncAddButton();
  });

  observer.observe(document.body,{childList:true,subtree:true});
  stripForbiddenActions();
  syncAddButton();
})();
