document.querySelectorAll('[data-parking]').forEach(widget => {
    const dialog = widget.querySelector('dialog'), content = widget.querySelector('[data-dialog-content]');
    let selected = null, busy = false, refreshController = null, refreshTimer = null, suspended = false;
    let quoteReady = false, quoteController = null;
    const vehicle = document.getElementById('quote-vehicle'), duration = document.getElementById('quote-duration');
    const initialQuotes = JSON.parse(widget.querySelector('[data-initial-quotes]')?.textContent || '{}');
    const desktopPanel = matchMedia('(min-width:1000px)');
    const openDetail = () => { if (widget.dataset.refresh && desktopPanel.matches) dialog.show(); else dialog.showModal(); };
    desktopPanel.addEventListener('change', () => { if (dialog.open) { dialog.close(); openDetail(); } });
    const preferences = dialog.querySelector('.booking-preferences');
    const loadQuote = async () => {
        quoteController?.abort();
        refreshController?.abort();
        quoteReady = false;
        if (!selected || !widget.dataset.refresh) return;
        const box = content.querySelector('[data-quote-url]');
        box.querySelector('[data-quote-retry]').hidden = true;
        const action = content.querySelector('[data-reserve-action]');
        action.hidden = true;
        box.querySelector('[data-quote-total]').textContent = '';
        box.querySelector('[data-quote-note]').textContent = '';
        if (!vehicle?.value) { box.querySelector('[data-quote-message]').textContent = 'Selecciona tu vehículo para consultar el precio.'; return; }
        const allowed = (box.dataset.allowedTypes || '').split(',').includes(vehicle.value);
        const preview = allowed ? initialQuotes[vehicle.value]?.[duration.value] : null;
        box.querySelector('[data-quote-message]').textContent = preview?.precio_unitario || 'Consultando tarifa vigente…';
        if (preview) {
            action.href = selected.getAttribute('href') + '?' + new URLSearchParams({vehiculo_tipo_id:vehicle.value,duracion_minutos:duration.value});
            quoteReady = true;
            action.textContent = 'Continuar y verificar espacio';
            updateDetail();
            box.querySelector('[data-quote-total]').textContent = 'Total estimado: S/ ' + preview.total;
            box.querySelector('[data-quote-note]').textContent = 'Tarifa al abrir la página. Estamos verificando el precio y la disponibilidad actuales.';
        }
        const controller = new AbortController(); quoteController = controller;
        const timeout = setTimeout(() => controller.abort(), 20000);
        try {
            const params = new URLSearchParams({vehiculo_tipo_id:vehicle.value,duracion_minutos:duration.value});
            const response = await fetch(box.dataset.quoteUrl+'?'+params, {headers:{Accept:'application/json'},signal:controller.signal,cache:'no-store'});
            if (!response.headers.get('content-type')?.includes('application/json')) {
                throw new Error('La conexión devolvió una página de acceso en lugar de la tarifa. Si usas un túnel, vuelve a abrir su enlace e inicia sesión; después reintenta.');
            }
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'No se pudo consultar el precio.');
            if (quoteController !== controller || !dialog.open) return;
            box.querySelector('[data-quote-message]').textContent = data.precio_unitario;
            box.querySelector('[data-quote-total]').textContent = 'Total: S/ '+data.total;
            box.querySelector('[data-quote-note]').textContent = data.detalle_precio;
            action.href = data.continuar;
            action.textContent = 'Reservar este espacio';
            quoteReady = data.puede_reservar;
            selected.dataset.available = data.puede_reservar ? '1' : '0';
            if (data.puede_reservar) {
                selected.dataset.state = 'libre';
                selected.querySelector('[data-state-label]').textContent = 'Libre';
            }
            updateDetail();
        } catch (error) {
            if (quoteController === controller && dialog.open) {
                if (preview) {
                    box.querySelector('[data-quote-note]').textContent = 'Precio estimado al abrir la página. Puedes continuar: el servidor verificará el espacio y recalculará el precio antes de confirmar.';
                } else box.querySelector('[data-quote-message]').textContent = error.name === 'AbortError' ? 'La consulta tardó demasiado. Comprueba la conexión y vuelve a intentarlo.' : error.message;
                box.querySelector('[data-quote-retry]').hidden = false;
            }
        } finally { clearTimeout(timeout); }
    };
    vehicle?.addEventListener('change', () => { if (dialog.open) loadQuote(); });
    duration?.addEventListener('change', () => { if (dialog.open) loadQuote(); });
    const updateDetail = () => {
        if (!selected) return;
        content.querySelector('[data-detail-state]').textContent = selected.querySelector('[data-state-label]').textContent;
        const action = content.querySelector('[data-reserve-action]');
        if (action) {
            action.hidden = (selected.dataset.available !== '1' && selected.dataset.state !== 'sin_senal') || !quoteReady;
            const unavailable = content.querySelector('[data-unavailable]');
            unavailable.hidden = selected.dataset.available === '1';
            unavailable.textContent = selected.dataset.state === 'sin_senal'
                ? 'Disponibilidad pendiente de verificar. Continuar no confirma ni cobra la reserva.'
                : 'Este espacio no está disponible. Elige otro espacio libre.';
        }
    };
    widget.querySelector('[data-map-toggle]')?.addEventListener('click', event => {
        const active = widget.classList.toggle('is-list');
        event.currentTarget.textContent = active ? 'Ver plano 2D' : 'Ver en lista';
        event.currentTarget.setAttribute('aria-pressed', String(active));
    });
    widget.addEventListener('click', event => {
        if (event.target.closest('[data-quote-retry]')) { loadQuote(); return; }
        const button = event.target.closest('[data-space]');
        if (!button) return;
        if (event.ctrlKey || event.metaKey || event.shiftKey) return;
        if (typeof dialog.showModal !== 'function') return;
        event.preventDefault();
        widget.querySelectorAll('[data-space]').forEach(item => item.classList.toggle('space-selected', item === button));
        selected = button;
        content.replaceChildren(widget.querySelector(`[data-space-detail="${button.dataset.space}"]`).content.cloneNode(true));
        if (preferences) content.querySelector('h2').after(preferences);
        quoteReady = false; updateDetail(); if (!dialog.open) openDetail(); dialog.scrollTop = 0; loadQuote();
    });
    dialog.addEventListener('close', () => { quoteController?.abort(); selected?.focus(); });
    if (widget.dataset.refresh) {
        const refresh = async () => {
            if (busy || document.hidden || dialog.open) return;
            busy = true;
            const controller = new AbortController(); refreshController = controller;
            const timeout = setTimeout(() => controller.abort(), 20000);
            try {
                const response = await fetch(widget.dataset.refresh, {headers: {Accept: 'application/json'}, cache: 'no-store', signal: controller.signal});
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
                if (dialog.open) return;
                widget.querySelector('[data-map-status]').textContent = 'No se pudo actualizar. Reservas pausadas hasta recuperar la conexión.';
                widget.querySelectorAll('[data-space]').forEach(b => {b.dataset.available = '0'; b.dataset.state = 'sin_senal'; b.querySelector('small').textContent = 'Sin actualizar';});
            } finally { clearTimeout(timeout); busy = false; updateDetail(); }
        };
        // Wait for the previous request before scheduling another on slow connections.
        const schedule = async () => { await refresh(); if (!suspended) refreshTimer = setTimeout(schedule, 15000); };
        refreshTimer = setTimeout(schedule, 10000);
        window.addEventListener('pagehide', () => { suspended = true; clearTimeout(refreshTimer); refreshController?.abort(); quoteController?.abort(); });
        window.addEventListener('pageshow', event => { if (event.persisted) { suspended = false; clearTimeout(refreshTimer); refreshTimer = setTimeout(schedule, 1000); } });
        document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
    }
});
