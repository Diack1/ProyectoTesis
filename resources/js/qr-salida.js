import jsQR from 'jsqr';

const video = document.getElementById('qr-video');
if (video) {
    const canvas = document.getElementById('qr-canvas');
    const status = document.getElementById('qr-status');
    const camera = document.getElementById('qr-camera');
    const photo = document.getElementById('qr-photo');
    const context = canvas.getContext('2d', { willReadFrequently: true });
    let stream = null, timer = null, busy = false;
    const message = value => { status.textContent = value; };
    const stop = () => { if (timer) clearTimeout(timer); timer = null; stream?.getTracks().forEach(track => track.stop()); stream = null; video.hidden = true; camera.textContent = 'Usar cámara'; };
    const decode = (source, width, height) => {
        const scale = Math.min(1, 1100 / Math.max(width, height));
        canvas.width = Math.max(1, Math.round(width * scale)); canvas.height = Math.max(1, Math.round(height * scale));
        context.drawImage(source, 0, 0, canvas.width, canvas.height);
        const image = context.getImageData(0, 0, canvas.width, canvas.height);
        return jsQR(image.data, image.width, image.height)?.data || null;
    };
    const resolve = async value => {
        let url;
        try { url = new URL(value); } catch { message('El QR no contiene un ticket válido de Parke’o.'); return false; }
        if (!/^\/admin\/estadias\/\d+\/identificar$/.test(url.pathname) || !url.searchParams.has('signature')) {
            message('El QR no corresponde a un ticket de Parke’o.'); return false;
        }
        try {
            const localTicket = new URL(url.pathname + url.search, location.origin);
            const response = await fetch(localTicket.href, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!response.ok) { message('Ticket no encontrado o QR inválido.'); return false; }
            const result = await response.json();
            if (!result.url) { message('No se pudo abrir el ticket.'); return false; }
            stop(); location.assign(result.url); return true;
        } catch { message('No se pudo verificar el QR. Revisa la conexión y vuelve a intentar.'); return false; }
    };
    const scanVideo = async () => {
        if (!stream || busy) return;
        if (video.readyState >= 2) {
            const value = decode(video, video.videoWidth, video.videoHeight);
            if (value) { busy = true; const found = await resolve(value); busy = false; if (found) return; }
        }
        timer = setTimeout(scanVideo, 160);
    };
    camera.addEventListener('click', async () => {
        if (stream) { stop(); message('Cámara detenida.'); return; }
        if (!navigator.mediaDevices?.getUserMedia) { message('La cámara requiere HTTPS o un navegador compatible. Usa una foto o busca el ticket.'); return; }
        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
            video.srcObject = stream; video.hidden = false; await video.play();
            camera.textContent = 'Detener cámara'; message('Apunta al QR del ticket.'); scanVideo();
        } catch { stop(); message('No se pudo abrir la cámara. Revisa el permiso del dispositivo o usa una foto.'); }
    });
    photo.addEventListener('change', async () => {
        const file = photo.files?.[0]; if (!file) return;
        if (!file.type.startsWith('image/') || file.size > 10 * 1024 * 1024) { message('Selecciona una imagen de hasta 10 MB.'); return; }
        stop(); message('Leyendo el QR de la foto…');
        try {
            let value;
            if (typeof createImageBitmap === 'function') {
                const bitmap = await createImageBitmap(file);
                value = decode(bitmap, bitmap.width, bitmap.height); bitmap.close();
            } else {
                const objectUrl = URL.createObjectURL(file);
                try {
                    const picture = new Image(); picture.src = objectUrl; await picture.decode();
                    value = decode(picture, picture.naturalWidth, picture.naturalHeight);
                } finally { URL.revokeObjectURL(objectUrl); }
            }
            if (!value) message('No se detectó un QR legible. Prueba con una foto más nítida.');
            else await resolve(value);
        } catch { message('No se pudo leer la imagen. Selecciona otra foto.'); }
        photo.value = '';
    });
    window.addEventListener('pagehide', stop);
}
