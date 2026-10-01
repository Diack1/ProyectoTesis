@extends('layouts.auth')
@section('title', 'Vincular Google - Parke’o')
@section('content')
<div class="auth-card">
<h1>Conserva tu cuenta y tus reservas</h1>
<p>Ya existe una cuenta con ese correo. Confirma su contraseña para vincular Google.</p>
@foreach($errors->all() as $error)<p role="alert">{{ $error }}</p>@endforeach
<form method="post" action="{{ route('google.link.store') }}">@csrf
<label for="password">Contraseña de Parke’o</label>
<input type="password" id="password" name="password" required autocomplete="current-password">
<button class="btn-auth" type="submit">Vincular y continuar</button>
</form>
<p><a href="{{ route('password.request') }}">Recuperar mi contraseña</a></p>
<a href="{{ route('login') }}">Volver al acceso</a>
</div>
@endsection
