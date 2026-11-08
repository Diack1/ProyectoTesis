const cashSections = document.querySelectorAll('[data-cash-section]');
const showCashSection = name => {
    cashSections.forEach(button => button.classList.toggle('active', button.dataset.cashSection === name));
    document.querySelectorAll('[data-cash-panel]').forEach(panel => { panel.hidden = panel.dataset.cashPanel !== name; });
};
cashSections.forEach(button => button.addEventListener('click', () => {
    showCashSection(button.dataset.cashSection);
    sessionStorage.setItem('parkeo-caja-seccion', button.dataset.cashSection);
}));
const savedCashSection = sessionStorage.getItem('parkeo-caja-seccion');
showCashSection([...cashSections].some(button => button.dataset.cashSection === savedCashSection) ? savedCashSection : 'transacciones');

const closeCashModal = () => {
    document.querySelectorAll('.cash-modal').forEach(modal => { modal.hidden = true; });
    document.body.classList.remove('cash-modal-open');
};
document.querySelectorAll('[data-open-cash-modal]').forEach(button => button.addEventListener('click', () => {
    closeCashModal();
    const modal = document.getElementById(button.dataset.openCashModal);
    if (!modal) return;
    modal.hidden = false;
    document.body.classList.add('cash-modal-open');
    modal.querySelector('.cash-amount')?.focus();
}));
document.querySelectorAll('[data-close-cash-modal]').forEach(button => button.addEventListener('click', closeCashModal));
document.querySelectorAll('.cash-modal').forEach(modal => modal.addEventListener('click', event => {
    if (event.target === modal) closeCashModal();
}));
document.addEventListener('keydown', event => { if (event.key === 'Escape') closeCashModal(); });
setInterval(() => {
    if (!document.hidden && !document.querySelector('.cash-modal:not([hidden])')) window.location.reload();
}, 30000);

document.querySelectorAll('[data-cash-calculator]').forEach(form => {
    const input = form.querySelector('.cash-amount');
    const keypad = form.querySelector('[data-cash-keypad]');
    const update = () => {
        const amount = Math.round(Number(input.value || 0) * 100);
        const difference = form.querySelector('[data-cash-difference]');
        if (difference) {
            const delta = amount - Number(form.dataset.expected);
            difference.textContent = `${delta < 0 ? 'Faltante' : delta > 0 ? 'Sobrante' : 'Sin diferencia'} · S/ ${(Math.abs(delta) / 100).toFixed(2)}`;
        }
    };
    for (const key of ['1', '2', '3', '4', '5', '6', '7', '8', '9', '.', '0', '00', 'borrar', 'ok']) {
        const button = document.createElement('button');
        button.type = 'button'; button.dataset.key = key;
        button.textContent = key === 'borrar' ? '⌫' : key === 'ok' ? 'OK' : key;
        button.addEventListener('click', () => {
            if (key === 'ok') { input.blur(); return; }
            if (key === 'borrar') input.value = input.value.slice(0, -1);
            else if (key !== '.' || !input.value.includes('.')) input.value = `${input.value === '0.00' ? '' : input.value}${key}`;
            update();
        });
        keypad.append(button);
    }
    input.addEventListener('input', update);
    update();
});
