<script>
(() => {
 let active = null, backdrop = null;
 const close = (restore = true) => {
  if (!active) return;
  const {button, menu, placeholder} = active;
  placeholder.replaceWith(menu);
  menu.classList.remove('is-open'); button.setAttribute('aria-expanded', 'false');
  menu.removeAttribute('role'); menu.removeAttribute('aria-modal');
  document.body.classList.remove('drawer-open'); backdrop?.remove(); backdrop = null; active = null;
  if (restore) button.focus();
 };
 document.addEventListener('click', event => {
  const button = event.target.closest('[data-menu-toggle]');
  if (button) {
   if (active?.button === button) {close(); return;}
   close(false);
   const menu = document.getElementById(button.dataset.menuToggle);
   if (!menu) return;
   const placeholder = document.createComment('menu-position');
   menu.before(placeholder); document.body.append(menu);
   active = {button, menu, placeholder}; menu.classList.add('is-open'); button.setAttribute('aria-expanded', 'true');
   menu.setAttribute('role', 'dialog'); menu.setAttribute('aria-modal', 'true');
   if (!menu.hasAttribute('aria-label')) menu.setAttribute('aria-label', 'Menú principal');
   document.body.classList.add('drawer-open');
   backdrop = document.createElement('div'); backdrop.className = 'drawer-backdrop'; backdrop.addEventListener('click', () => close()); document.body.append(backdrop);
   menu.querySelector('[data-menu-close]')?.focus();
  } else if (event.target.closest('[data-menu-close]')) close();
  else if (active && event.target.closest('a')) close(false);
 });
 document.addEventListener('keydown', event => {
  if (!active) return;
  if (event.key === 'Escape') {event.preventDefault(); close();}
  if (event.key === 'Tab') {
   const nodes = [...active.menu.querySelectorAll('a,button,summary,input')].filter(el => !el.disabled && el.getClientRects().length);
   const first = nodes[0], last = nodes[nodes.length - 1];
   if (event.shiftKey && document.activeElement === first) {event.preventDefault(); last?.focus();}
   else if (!event.shiftKey && document.activeElement === last) {event.preventDefault(); first?.focus();}
  }
 });
 matchMedia('(max-width:760px)').addEventListener('change', () => close(false));
})();
</script>
