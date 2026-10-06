document.querySelectorAll('[data-duration-picker]').forEach(picker => {
    const select = picker.querySelector('select');
    const steps = picker.querySelectorAll('[data-duration-step]');
    const presets = picker.querySelectorAll('[data-duration-value]');
    function sync() {
        steps.forEach(button => {
            const next = select.selectedIndex + Number(button.dataset.durationStep);
            button.disabled = next < 0 || next >= select.options.length;
        });
        presets.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.durationValue === select.value)));
    }
    steps.forEach(button => { button.hidden = false; });
    picker.querySelector('.duration-presets').hidden = false;
    picker.addEventListener('click', event => {
        const button = event.target.closest('button');
        if (!button || button.disabled) return;
        if (button.dataset.durationStep) {
            select.selectedIndex = Math.max(0, Math.min(select.options.length - 1, select.selectedIndex + Number(button.dataset.durationStep)));
        } else if (button.dataset.durationValue) select.value = button.dataset.durationValue;
        else return;
        select.dispatchEvent(new Event('change', {bubbles:true}));
    });
    select.addEventListener('change', sync);
    window.addEventListener('pageshow', sync);
    sync();
});
