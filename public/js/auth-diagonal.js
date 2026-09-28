(() => {
    document.querySelectorAll('.auth-form-area input[type="password"]').forEach(input => {
        const wrap = document.createElement('div');
        wrap.className = 'password-field';
        input.before(wrap);
        wrap.append(input);
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'password-toggle';
        button.textContent = 'Mostrar';
        button.setAttribute('aria-controls', input.id);
        button.setAttribute('aria-label', 'Mostrar ' + (input.labels[0]?.textContent.trim() || 'contraseña'));
        button.setAttribute('aria-pressed', 'false');
        button.addEventListener('click', () => {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.textContent = show ? 'Ocultar' : 'Mostrar';
            button.setAttribute('aria-pressed', String(show));
            button.setAttribute('aria-label', (show ? 'Ocultar ' : 'Mostrar ') + (input.labels[0]?.textContent.trim() || 'contraseña'));
        });
        wrap.append(button);
    });
    document.querySelectorAll('[data-auth-switch]').forEach(link => link.addEventListener('click', event => {
        if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey
            || link.hasAttribute('aria-current') || matchMedia('(prefers-reduced-motion: reduce)').matches
            || matchMedia('(max-width:760px)').matches) return;
        event.preventDefault();
        document.body.classList.add('is-switching');
        setTimeout(() => location.assign(link.href), 260);
    }));
    addEventListener('pageshow', () => document.body.classList.remove('is-switching'));
})();
