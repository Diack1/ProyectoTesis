@extends('layouts.public')
@section('title', 'Elige tu espacio - Parke’o')
@section('content')
<section class="section-sm"><div class="container">
@if(session('error'))<div class="alert alert-info">{{ session('error') }}</div>@endif
<div class="availability-intro flex-between flex-wrap mb-3"><div><span class="badge badge-info">Reserva antes de llegar</span><h1 class="section-title">Tu lugar en la cochera</h1><p class="section-subtitle">Consulta el plano y selecciona un espacio libre. La disponibilidad se verifica nuevamente al reservar.</p></div><aside class="availability-schedule"><x-icon name="clock"/><div><strong>Horario de atención</strong><p>Lunes a domingo<br>6:30 a. m. – 11:00 p. m.</p></div></aside></div>
@include('partials.plano')
<noscript><p>Activa JavaScript para consultar el plano interactivo.</p><div class="space-grid">@foreach($espacios as $espacio)@if($disponibilidadPorEspacio[$espacio->id]['puede_reservar'])<a class="btn btn-secondary" href="{{ route('reservas.create',$espacio) }}">Reservar {{ $espacio->codigo }}</a>@endif @endforeach</div></noscript>
</div></section>
@endsection
