document.addEventListener('DOMContentLoaded', () => {
 document.querySelectorAll('[data-menu-toggle]').forEach(button => button.addEventListener('click', () => {
  const menu=document.getElementById(button.dataset.menuToggle); const open=button.getAttribute('aria-expanded')==='true';
  button.setAttribute('aria-expanded',String(!open)); menu.classList.toggle('is-open',!open);
 }));
 document.querySelectorAll('[data-space-filter]').forEach(button=>button.addEventListener('click',()=>{
  document.querySelectorAll('[data-space-filter]').forEach(b=>{b.classList.toggle('active',b===button);b.setAttribute('aria-pressed',String(b===button));});
  let visibles=0; document.querySelectorAll('[data-space-type]').forEach(card=>{
   const visible=button.dataset.spaceFilter==='all'||card.dataset.spaceType.split(' ').includes(button.dataset.spaceFilter); card.hidden=!visible;if(visible)visibles++;
  }); const empty=document.querySelector('[data-filter-empty]');if(empty)empty.hidden=visibles>0;
 }));
 const notice=document.querySelector('[data-notifications]');
 if(notice){let running=false;const update=async()=>{if(document.hidden||running)return;running=true;try{
  const r=await fetch(notice.dataset.notifications,{headers:{Accept:'application/json'},cache:'no-store'});
  if(!r.ok)throw new Error();const d=await r.json();document.querySelectorAll('[data-payment-count]').forEach(b=>{b.hidden=!d.cantidad;b.textContent=d.cantidad;});notice.textContent=d.cantidad?`${d.cantidad} pago(s) por verificar`:'Pagos al día · Sin revisiones pendientes';
 }catch(_){notice.textContent='Consultar bandeja de pagos';}finally{running=false;}};update();setInterval(update,15000);}
 document.querySelectorAll('[data-print]').forEach(b=>b.addEventListener('click',()=>window.print()));
 const method=document.querySelector('[data-payment-method]');if(method){const change=()=>{
  document.querySelectorAll('[data-cash-field]').forEach(e=>{e.hidden=method.value!=='efectivo';e.querySelectorAll('input').forEach(i=>i.disabled=e.hidden);});
  document.querySelectorAll('[data-transfer-field]').forEach(e=>{e.hidden=!['yape','plin'].includes(method.value);e.querySelectorAll('input').forEach(i=>i.disabled=e.hidden);});
 };method.addEventListener('change',change);change();}
 const space=document.querySelector('[data-entry-space]'),type=document.querySelector('[data-entry-type]');
 if(space&&type){const change=()=>{const allowed=(space.selectedOptions[0]?.dataset.types||'').split(',');
  Array.from(type.options).forEach(o=>{o.disabled=o.value!==''&&!allowed.includes(o.value);});if(type.selectedOptions[0]?.disabled)type.value='';
  const help=document.querySelector('[data-sensor-help]');if(help)help.textContent=space.selectedOptions[0]?.dataset.mode==='sensor'?'El ticket se entrega ahora. El cobro comenzará cuando el sensor detecte el vehículo estacionado.':'El cobro comienza al registrar este ingreso y entregar el ticket.';
 };space.addEventListener('change',change);change();}
});

if ('IntersectionObserver' in window && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
 const sections = document.querySelectorAll('body > main > section');
 const observer = new IntersectionObserver(entries => entries.forEach(entry => {
  if (entry.isIntersecting) {entry.target.classList.add('is-visible');observer.unobserve(entry.target);}
 }), {threshold:0.05});
 sections.forEach(section => {if(section.getBoundingClientRect().top > innerHeight){section.classList.add('reveal-ready');observer.observe(section);}});
}
