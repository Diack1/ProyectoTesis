const receiptModal = document.getElementById('sale-receipt-modal');
if (receiptModal) {
    const content = document.getElementById('sale-receipt-content');
    const close = () => { receiptModal.hidden = true; content.replaceChildren(); document.body.classList.remove('cash-modal-open'); };
    document.querySelectorAll('[data-sale-url]').forEach(button => button.addEventListener('click', async () => {
        content.textContent = 'Cargando comprobante…'; receiptModal.hidden = false; document.body.classList.add('cash-modal-open');
        const response = await fetch(button.dataset.saleUrl, { headers: { Accept: 'text/html' } });
        content.innerHTML = response.ok ? await response.text() : 'No se pudo cargar el comprobante.';
    }));
    receiptModal.addEventListener('click', event => { if (event.target === receiptModal || event.target.closest('[data-close-sale]')) close(); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && !receiptModal.hidden) close(); });
}
