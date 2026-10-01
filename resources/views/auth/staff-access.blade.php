@extends('layouts.auth')
@section('title', 'Autorizar acceso')
@section('content')
<div class="auth-card" style="width:100%;overflow-wrap:anywhere">
<h1>{{ $owner ? 'Revisa tu correo' : 'Código de autorización' }}</h1>
<p>{{ $owner ? 'Recibirás un código de seis dígitos en tu correo. No necesitas una aplicación autenticadora.' : 'El código se envía al correo del superadministrador. Pídeselo para autorizar esta sesión. No se repite al cambiar de página.' }}</p>
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
@foreach($errors->all() as $error)<p role="alert">{{ $error }}</p>@endforeach
@if(!$ready)
<p role="alert">Falta configurar el envío de correo del sistema. Todavía no se pueden enviar códigos ni solicitudes. No se ha enviado ningún correo. Primero debe completarse la conexión con Gmail en la PC del sistema.</p>
@endif
<form method="post" action="{{ route('staff-access.start') }}">@csrf
<button class="btn-auth" type="submit" data-code-send data-wait="{{ $retryAfter }}" data-ready="{{ $ready ? '1' : '0' }}" @disabled(!$ready || $retryAfter > 0)>Enviar código a mi correo</button><p data-code-wait role="status">@if($retryAfter > 0)Podrás solicitar otro código en {{ $retryAfter }} segundos. Puedes introducir el código recibido sin esperar.@endif</p></form>
@if($entry)
<p>Estado: {{ $entry->expires_at->isPast() ? 'Vencida' : (['pending'=>'Pendiente', 'approved'=>'Aprobada', 'rejected'=>'Rechazada', 'consumed'=>'Utilizada', 'superseded'=>'Reemplazada'][$entry->state] ?? 'No disponible') }}.</p>
<p>Vence: {{ $entry->expires_at->format('H:i') }}.</p>
@endif
<form method="post" action="{{ route('staff-access.verify') }}">@csrf
<label for="code">Código recibido por correo</label><input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required aria-describedby="code-help" style="font-size:1.25rem;letter-spacing:.25em;width:100%;box-sizing:border-box">
<p id="code-help">{{ $entry ? 'Introduce el código de seis dígitos recibido por el superadministrador.' : 'Aún no se ha enviado un código para esta sesión.' }}</p>
<button class="btn-auth" type="submit" @disabled(!$entry)>Verificar y entrar</button>
</form>
<form method="post" action="{{ route('logout') }}" style="margin-top:1.5rem">@csrf<button class="btn-auth" type="submit">Cerrar sesión</button></form>
</div>
@endsection
@push('scripts')
<script src="/js/access-code.js?v={{ filemtime(public_path('js/access-code.js')) }}" defer></script>
@endpush
