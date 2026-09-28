@extends('layouts.auth')
@section('title', 'Espera antes de intentar de nuevo - Parke’o')
@section('content')
<div class="auth-card">
    <div class="auth-card-header"><h2>Espera un momento</h2></div>
    <p role="alert">Se alcanzó el límite de intentos. Espera {{ $seconds }} segundos antes de volver a verificar.</p>
    <p>Espera antes de volver a solicitar o comprobar el acceso.</p>
    <a class="btn-auth" href="{{ route(app(\App\Services\StaffAccessService::class)->entryRoute()) }}">Volver a la verificación</a>
    <form method="post" action="{{ route('logout') }}" style="margin-top:1rem">@csrf<button class="back-home">Cerrar sesión</button></form>
</div>
@endsection
