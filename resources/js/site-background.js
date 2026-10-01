import '../css/shared/site-background.css';

const background = document.querySelector('[data-hero-background]');
const reduced = matchMedia('(prefers-reduced-motion: reduce)');
const update = () => background?.classList.toggle('is-running', !document.hidden && !reduced.matches);
document.addEventListener('visibilitychange', update);
reduced.addEventListener('change', update);
window.addEventListener('pageshow', update);
window.addEventListener('pagehide', () => background?.classList.remove('is-running'));
update();
