(()=>{
async function api(url,options={}){const r=await fetch(url,{cache:'no-store',...options});const data=await r.json().catch(()=>({}));if(!r.ok)throw new Error(data.error||'No se pudo completar la operación');return data}
async function refreshClientCount(){try{const data=await api('../backend/api/clients.php?count_only=1');const el=document.getElementById('clientCount');if(el)el.textContent=Number(data.total||0)}catch(e){}}

function relabel(root=document){
  root.querySelectorAll?.('[data-delete-client]').forEach(b=>{b.textContent='Archivar cliente';b.title='Archiva al cliente sin borrar su historial'});
  root.querySelectorAll?.('[data-delete-service]').forEach(b=>{b.textContent='Archivar servicio';b.title='Archiva el servicio sin borrar su historial'});
}

const observer=new MutationObserver(records=>{
  let needed=false;
  for(const record of records){
    for(const node of record.addedNodes){
      if(node.nodeType!==1)continue;
      if(node.matches?.('[data-delete-client],[data-delete-service]')||node.querySelector?.('[data-delete-client],[data-delete-service]')){needed=true;break}
    }
    if(needed)break;
  }
  if(needed)relabel();
});
observer.observe(document.body,{childList:true,subtree:true});
relabel();

const baseClients=window.clients;
if(typeof baseClients==='function'){
  window.clients=async function(...args){const result=await baseClients.apply(this,args);await refreshClientCount();return result}
}
refreshClientCount();
})();