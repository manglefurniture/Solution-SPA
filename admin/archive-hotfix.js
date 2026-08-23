(()=>{
let currentClientId=null,currentClientName='cliente',currentServiceId=null,currentServiceName='servicio';

const origOpenClientCard=window.openClientCard;
if(typeof origOpenClientCard==='function')window.openClientCard=async function(client){currentClientId=Number(client?.id)||null;currentClientName=client?.name||'cliente';return origOpenClientCard(client)};
const origOpenClientEdit=window.openClientEdit;
if(typeof origOpenClientEdit==='function')window.openClientEdit=async function(client){currentClientId=Number(client?.id)||null;currentClientName=client?.name||'cliente';return origOpenClientEdit(client)};
const origOpenServiceCard=window.openServiceCard;
if(typeof origOpenServiceCard==='function')window.openServiceCard=async function(service,id){currentServiceId=Number(service?.id||id)||null;currentServiceName=service?.name||'servicio';return origOpenServiceCard(service,id)};

async function json(url,options={}){const r=await fetch(url,{cache:'no-store',...options});const data=await r.json().catch(()=>({}));if(!r.ok)throw new Error(data.error||'No se pudo completar la operación');return data}
async function resolveClientId(name){if(currentClientId)return currentClientId;const data=await json(`../backend/api/clients.php?q=${encodeURIComponent(name)}&include_archived=1&per_page=100`);const rows=Array.isArray(data)?data:(data.data||[]);const exact=rows.find(x=>String(x.name||'').trim().toLowerCase()===String(name||'').trim().toLowerCase());return Number(exact?.id)||null}
async function resolveServiceId(name){if(currentServiceId)return currentServiceId;const data=await json('../backend/api/services.php');const rows=Array.isArray(data)?data:(data.data||[]);const exact=rows.find(x=>String(x.name||'').trim().toLowerCase()===String(name||'').trim().toLowerCase());return Number(exact?.id)||null}

async function archive(endpoint,id,label,name){
  if(!id)throw new Error(`No pude identificar el ${label}. Cierra la ficha y ábrela de nuevo.`);
  if(!confirm(`¿Archivar ${label} “${name}”? Su historial se conservará.`))return false;
  await json(`../backend/api/${endpoint}`,{method:'DELETE',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:Number(id)})});
  document.getElementById('modal')?.classList.remove('open');
  window.uxToast?.(`${label[0].toUpperCase()+label.slice(1)} archivado`);
  if(label==='cliente'){
    currentClientId=null;
    await window.clients?.();
    await window.appointments?.();
    await window.loadCalendar?.();
  }else{
    currentServiceId=null;
    await window.services?.();
    await window.appointments?.();
    await window.loadCalendar?.();
  }
  return true;
}

document.addEventListener('click',async e=>{
  const c=e.target.closest('[data-delete-client]');
  if(c){
    e.preventDefault();e.stopImmediatePropagation();
    const name=document.getElementById('modalTitle')?.textContent?.trim()||currentClientName||'cliente';
    currentClientName=name;
    try{
      const hidden=Number(document.querySelector('#modal input[name="client_id"]')?.value)||null;
      const id=hidden||await resolveClientId(name);
      await archive('clients.php',id,'cliente',name);
    }catch(err){window.uxToast?.(err.message,'error');if(!window.uxToast)alert(err.message)}
    return;
  }
  const s=e.target.closest('[data-delete-service]');
  if(s){
    e.preventDefault();e.stopImmediatePropagation();
    const name=document.getElementById('modalTitle')?.textContent?.trim()||currentServiceName||'servicio';
    currentServiceName=name;
    try{
      const hidden=Number(document.querySelector('#modal input[name="service_id"]')?.value)||null;
      const id=hidden||await resolveServiceId(name);
      await archive('services.php',id,'servicio',name);
    }catch(err){window.uxToast?.(err.message,'error');if(!window.uxToast)alert(err.message)}
  }
},true);
})();