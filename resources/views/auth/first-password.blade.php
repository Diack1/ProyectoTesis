<x-guest-layout>
    <h1>Cambia tu contraseña inicial</h1>
    <p>Para continuar, utiliza una contraseña de al menos 12 caracteres con mayúsculas, minúsculas, números y símbolos.</p>
    @if($errors->any())<div class="errors-box"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="post" action="{{ route('password.first.update') }}">@csrf
        <div class="form-group"><label>Contraseña actual<input type="password" name="current_password" required autocomplete="current-password"></label></div>
        <div class="form-group"><label>Nueva contraseña<input type="password" name="password" required minlength="12" autocomplete="new-password"></label></div>
        <div class="form-group"><label>Confirmar nueva contraseña<input type="password" name="password_confirmation" required minlength="12" autocomplete="new-password"></label></div>
        <button class="btn btn-primary">Cambiar contraseña y continuar</button>
    </form>
    <form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-secondary">Cerrar sesión</button></form>
</x-guest-layout>
