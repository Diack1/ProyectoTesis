const sendButton = document.querySelector('[data-code-send]');
if (sendButton) {
    const status = document.querySelector('[data-code-wait]');
    const until = Date.now() + Number(sendButton.dataset.wait) * 1000;
    const update = () => {
        const seconds = Math.max(0, Math.ceil((until - Date.now()) / 1000));
        sendButton.disabled = sendButton.dataset.ready !== '1' || seconds > 0;
        sendButton.textContent = 'Enviar código a mi correo';
        status.textContent = seconds > 0 ? `Podrás solicitar otro código en ${seconds} segundos. Puedes introducir el código recibido sin esperar.` : '';
        if (!seconds) clearInterval(timer);
    };
    const timer = setInterval(update, 1000);
    update();
    sendButton.form.addEventListener('submit', () => { sendButton.disabled = true; sendButton.textContent = 'Enviando código…'; });
    window.addEventListener('pageshow', update);
}
