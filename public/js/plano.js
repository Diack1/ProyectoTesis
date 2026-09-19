document.querySelectorAll('[data-parking]').forEach(widget => {
    const dialog = widget.querySelector('dialog'), content = widget.querySelector('[data-dialog-content]');
    let selected = null, busy = false;
    const updateDetail = () => {
        if (!selected) return;
        content.querySelector('[data-detail-state]').textContent = selected.querySelector('[data-state-label]').textContent;
        const action = content.querySelector('[data-reserve-action]');
        if (action) { action.hidden = selected.dataset.available !== '1'; content.querySelector('[data-unavailable]').hidden = !action.hidden; }
    };
    widget.querySelector('[data-map-toggle]').addEventListener('click', event => {
        const active = widget.classList.toggle('is-list');
        event.currentTarget.textContent = active ? 'Ver plano 2D' : 'Ver en lista';
        event.currentTarget.setAttribute('aria-pressed', String(active));
    });
    widget.addEventListener('click', event => {
        const button = event.target.closest('[data-space]');
        if (!button) return;
        selected = button;
        content.replaceChildren(widget.querySelector(`[data-space-detail="${button.dataset.space}"]`).content.cloneNode(true));
        updateDetail(); dialog.showModal();
    });
    dialog.addEventListener('close', () => selected?.focus());
    if (widget.dataset.refresh) {
        const refresh = async () => {
            if (busy || document.hidden) return;
            busy = true;
            try {
                const response = await fetch(widget.dataset.refresh, {headers: {Accept: 'application/json'}, cache: 'no-store', signal: AbortSignal.timeout(10000)});
                if (!response.ok) throw new Error();
                const data = await response.json();
                const states = new Map(data.espacios.map(s => [String(s.id), s]));
                widget.querySelectorAll('[data-space]').forEach(button => {
                    const state = states.get(button.dataset.space);
                    button.dataset.state = state?.estado_visual || 'sin_senal';
                    button.dataset.available = state?.puede_reservar ? '1' : '0';
                    button.querySelector('[data-state-label]').textContent = state?.estado_texto || 'No disponible';
                    button.setAttribute('aria-label', `${button.querySelector('strong').textContent}: ${button.querySelector('small').textContent}`);
                });
                widget.querySelector('[data-map-status]').textContent = `Actualizado: ${new Date().toLocaleTimeString('es-PE')}`;
            } catch (_) {
                widget.querySelector('[data-map-status]').textContent = 'No se pudo actualizar. Reservas pausadas hasta recuperar la conexión.';
                widget.querySelectorAll('[data-space]').forEach(b => {b.dataset.available = '0'; b.dataset.state = 'sin_senal'; b.querySelector('small').textContent = 'Sin actualizar';});
            } finally { busy = false; updateDetail(); }
        };
        setInterval(refresh, 10000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
    }
});
