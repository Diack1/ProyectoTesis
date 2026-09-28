@extends('layouts.auth')
@section('title', 'Verifica tu correo')
@section('content')
<div class="auth-card" style="width:100%;overflow-wrap:anywhere">
<h1>Verifica tu correo</h1>
<p>Para activar las reservas, confirma el correo de tu cuenta: <strong>{{ auth()->user()->email }}</strong>.</p>
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
@foreach($errors->all() as $error)<p role="alert">{{ $error }}</p>@endforeach
<form method="post" action="{{ route('verification.verify') }}">@csrf
<label for="code">Código de seis dígitos</label>
<input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required style="width:100%;box-sizing:border-box;font-size:1.25rem;letter-spacing:.2em">
<p>El código vence en 10 minutos. Revisa también la carpeta de spam.</p>
<button class="btn-auth" type="submit">Verificar correo</button>
</form>
<form method="post" action="{{ route('verification.send') }}" style="margin-top:1rem">@csrf<button class="btn-auth" type="submit">Reenviar código</button></form>
<form method="post" action="{{ route('logout') }}" style="margin-top:1rem">@csrf<button class="btn-auth" type="submit">Cerrar sesión</button></form>
</div>
@endsection
