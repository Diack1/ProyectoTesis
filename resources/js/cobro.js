const root = document.getElementById('checkout');
if (root) {
    const $ = id => document.getElementById(id);
    let base = Number(root.dataset.base);
    const payments = JSON.parse(root.dataset.initialPayments || '[]');
    let client = JSON.parse(root.dataset.client || 'null');
    let method = 'efectivo';
    let editing = -1;
    const draftKey = `parkeo-cobro:${root.dataset.ticket}`;
    const saveDraft = () => {
        try { sessionStorage.setItem(draftKey, JSON.stringify({ payments, client,
            percent: $('discount-percent').value, amount: $('discount-amount').value,
            reason: $('discount-reason').value, receipt: $('receipt-type').value,
            print: $('print-type').value, base, tariff: $('correction-rate')?.value,
            correctionReason: $('correction-reason')?.value })); } catch (_) { /* El cobro sigue disponible si el navegador bloquea el almacenamiento. */ }
    };
    const money = cents => `S/ ${(cents / 100).toFixed(2)}`;
    const cents = value => Math.round(Number(value || 0) * 100);
    const labels = { efectivo: 'Efectivo', tarjeta: 'Tarjeta', yape: 'Yape', plin: 'Plin', manual: 'Otros' };
    const error = (id, message) => { $(id).textContent = message; $(id).hidden = !message; };
    const show = id => {
        const leavingPreview = !$('preview-panel').hidden && id !== 'preview-panel';
        ['checkout-main', 'client-panel', 'payment-panel', 'preview-panel'].forEach(panel => { $(panel).hidden = panel !== id; });
        document.body.classList.toggle('receipt-open', id === 'preview-panel');
        document.body.classList.toggle('amount-modal-open', id === 'payment-panel');
        if (id === 'preview-panel') $('submit-payment').focus();
        if (id === 'payment-panel') $('payment-amount').focus();
        if (leavingPreview) $('preview-payment').focus();
    };
    const discount = () => Math.round(base * cents($('discount-percent').value) / 10000) + cents($('discount-amount').value);
    const total = () => Math.max(0, base - discount());
    const paid = () => payments.reduce((sum, payment) => sum + payment.amount, 0);
    const pending = () => Math.max(0, total() - paid());
    const change = () => payments.reduce((sum, payment) => sum + (payment.received || 0) - (payment.method === 'efectivo' ? payment.amount : 0), 0);
    const redraw = () => {
        $('base-label').textContent = money(base);
        $('discount-label').textContent = money(discount());
        $('total-label').textContent = money(total());
        $('paid-label').textContent = money(paid());
        $('pending-label').textContent = money(pending());
        $('change-label').textContent = money(change());
        $('paid-row').hidden = payments.length === 0;
        $('pending-row').hidden = payments.length === 0 || pending() === 0;
        $('change-row').hidden = change() <= 0;
        if (root.dataset.kind === 'correccion') {
            $('correction-base').value = (base / 100).toFixed(2);
            $('correction-total').value = (total() / 100).toFixed(2);
        }
        $('payments-list').replaceChildren();
        if (!payments.length) $('payments-list').textContent = 'Ninguno todavía';
        payments.forEach((payment, index) => {
            const row = document.createElement('div'); row.className = 'payment-row';
            const summary = document.createElement('span');
            summary.textContent = `${labels[payment.method]} · ${money(payment.amount)}${payment.method === 'efectivo' ? ` · recibido ${money(payment.received)}` : ''}`;
            const edit = document.createElement('button'); edit.type = 'button'; edit.textContent = 'Corregir'; edit.onclick = () => openPayment(payment.method, index);
            const remove = document.createElement('button'); remove.type = 'button'; remove.textContent = 'Eliminar'; remove.onclick = () => { payments.splice(index, 1); redraw(); };
            row.append(summary, edit, remove); $('payments-list').append(row);
        });
        saveDraft();
    };
    ['discount-percent', 'discount-amount'].forEach(id => $(id).addEventListener('input', () => { redraw(); error('checkout-error', ''); }));
    ['discount-reason', 'receipt-type', 'print-type'].forEach(id => $(id).addEventListener('input', saveDraft));
    if (root.dataset.kind === 'correccion') {
        $('client-id').value = client?.id || '';
        if (client) {
            ['nombres', 'apellidos', 'celular', 'numero_documento'].forEach(name => {
                const field = $('client-form').elements.namedItem(name);
                if (field) field.value = client[name] || '';
            });
            if (client.tipo_documento === 'RUC') { $('client-type').value = 'RUC'; $('client-document').maxLength = 11; $('client-document').pattern = '[0-9]{11}'; }
        }
        $('correction-reason').addEventListener('input', saveDraft);
        $('correction-base-input')?.addEventListener('input', event => { base = cents(event.target.value); redraw(); error('checkout-error', ''); });
        $('correction-rate')?.addEventListener('input', event => { root.dataset.tarifa = event.target.value; $('checkout-tariff-label').textContent = event.target.value; saveDraft(); });
    }
    $('open-client').onclick = () => show('client-panel');
    document.querySelectorAll('[data-back]').forEach(button => button.onclick = () => show('checkout-main'));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !$('preview-panel').hidden) show('checkout-main');
    });
    $('client-type').onchange = () => { const length = $('client-type').value === 'RUC' ? 11 : 8; $('client-document').maxLength = length; $('client-document').pattern = `[0-9]{${length}}`; $('client-document').value = ''; };
    [$('client-document'), ...document.querySelectorAll('#client-form [name=celular]')].forEach(input => input.addEventListener('input', () => { input.value = input.value.replace(/\D/g, ''); }));
    $('client-form').onsubmit = async event => {
        event.preventDefault(); error('client-error', '');
        const form = event.currentTarget;
        try {
            const response = await fetch(form.action, { method: 'POST', headers: { Accept: 'application/json' }, body: new FormData(form) });
            const result = await response.json();
            if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || 'No se pudo guardar el cliente.');
            client = result; $('client-id').value = result.id;
            $('client-summary').replaceChildren();
            const title = document.createElement('strong'); title.textContent = 'Cliente';
            const details = document.createElement('span'); details.textContent = `${result.nombres} ${result.apellidos} · ${result.tipo_documento} ${result.numero_documento}${result.celular ? ` · ${result.celular}` : ''}`;
            $('client-summary').append(title, details); saveDraft(); show('checkout-main');
        } catch (e) { error('client-error', e.message); }
    };
    function openPayment(selected, index = -1) {
        method = selected; editing = index;
        const current = index < 0 ? null : payments[index];
        $('payment-title').textContent = `${index < 0 ? 'Añadir' : 'Corregir'} pago · ${labels[method]}`;
        $('calculator-pending').textContent = money(pending() + (current?.amount || 0));
        $('payment-amount-kind').textContent = method === 'efectivo' ? 'recibido' : 'aplicado';
        $('payment-amount').value = ((method === 'efectivo' ? current?.received : current?.amount) ?? (pending() + (current?.amount || 0))) / 100;
        $('payment-amount').dataset.replaceOnKey = '1';
        $('cash-keypad').hidden = false;
        error('payment-error', ''); show('payment-panel');
    }
    document.querySelectorAll('[data-pay]').forEach(button => button.onclick = () => openPayment(button.dataset.pay));
    $('payment-amount').addEventListener('input', () => { $('payment-amount').dataset.replaceOnKey = '0'; });
    document.querySelectorAll('[data-key]').forEach(button => button.onclick = () => {
        const input = $('payment-amount');
        const key = button.dataset.key;
        input.value = input.dataset.replaceOnKey === '1' ? (key === '.' ? '0.' : key) : `${input.value}${key}`;
        input.dataset.replaceOnKey = '0';
    });
    document.querySelectorAll('[data-quick]').forEach(button => button.onclick = () => { $('payment-amount').value = ((cents($('payment-amount').value) + Number(button.dataset.quick) * 100) / 100).toFixed(2); });
    document.querySelector('[data-clear]').onclick = () => { $('payment-amount').value = ''; $('payment-amount').dataset.replaceOnKey = '0'; };
    document.querySelector('[data-exact]').onclick = () => { $('payment-amount').value = ((pending() + (editing < 0 ? 0 : payments[editing].amount)) / 100).toFixed(2); $('payment-amount').dataset.replaceOnKey = '1'; };
    $('confirm-payment').onclick = () => {
        const available = pending() + (editing < 0 ? 0 : payments[editing].amount);
        const entered = cents($('payment-amount').value);
        const amount = method === 'efectivo' ? Math.min(entered, available) : entered;
        if (!Number.isFinite(entered) || entered <= 0 || amount > available || amount <= 0) return error('payment-error', 'El importe debe ser positivo y no superar el saldo pendiente.');
        const payment = { method, amount, received: method === 'efectivo' ? entered : null };
        if (editing < 0) payments.push(payment); else payments[editing] = payment;
        redraw(); show('checkout-main');
    };
    const submitPayment = () => {
        $('submit-payment').disabled = true;
        $('preview-payment').disabled = true;
        $('payment-fields').replaceChildren();
        payments.forEach((payment, index) => Object.entries({ metodo_pago: payment.method, monto: (payment.amount / 100).toFixed(2), efectivo_recibido: payment.received === null ? '' : (payment.received / 100).toFixed(2) }).forEach(([name, value]) => { const field = document.createElement('input'); field.type = 'hidden'; field.name = `pagos[${index}][${name}]`; field.value = value; $('payment-fields').append(field); }));
        $('checkout-form').submit();
    };
    $('preview-payment').onclick = () => {
        if (root.dataset.kind === 'correccion' && $('correction-reason').value.trim().length < 5) return error('checkout-error', 'Indica un motivo de al menos 5 caracteres para la corrección.');
        if (discount() < 0 || discount() > base) return error('checkout-error', 'El descuento supera el importe.');
        if (discount() > 0 && !$('discount-reason').value.trim()) return error('checkout-error', 'Indica el motivo del descuento.');
        if (paid() > total()) return error('checkout-error', 'Los pagos agregados superan el total. Corrígelos antes de continuar.');
        if (pending()) return error('checkout-error', 'No se puede finalizar con saldo pendiente.');
        if ($('receipt-type').value === 'factura' && client?.tipo_documento !== 'RUC') return error('checkout-error', 'La factura requiere un cliente con RUC.');
        const verification = $('checkout-form').querySelector('[name=pago_verificado]');
        if (verification && !verification.checked && total() > 0) return error('checkout-error', 'Confirma que recibiste y verificaste los pagos.');
        if (root.dataset.kind !== 'correccion') {
            error('checkout-error', '');
            submitPayment();
            return;
        }
        if (root.dataset.kind === 'correccion' && root.dataset.paymentOnly === '0') {
            error('checkout-error', '');
            submitPayment();
            return;
        }
        const details = [
            ['Tipo', $('receipt-type').selectedOptions[0].textContent],
            ['Impresión', $('print-type').selectedOptions[0].textContent],
            ['Cliente', client ? `${client.nombres} ${client.apellidos}` : 'Cliente general'],
            ['Documento', client ? `${client.tipo_documento} ${client.numero_documento}` : ''],
            ['Placa', root.dataset.placa], ['Vehículo', root.dataset.vehiculo],
            ['Espacio', root.dataset.espacio],
            [root.dataset.kind === 'abono' ? 'Inicio' : 'Entrada', root.dataset.ingreso],
            [root.dataset.kind === 'abono' ? 'Vencimiento' : 'Salida', root.dataset.salida],
            [root.dataset.kind === 'abono' ? 'Periodo' : 'Tiempo utilizado', root.dataset.tiempo],
            ['Tarifa', root.dataset.tarifa], ['Cajero', root.dataset.cajero],
        ];
        $('receipt-details').replaceChildren();
        details.filter(([, value]) => value).forEach(([label, value]) => {
            const row = document.createElement('div');
            const name = document.createElement('dt'); name.textContent = label;
            const detail = document.createElement('dd'); detail.textContent = value;
            row.append(name, detail); $('receipt-details').append(row);
        });
        $('receipt-date').textContent = new Date().toLocaleString('es-PE', { dateStyle: 'short', timeStyle: 'short', timeZone: 'America/Lima' });
        $('receipt-payments-list').replaceChildren();
        payments.forEach(payment => {
            const row = document.createElement('div');
            const name = document.createElement('span'); name.textContent = labels[payment.method];
            const amount = document.createElement('strong'); amount.textContent = money(payment.amount);
            row.append(name, amount); $('receipt-payments-list').append(row);
        });
        $('receipt-base').textContent = money(base);
        $('receipt-discount-row').hidden = discount() === 0;
        $('receipt-discount').textContent = `${money(discount())} · ${$('discount-reason').value.trim()}`;
        $('receipt-total').textContent = money(total());
        $('receipt-received').textContent = money(payments.reduce((sum, payment) => sum + (payment.method === 'efectivo' ? payment.received : payment.amount), 0));
        $('receipt-change-row').hidden = change() <= 0;
        $('receipt-change').textContent = money(change());
        if (root.dataset.kind === 'correccion') $('receipt-correction-reason').textContent = $('correction-reason').value.trim();
        error('checkout-error', ''); show('preview-panel');
    };
    $('submit-payment').onclick = submitPayment;
    try {
        const draft = JSON.parse(sessionStorage.getItem(draftKey) || 'null');
        if (draft) {
            if (root.dataset.kind === 'correccion' && Number.isInteger(draft.base) && draft.base >= 0) {
                base = draft.base;
                if ($('correction-base-input')) $('correction-base-input').value = (base / 100).toFixed(2);
            }
            $('discount-percent').value = draft.percent || '0'; $('discount-amount').value = draft.amount || '0';
            $('discount-reason').value = draft.reason || ''; $('receipt-type').value = draft.receipt || 'boleta';
            $('print-type').value = draft.print || 'detallado';
            if (Array.isArray(draft.payments)) payments.splice(0, payments.length, ...draft.payments.filter(p => p && Number.isInteger(p.amount) && p.amount > 0));
            if (root.dataset.kind === 'correccion') {
                if ($('correction-rate') && draft.tariff !== undefined) { $('correction-rate').value = draft.tariff; root.dataset.tarifa = draft.tariff; $('checkout-tariff-label').textContent = draft.tariff; }
                if (draft.correctionReason) $('correction-reason').value = draft.correctionReason;
            }
            if (draft.client?.id) {
                client = draft.client; $('client-id').value = client.id;
                const title = document.createElement('strong'); title.textContent = 'Cliente';
                const details = document.createElement('span'); details.textContent = `${client.nombres} ${client.apellidos} · ${client.tipo_documento} ${client.numero_documento}`;
                $('client-summary').replaceChildren(title, details);
            }
        }
    } catch (_) { /* Ignorar un borrador ilegible. */ }
    redraw();
    if (root.dataset.kind === 'estadia') setInterval(async () => {
        if (document.hidden || !$('preview-panel').hidden || !$('payment-panel').hidden) return;
        try {
            const response = await fetch(window.location.href, { cache: 'no-store', headers: { Accept: 'text/html' } });
            if (!response.ok) return;
            const updated = new DOMParser().parseFromString(await response.text(), 'text/html').getElementById('checkout');
            const quote = updated?.querySelector('[name=cotizacion]')?.value;
            if (!quote || updated.dataset.ticket !== root.dataset.ticket) return;
            base = Number(updated.dataset.base);
            root.dataset.base = updated.dataset.base;
            root.dataset.tiempo = updated.dataset.tiempo;
            root.dataset.salida = updated.dataset.salida;
            $('checkout-form').querySelector('[name=cotizacion]').value = quote;
            const currentFacts = root.querySelector('.checkout-detail .checkout-facts:not(#client-summary)');
            const refreshedFacts = updated.querySelector('.checkout-detail .checkout-facts:not(#client-summary)');
            if (currentFacts && refreshedFacts) {
                currentFacts.querySelectorAll('span').forEach((line, index) => {
                    line.textContent = refreshedFacts.querySelectorAll('span')[index]?.textContent || '';
                });
            }
            redraw();
        } catch (_) { /* Conserva la cotización visible si la conexión falla. */ }
    }, 60000);
}
