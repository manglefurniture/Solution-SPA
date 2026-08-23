(()=>{
async function api(url,options={}){const r=await fetch(url,{cache:'no-store',...options});const data=await r.json().catch(()=>({}));if(!r.ok)throw new Error(data.error||'No se pudo completar la operación');return data}
async function refreshClientCount(){try{const data=await api('../backend/api/clients.php?count_only=1');const el=document.getElementById('clientCount');if(el)el.textContent=Number(data.total||0)}catch(e){}}
async function archive(kind,id,name){const endpoint=kind==='client'?'clients.php':'services.php';const label=kind==='client'?'cliente':'servicio';if(!confirm(`¿Archivar ${label} “${name}”? Su historial se conservará.`))return;try{await api(`../backend/api/${endpoint}`,{method:'DELETE',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:Number(id)})});document.getElementById('modal')?.classList.remove('open');if(window.uxToast)window.uxToast(`${kind==='client'?'Cliente':'Servicio'} archivado`);if(kind==='client'){await clients();await refreshClientCount()}else await services();await Promise.all([appointments(),loadCalendar()])}catch(e){if(window.uxToast)window.uxToast(e.message,'error');else alert(e.message)}}

document.addEventListener('click',e=>{
  const c=e.target.closest('[data-delete-client]');
  if(c){
    e.preventDefault();e.stopImmediatePropagation();
    const editId=document.querySelector('#modal input[name="client_id"]')?.value;
    const clientButton=document.querySelector('#clientsList .client-open[data-client-index].is-current');
    const fallbackId=c.dataset.clientId||clientButton?.dataset.clientId||'';
    const id=editId||fallbackId;
    const name=document.getElementById('modalTitle')?.textContent||'cliente';
    if(id)archive('client',id,name);
    return;
  }
  const s=e.target.closest('[data-delete-service]');
  if(s){
    e.preventDefault();e.stopImmediatePropagation();
    const id=document.querySelector('#modal input[name="service_id"]')?.value||s.dataset.serviceId||'';
    const name=document.getElementById('modalTitle')?.textContent||'servicio';
    if(id)archive('service',id,name);
  }
},true);

function relabel(root=document){
  root.querySelectorAll?.('[data-delete-client]').forEach(b=>{if(b.textContent!=='Archivar cliente')b.textContent='Archivar cliente'});
  root.querySelectorAll?.('[data-delete-service]').forEach(b=>{if(b.textContent!=='Archivar servicio')b.textContent='Archivar servicio'});
}
const observer=new MutationObserver(records=>{
  for(const record of records){
    for(const node of record.addedNodes){
      if(node.nodeType!==1)continue;
      if(node.matches?.('[data-delete-client],[data-delete-service]'))relabel(node.parentElement||document);
      else if(node.querySelector?.('[data-delete-client],[data-delete-service]'))relabel(node);
    }
  }
});
observer.observe(document.body,{childList:true,subtree:true});
relabel();

const baseClients=window.clients;
if(typeof baseClients==='function'){
  window.clients=async function(...args){const result=await baseClients.apply(this,args);await refreshClientCount();return result}
}
refreshClientCount();
})();