@extends('layouts.auth')
@section('title', 'Espera un momento - Parke’o')
@section('content')
<div class="auth-card">
    <h1>Espera un momento</h1>
    <p role="alert">Se realizaron varios intentos seguidos. Podrás volver a intentarlo en {{ $seconds }} segundos.</p>
    <p>Google está disponible para clientes. Si eres personal o propietario, utiliza tu correo y contraseña de Parke’o.</p>
    <a class="btn-auth" href="{{ route('login') }}">Volver a iniciar sesión</a>
</div>
@endsection
