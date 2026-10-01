@if(config('services.google.client_id') && config('services.google.client_secret') && config('services.google.redirect'))
<a class="google-login" href="{{ route('google.redirect') }}">Continuar con Google</a>
<p class="text-muted">Para cuentas de clientes · O utiliza tu correo</p>
@endif
