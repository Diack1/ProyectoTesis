const keyCheckbox = document.querySelector('[data-entry-key]');
if (keyCheckbox) {
    const locationGroup = document.querySelector('[data-entry-key-location]');
    const locationInput = locationGroup?.querySelector('select, input');
    const refreshKey = () => {
        locationGroup.hidden = !keyCheckbox.checked;
        locationGroup.style.display = keyCheckbox.checked ? '' : 'none';
        if (locationInput) {
            locationInput.required = keyCheckbox.checked;
            if (!keyCheckbox.checked) locationInput.value = '';
        }
    };
    keyCheckbox.addEventListener('change', refreshKey);
    refreshKey();
}

const dni = document.getElementById('entry-dni');
if (dni) {
    const form = document.getElementById('entry-form');
    const plate = document.getElementById('placa');
    const plateGroup = document.getElementById('entry-subscriber-plates');
    const plateSelect = document.getElementById('entry-subscriber-plate');
    const space = document.getElementById('espacio_id');
    const type = document.getElementById('vehiculo_tipo_id');
    const abono = document.getElementById('abono_id');
    const message = document.getElementById('entry-subscriber-message');
    const monthly = document.getElementById('entry-subscriber-rate');
    const release = document.getElementById('entry-release-space');
    const submit = document.getElementById('entry-submit');
    const normalNote = document.querySelector('[data-entry-normal-note]');
    const originalNote = normalNote?.textContent;
    const sensorHelp = document.querySelector('[data-sensor-help]');
    const clientLookup = document.getElementById('entry-client');
    const otherSpace = document.getElementById('entry-subscriber-other-space');
    const fixedSpace = space.matches('[data-fixed-space]');
    const draftKey = 'parkeo-ingreso-abonado';
    let normalValues = null;
    let activeAbono = null;
    let lookupRequest;
    let timer;

    const inform = (text, benefit = false) => {
        message.textContent = text;
        message.hidden = !text;
        message.classList.toggle('is-active', benefit);
    };
    const resetSubscriber = () => {
        if (activeAbono && normalValues) {
            plate.value = normalValues.plate;
            space.value = normalValues.space;
            type.value = normalValues.type;
        }
        activeAbono = null;
        abono.value = '';
        plate.readOnly = false;
        plateGroup.hidden = true;
        monthly.hidden = true;
        release.hidden = true;
        release.dataset.url = '';
        submit.disabled = false;
        if (normalNote) normalNote.textContent = originalNote;
        if (sensorHelp) sensorHelp.hidden = false;
        if (clientLookup) clientLookup.hidden = false;
        if (otherSpace) otherSpace.hidden = true;
        if (!fixedSpace) space.querySelector('[data-dynamic-subscriber]')?.remove();
        abono.dispatchEvent(new Event('change'));
    };
    const selectPlate = () => {
        plate.value = plateSelect.value;
        const duplicate = plateSelect.selectedOptions[0]?.dataset.active === '1';
        const occupied = activeAbono?.espacio_estado !== 'libre';
        submit.disabled = duplicate || occupied;
        release.hidden = !(occupied && activeAbono?.ocupante_placa !== plate.value && release.dataset.url);
        if (duplicate) inform('Esta placa ya tiene un ingreso activo. No puedes registrarla nuevamente.', true);
        else if (occupied && !release.hidden) inform(`El espacio ${activeAbono.espacio_codigo} está ocupado por ${activeAbono.ocupante_placa}. Libéralo antes de registrar.`, true);
        else if (occupied) inform(`El espacio ${activeAbono.espacio_codigo} no está libre. Revisa el estado antes de registrar.`, true);
        else if (activeAbono) inform(`Abono vigente de ${activeAbono.cliente}. Espacio ${activeAbono.espacio_codigo} asignado.`, true);
    };
    plateSelect.addEventListener('change', selectPlate);
    const applySubscriber = data => {
        if (fixedSpace && space.value !== String(data.espacio_id)) {
            resetSubscriber();
            inform(`El espacio habitual del abonado es ${data.espacio_codigo}. Abre ese espacio para registrar su ingreso.`, true);
            if (otherSpace) {
                const url = new URL(dni.dataset.entryUrl, location.href);
                url.searchParams.set('dni', dni.value);
                otherSpace.href = url.href;
                otherSpace.hidden = false;
            }
            submit.disabled = true;
            return;
        }
        if (!activeAbono) normalValues = {plate: plate.value, space: space.value, type: type.value};
        activeAbono = data;
        abono.value = data.abono_id;
        if (!fixedSpace) {
            let selectedSpace = space.querySelector(`option[value="${data.espacio_id}"]`);
            if (!selectedSpace) {
                space.querySelector('[data-dynamic-subscriber]')?.remove();
                const option = new Option(`${data.espacio_codigo} · asignado al abonado`, data.espacio_id);
                option.dataset.dynamicSubscriber = '1';
                space.add(option);
                selectedSpace = option;
            }
            selectedSpace.dataset.types = data.espacio_tipos.join(',');
            selectedSpace.dataset.mode = data.espacio_modo;
            space.value = String(data.espacio_id);
            space.dispatchEvent(new Event('change'));
        }
        if (data.vehiculo_tipo_id) type.value = String(data.vehiculo_tipo_id);
        type.dispatchEvent(new Event('change'));
        plateSelect.replaceChildren();
        data.placas.forEach(value => {
            const option = new Option(value, value);
            option.dataset.active = data.placas_activas.includes(value) ? '1' : '0';
            plateSelect.add(option);
        });
        const previousPlate = plate.value.toUpperCase().replace(/[\s-]+/g, '');
        release.dataset.url = data.mover_url || '';
        plateSelect.value = data.placas.includes(previousPlate) ? previousPlate :
            (data.placas.find(value => !data.placas_activas.includes(value)) || data.placas[0]);
        plateGroup.hidden = data.placas.length <= 1;
        plate.readOnly = true;
        selectPlate();
        monthly.textContent = `Tarifa mensual: ${data.tarifa_mensual}. Vigente hasta ${data.vence}. Entradas y salidas cubiertas.`;
        monthly.hidden = false;
        if (normalNote) normalNote.textContent = 'Ingreso cubierto por la mensualidad vigente.';
        if (sensorHelp) sensorHelp.hidden = true;
        if (clientLookup) clientLookup.hidden = true;
        if (otherSpace) otherSpace.hidden = true;
        abono.dispatchEvent(new Event('change'));
    };
    const lookup = async () => {
        lookupRequest?.abort();
        const value = dni.value.replace(/\D/g, '').slice(0, 8);
        dni.value = value;
        if (value.length !== 8) { resetSubscriber(); inform(''); return; }
        lookupRequest = new AbortController();
        const current = lookupRequest;
        submit.disabled = true;
        inform('Buscando abono…');
        try {
            const response = await fetch(`${dni.dataset.subscriberUrl}?dni=${encodeURIComponent(value)}`,
                {headers: {Accept: 'application/json'}, cache: 'no-store', signal: current.signal});
            if (!response.ok) throw new Error('No se pudo consultar el abono. Intenta nuevamente.');
            const data = await response.json();
            if (current !== lookupRequest || dni.value !== value) return;
            if (data.vigente) applySubscriber(data);
            else { resetSubscriber(); inform(data.mensaje); }
        } catch (error) {
            if (error.name !== 'AbortError') inform(error.message);
        }
    };
    dni.addEventListener('input', () => {
        if (activeAbono) { resetSubscriber(); inform(''); }
        clearTimeout(timer);
        timer = setTimeout(lookup, 300);
    });
    dni.addEventListener('change', lookup);
    dni.addEventListener('blur', () => { if (dni.value.length === 8 && !activeAbono) lookup(); });
    setInterval(() => { if (activeAbono && !document.hidden) lookup(); }, 10000);
    release.addEventListener('click', () => {
        if (!release.dataset.url) return;
        const values = Object.fromEntries(new FormData(form).entries());
        sessionStorage.setItem(draftKey, JSON.stringify(values));
        window.location.assign(release.dataset.url);
    });
    if (new URLSearchParams(location.search).has('reubicado')) {
        try {
            const saved = JSON.parse(sessionStorage.getItem(draftKey) || 'null');
            sessionStorage.removeItem(draftKey);
            if (saved) {
                for (const [name, value] of Object.entries(saved)) {
                    const field = form.elements.namedItem(name);
                    if (!field || name === '_token') continue;
                    if (field.type === 'checkbox') field.checked = value === field.value;
                    else field.value = value;
                }
                keyCheckbox?.dispatchEvent(new Event('change'));
            }
        } catch (_) { sessionStorage.removeItem(draftKey); }
    }
    if (dni.value.length === 8) lookup();
}
