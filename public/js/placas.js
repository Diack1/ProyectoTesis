document.getElementById('form-placas')?.addEventListener('submit', function () {
    this.querySelector('button').disabled = true;
    document.getElementById('estado-analisis').hidden = false;
});
window.addEventListener('pageshow', function (event) {
    if (event.persisted) window.location.reload();
});
