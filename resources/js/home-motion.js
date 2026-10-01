import '../css/public/home-motion.css';

const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
const count = document.querySelector('[data-availability-count]');
const meter = document.querySelector('#occupancy-meter');
const cards = [...document.querySelectorAll('.how-grid article')];
let frame = 0;
let current = 0;
let animations = [];

function animateAvailability() {
    if (!count || !meter) return;
    cancelAnimationFrame(frame);
    const target = Math.max(0, Number(count.dataset.availabilityCount) || 0);
    const start = current;
    const startTime = performance.now();
    const finish = () => {
        count.textContent = String(target);
        meter.value = target;
        current = target;
    };
    if (reduced.matches || document.hidden) return finish();
    function tick(now) {
        const progress = Math.min(1, (now - startTime) / 800);
        current = start + (target - start) * (1 - (1 - progress) ** 3);
        count.textContent = String(Math.round(current));
        meter.value = current;
        if (progress < 1) frame = requestAnimationFrame(tick);
        else finish();
    }
    frame = requestAnimationFrame(tick);
}

const revealObserver = new IntersectionObserver(entries => {
    entries.filter(entry => entry.isIntersecting).forEach((entry, i) => {
        revealObserver.unobserve(entry.target);
        if (!reduced.matches && entry.target.animate) {
            const animation = entry.target.animate([
                { opacity: 0, transform: 'translateY(18px)' },
                { opacity: 1, transform: 'translateY(0)' },
            ], { duration: 450, delay: i * 90, easing: 'ease-out', fill: 'backwards' });
            animations.push(animation);
            animation.onfinish = () => { animations = animations.filter(item => item !== animation); };
        }
    });
}, { threshold: .12 });
cards.forEach(card => revealObserver.observe(card));

// Future availability updates can change this attribute without replacing the UI.
const availabilityObserver = new MutationObserver(animateAvailability);
if (count) availabilityObserver.observe(count, { attributes: true, attributeFilter: ['data-availability-count'] });
function preferencesChanged() {
    animateAvailability();
    if (reduced.matches) animations.forEach(animation => animation.cancel());
}
function visibilityChanged() {
    if (document.hidden) animateAvailability();
}
reduced.addEventListener('change', preferencesChanged);
document.addEventListener('visibilitychange', visibilityChanged);
animateAvailability();
window.addEventListener('pagehide', event => {
    cancelAnimationFrame(frame);
    animations.forEach(animation => animation.cancel());
    if (event.persisted) return;
    revealObserver.disconnect();
    availabilityObserver.disconnect();
    reduced.removeEventListener('change', preferencesChanged);
    document.removeEventListener('visibilitychange', visibilityChanged);
});
window.addEventListener('pageshow', event => {
    if (event.persisted) { animateAvailability(); }
});
