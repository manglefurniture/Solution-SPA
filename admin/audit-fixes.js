(()=>{
async function api(url,options={}){const r=await fetch(url,{cache:'no-store',...options});const data=await r.json().catch(()=>({}));if(!r.ok)throw new Error(data.error||'No se pudo completar la operación');return data}
async function refreshClientCount(){try{const data=await api('../backend/api/clients.php?count_only=1');const el=document.getElementById('clientCount');if(el)el.textContent=Number(data.total||0)}catch(e){}}
const baseClients=window.clients;
if(typeof baseClients==='function')window.clients=async function(...args){const result=await baseClients.apply(this,args);await refreshClientCount();return result};
refreshClientCount();
})();