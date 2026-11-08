(() => {
    const form = document.getElementById('activity-filters');
    const mode = document.getElementById('activity-mode');
    if (!form || !mode) return;
    const day = form.querySelector('.activity-date-day');
    const ranges = [...form.querySelectorAll('.activity-date-range')];
    const sync = () => {
        const isRange = mode.value === 'rango';
        day.hidden = isRange;
        day.querySelector('input').disabled = isRange;
        for (const field of ranges) {
            field.hidden = !isRange;
            field.querySelector('input').disabled = !isRange;
            field.querySelector('input').required = isRange;
        }
    };
    mode.addEventListener('change', () => {
        sync();
        if (mode.value === 'dia') form.requestSubmit();
    });
    day.querySelector('input').addEventListener('change', () => {
        if (mode.value === 'dia') form.requestSubmit();
    });
    for (const field of ranges) field.querySelector('input').addEventListener('change', () => {
        const [from, to] = ranges.map(item => item.querySelector('input').value);
        if (mode.value === 'rango' && from && to && from <= to) form.requestSubmit();
    });
    sync();
})();
