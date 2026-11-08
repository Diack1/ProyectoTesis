const entryRate = document.querySelector('[data-entry-rate]');
if (entryRate) {
    const entryType = document.querySelector('[data-entry-type]') || document.getElementById('entry-type');
    const abonado = document.getElementById('abono_id');
    const group = entryRate.closest('[data-entry-rate-group]');
    const refreshRates = () => {
        const isSubscriber = Boolean(abonado?.value);
        group.hidden = isSubscriber;
        group.style.display = isSubscriber ? 'none' : '';
        entryRate.required = !isSubscriber;
        if (isSubscriber) { entryRate.value = ''; return; }
        for (const option of entryRate.options) {
            if (!option.value) continue;
            option.hidden = option.disabled = option.dataset.type !== entryType?.value;
        }
        if (entryRate.selectedOptions[0]?.disabled) entryRate.value = '';
    };
    entryType?.addEventListener('change', refreshRates);
    abonado?.addEventListener('change', refreshRates);
    refreshRates();
}
