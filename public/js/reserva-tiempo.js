(() => {
    const panel = document.querySelector('[data-stay-clock]');
    if (!panel) return;
    let seconds = 0, running = false, synced = performance.now(), pending = false;
    const render = () => {
        const value = seconds + (running ? Math.floor((performance.now() - synced) / 1000) : 0);
        panel.querySelector('[data-clock-time]').textContent = [Math.floor(value / 3600), Math.floor(value / 60) % 60, value % 60].map(n => String(n).padStart(2, '0')).join(':');
    };
    const update = async () => {
        if (pending) return;
        pending = true;
        try {
            const response = await fetch(panel.dataset.url, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!response.ok) throw new Error('unavailable');
            const data = await response.json();
            seconds = data.segundos; running = data.en_curso; synced = performance.now();
            panel.querySelector('[data-clock-status]').textContent = data.estado;
            panel.querySelector('[data-clock-extra]').textContent = 'S/ ' + (data.extra_centavos / 100).toFixed(2);
            render();
        } catch {
            running = false;
            panel.querySelector('[data-clock-status]').textContent = 'No se pudo actualizar. Reconectando; el tiempo real se conserva en el servidor.';
            panel.querySelector('[data-clock-extra]').textContent = 'Pendiente de actualizar';
        } finally { pending = false; }
    };
    update(); setInterval(render, 1000); setInterval(update, 15000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) update(); });
})();
