const mapa = document.querySelector('[data-admin-refresh]');
if (mapa) {
    let actualizando = false;
    const refrescar = async () => {
        if (actualizando || document.hidden) return;
        actualizando = true;
        try {
            const respuesta = await fetch(mapa.dataset.adminRefresh, {
                headers: {Accept: 'application/json'}, cache: 'no-store',
            });
            if (!respuesta.ok) throw new Error('No se pudo consultar el mapa.');
            const datos = await respuesta.json();
            const estados = new Map(datos.espacios.map(espacio => [String(espacio.id), espacio]));
            mapa.querySelectorAll('[data-space]').forEach(elemento => {
                const estado = estados.get(elemento.dataset.space);
                if (!estado) return;
                elemento.dataset.state = estado.estado_visual;
                elemento.dataset.available = estado.puede_reservar ? '1' : '0';
                elemento.querySelector('[data-state-label]').textContent = estado.estado_texto;
                elemento.setAttribute('aria-label', `${elemento.querySelector('strong').textContent}: ${estado.estado_texto}`);
            });
            mapa.querySelector('[data-map-status]').textContent = `Actualizado: ${new Date().toLocaleTimeString('es-PE')}`;
        } catch (_) {
            mapa.querySelector('[data-map-status]').textContent = 'No se pudo actualizar el mapa. Se reintentará automáticamente.';
        } finally {
            actualizando = false;
        }
    };
    setInterval(refrescar, 10000);
    window.addEventListener('pageshow', evento => { if (evento.persisted) refrescar(); });
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refrescar(); });
}
