import {PlateTracker} from './plate-tracker.js';

const panel = document.getElementById('camera-panel');
if (panel) {
    const el = id => document.getElementById(id);
    const video = el('camera-video'), devices = el('camera-device'), status = el('camera-status');
    const tracker = new PlateTracker();
    let stream = null, watching = false, busy = false, opening = false, timer = null, generation = 0, request = null;
    const say = text => { status.textContent = text; };
    function buttons() {
        el('camera-close').disabled = !stream && !opening;
        el('camera-photo').disabled = !stream || busy || watching || !!el('camera-photo').dataset.unavailable;
        el('camera-watch').disabled = !stream || busy || watching || !!el('camera-watch').dataset.unavailable;
        el('camera-pause').disabled = !watching;
        el('camera-open').disabled = !!stream || opening;
        devices.disabled = busy || opening;
    }
    function pause(message = null) {
        watching = false;
        clearTimeout(timer);
        generation++;
        request?.abort();
        tracker.reset();
        say(message || (stream ? 'Detección pausada. La vista de cámara sigue encendida.' : 'Detección pausada. Cámara apagada.')); buttons();
    }
    function stop(message = 'Cámara apagada.') {
        opening = false;
        pause(message);
        stream?.getTracks().forEach(track => track.stop());
        stream = null; video.srcObject = null; video.hidden = true; buttons();
    }
    async function open() {
        stop('Solicitando permiso para la cámara…');
        const ticket = ++generation;
        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
            say('Abre el sistema en localhost o HTTPS desde Chrome o Edge para utilizar la cámara.'); return;
        }
        opening = true; buttons();
        try {
            const obtained = await navigator.mediaDevices.getUserMedia({audio: false, video: {
                ...(devices.value ? {deviceId: {exact: devices.value}} : {}),
                width: {ideal: 1920}, height: {ideal: 1080}, frameRate: {ideal: 15, max: 30},
            }});
            if (ticket !== generation) { obtained.getTracks().forEach(t => t.stop()); return; }
            stream = obtained; video.srcObject = stream; video.hidden = false;
            await video.play();
            const track = stream.getVideoTracks()[0];
            track.addEventListener('ended', () => stop('Se desconectó la cámara. Vuelve a conectarla y enciéndela aquí.'));
            const list = await navigator.mediaDevices.enumerateDevices();
            devices.replaceChildren();
            list.filter(d => d.kind === 'videoinput').forEach((d, i) => {
                const option = document.createElement('option'); option.value = d.deviceId;
                option.textContent = d.label || `Cámara ${i + 1}`; devices.append(option);
            });
            devices.value = track.getSettings().deviceId || devices.value;
            say(`Cámara encendida: ${track.label}. Vista de ${video.videoWidth} × ${video.videoHeight}. Coloca una placa frente a la cámara.`);
        } catch (error) {
            if (ticket !== generation) return;
            const messages = {NotAllowedError: 'El permiso de cámara fue rechazado. Permítelo en el navegador y vuelve a intentar.', NotFoundError: 'No se encontró una cámara. Comprueba el cable USB.', NotReadableError: 'No se pudo abrir la cámara. Cierra otras aplicaciones que la estén usando.', OverconstrainedError: 'La cámara seleccionada ya no está disponible. Selecciona otra.'};
            stop(messages[error.name] || 'No se pudo iniciar la cámara. Comprueba su conexión y permisos.');
        } finally { if (ticket === generation) opening = false; buttons(); }
    }
    async function post(url, body, signal) {
        const response = await fetch(url, {method: 'POST', credentials: 'same-origin', signal,
            headers: {'X-CSRF-TOKEN': panel.dataset.csrf, Accept: 'application/json'}, body});
        if (!response.ok) {
            if ([401, 403, 419].includes(response.status)) throw new Error('La sesión terminó o no tienes permiso. Recarga la página e inicia sesión.');
            if (response.status === 429) throw new Error('Se alcanzó el límite de análisis. Espera un minuto y reinicia la detección.');
            const data = await response.json().catch(() => ({}));
            throw new Error(Object.values(data.errors || {}).flat()[0] || 'No se pudo analizar la imagen. Revisa el servidor y vuelve a intentar.');
        }
        return response.json();
    }
    function line(parent, text, tag = 'p') { const item = document.createElement(tag); item.textContent = text; parent.append(item); return item; }
    function link(parent, text, url) {
        if (!url || new URL(url, location.href).origin !== location.origin) return;
        const item = line(parent, text, 'a'); item.href = url; item.className = 'btn btn-secondary';
    }
    function render(candidates, heading) {
        const results = el('camera-results'); results.replaceChildren(); line(results, heading, 'h3');
        if (!candidates.length) { line(results, 'No se detectó una placa. Acerca el vehículo o la imagen de prueba y mejora la iluminación.'); return; }
        candidates.forEach(c => {
            const card = document.createElement('article'); card.className = 'vision-candidate'; results.append(card);
            line(card, c.text || 'Placa sin lectura', 'h3');
            if (c.crop) { const image = document.createElement('img'); image.className = 'vision-crop'; image.src = `data:image/jpeg;base64,${c.crop}`; image.alt = 'Recorte de la placa para revisión'; card.append(image); }
            const match = c.coincidencia;
            if (match?.cliente) {
                line(card, `Cliente asociado: ${match.cliente.nombre}`, 'strong');
                if (match.cliente.telefono) line(card, `Teléfono: ${match.cliente.telefono}`);
                if (match.cliente.email) line(card, `Cuenta web: ${match.cliente.email}`);
            } else line(card, 'Sin cliente asociado a esta placa. Puedes registrarlo en Clientes y vehículos.');
            line(card, `${match?.visitas || 0} visitas finalizadas del vehículo${match?.frecuente ? ' · Vehículo frecuente' : ''}.`);
            line(card, 'Coincidencia por matrícula: comprueba la placa y quién conduce.');
            if (match?.estadia_activa) link(card, `Ya tiene ticket activo: ${match.estadia_activa.ticket}`, match.estadia_activa.url);
            else link(card, 'Revisar y registrar ingreso', match?.ingreso_url);
            for (const reserva of match?.reservas || []) {
                line(card, 'Este vehículo tiene una reserva confirmada', 'h4');
                line(card, `Reserva ${reserva.codigo} · ${reserva.placa} · Espacio ${reserva.espacio}`);
                line(card, `Reservó: ${reserva.cliente} · Llegada prevista: ${reserva.llegada}`);
                line(card, 'Aviso de reserva: el ingreso todavía debe registrarlo el operador.');
                link(card, 'Revisar esta reserva', reserva.url);
            }
        });
    }
    async function capture(automatic = false) {
        if (!stream || busy || !video.videoWidth || (automatic && !watching)) return;
        const ticket = generation;
        busy = true; buttons(); request = new AbortController();
        const timeout = setTimeout(() => request?.abort(), 35000);
        try {
            const canvas = document.createElement('canvas');
            const scale = Math.min(1, 1920 / Math.max(video.videoWidth, video.videoHeight));
            canvas.width = Math.round(video.videoWidth * scale); canvas.height = Math.round(video.videoHeight * scale);
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
            const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.9));
            if (ticket !== generation) return;
            if (!blob) throw new Error('No se pudo capturar la imagen.');
            const body = new FormData(); body.append('imagen', blob, 'camara.jpg');
            say(automatic ? 'Vigilando la entrada: analizando imagen…' : 'Analizando foto capturada…');
            const data = await post(panel.dataset.frameUrl, body, request.signal);
            if (ticket !== generation) return;
            if (automatic) {
                const events = tracker.update(data.candidates, Date.now());
                if (events.length) render(events, `Placa estable detectada · ${new Date().toLocaleTimeString()}`);
                say(events.length ? 'Vehículo detectado. Revisa la ficha; la vigilancia continúa.' : 'Vigilando: esperando una nueva placa con dos lecturas coincidentes.');
            } else {
                render(data.candidates, 'Foto capturada: resultado por revisar');
                say('Fotografía analizada. Puedes corregir la matrícula abajo o capturar otra foto.');
            }
        } catch (error) {
            if (ticket === generation) pause(error.name === 'AbortError' ? 'El análisis no respondió a tiempo. Reinicia la detección.' : error.message);
        } finally {
            clearTimeout(timeout); busy = false; request = null; buttons();
            if (watching && ticket === generation) timer = setTimeout(() => capture(true), 2000);
        }
    }
    el('camera-open').addEventListener('click', open);
    el('camera-close').addEventListener('click', () => stop());
    devices.addEventListener('change', () => { if (stream) open(); });
    el('camera-photo').addEventListener('click', () => capture(false));
    el('camera-watch').addEventListener('click', () => { tracker.reset(); watching = true; buttons(); capture(true); });
    el('camera-pause').addEventListener('click', () => pause());
    el('camera-lookup').addEventListener('submit', async event => {
        event.preventDefault(); pause();
        const ticket = ++generation;
        el('camera-results').replaceChildren();
        const body = new FormData(); body.append('placa', el('camera-corrected').value);
        try { const data = await post(panel.dataset.clientUrl, body); if (ticket !== generation) return; render([{text: data.placa, coincidencia: data.coincidencia}], 'Consulta manual por matrícula'); say('Consulta lista. La detección automática está pausada.'); }
        catch (error) { if (ticket === generation) say(error.message); }
    });
    document.addEventListener('visibilitychange', () => { if (document.hidden) stop('Cámara apagada al ocultar la página. Enciéndela para continuar.'); });
    window.addEventListener('pagehide', () => stop());
    buttons();
}
