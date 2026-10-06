document.addEventListener('DOMContentLoaded', () => {
    const mobile = matchMedia('(max-width: 760px)');
    const records = [];
    document.querySelectorAll('[data-mobile-details]').forEach(table => {
        const columns = table.dataset.mobileDetails.split(',').map(Number);
        table.querySelectorAll('tbody tr').forEach(row => {
            const cells = [...row.cells];
            if (cells.length < 2) return;
            const cell = document.createElement('td');
            cell.className = 'record-disclosure';
            cell.colSpan = cells.length;
            const details = document.createElement('details');
            const summary = document.createElement('summary');
            summary.textContent = 'Ver detalles';
            details.append(summary);
            cell.append(details);
            const fields = columns.map(index => ({cell: cells[index], slot: document.createElement('div')})).filter(field => field.cell);
            fields.forEach(field => {
                field.slot.className = 'record-detail-field';
                const label = document.createElement('strong');
                label.textContent = field.cell.dataset.label;
                field.slot.append(label);
                details.append(field.slot);
            });
            records.push({row, cell, details, fields});
        });
    });
    const update = () => {
        records.forEach(record => {
            if (mobile.matches) {
                record.fields.forEach(field => {
                    while (field.cell.firstChild) field.slot.append(field.cell.firstChild);
                    field.cell.hidden = true;
                });
                record.row.append(record.cell);
            } else {
                record.fields.forEach(field => {
                    while (field.slot.childNodes.length > 1) field.cell.append(field.slot.childNodes[1]);
                    field.cell.hidden = false;
                });
                record.cell.remove();
            }
        });
        document.querySelectorAll('[data-mobile-filter]').forEach(details => {
            details.open = !mobile.matches || details.dataset.filtered === 'true';
        });
    };
    update();
    mobile.addEventListener('change', update);
});
