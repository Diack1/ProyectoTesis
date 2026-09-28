const entryPlate = document.getElementById('placa');
const entryClient = document.getElementById('entry-client');
if (entryPlate && entryClient) {
 let request;
 entryPlate.addEventListener('input', () => {request?.abort();entryClient.replaceChildren();});
 entryPlate.addEventListener('blur', async () => {
  request?.abort();
  const plate = entryPlate.value.toUpperCase().replace(/[\s-]+/g,'');
  if (!/^[A-Z0-9]{5,10}$/.test(plate)) return;
  request = new AbortController();
  const current = request;
  entryClient.textContent = 'Buscando vehículo…';
  try {
   const response = await fetch(entryClient.dataset.url, {method:'POST', headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':entryClient.dataset.token},body:JSON.stringify({placa:plate}),signal:current.signal});
   if (!response.ok) throw new Error();
   const data = await response.json();
   if(current!==request || current.signal.aborted) return;
   entryClient.replaceChildren();
   const text=document.createElement('p');
   text.textContent=data.coincidencia.cliente ? `Cliente asociado: ${data.coincidencia.cliente.nombre}. Comprueba quién conduce.` : 'Vehículo sin cliente asociado. Puedes continuar con la placa.';
   entryClient.append(text);
   for(const booking of data.coincidencia.reservas || []) {
    const link=document.createElement('a');const url=new URL(booking.url,location.href);
    if(url.origin!==location.origin)continue;
    link.href=url.href;link.className='btn btn-secondary';link.textContent=`Usar reserva ${booking.codigo} · ${booking.cliente}`;entryClient.append(link);
   }
  } catch(error) {if(error.name!=='AbortError')entryClient.textContent='No se pudo consultar el cliente. Puedes buscarlo en Clientes o continuar con la placa.';}
 });
}
