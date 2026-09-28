document.addEventListener('DOMContentLoaded', () => {
 const form=document.getElementById('formNuevaReserva');if(!form)return;
 const plate=form.querySelector('#placa'),vehicle=form.querySelector('#vehiculo_tipo_id');
 const update=()=>{
  form.querySelector('[data-booking-vehicle]').textContent=vehicle.value?vehicle.selectedOptions[0].textContent.trim():'Por elegir';
  form.querySelector('[data-booking-plate]').textContent=plate.value.toUpperCase()||'Por completar';
 };
 form.addEventListener('input',update);form.addEventListener('change',update);
 window.addEventListener('pageshow',()=>{const button=form.querySelector('#btnNuevaReserva');button.disabled=false;button.textContent='Continuar a confirmación';update();});update();
});
