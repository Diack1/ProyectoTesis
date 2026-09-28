@extends('layouts.admin')
@section('title', 'Actividad de seguridad')
@section('page-title', 'Actividad de seguridad')
@section('page-subtitle', 'Registro de cambios sensibles; no contiene contraseñas ni datos de pago')
@section('content')
<p>Los cambios marcados requieren revisión. Las alertas por correo necesitan un destinatario configurado y un trabajador de colas activo.</p>
@forelse ($events as $event)
<article class="admin-page-card" style="overflow-wrap:anywhere;margin-bottom:1rem">
    @if($event->requires_attention && !$event->reviewed_at)
        <p class="badge">Requiere revisión</p>
        <form method="post" action="{{ route('admin.seguridad.review', $event->id) }}">@csrf<button class="btn btn-secondary">Marcar como revisado</button></form>
    @elseif($event->reviewed_at)
        <p>Revisado por usuario #{{ $event->reviewed_by }} · {{ $event->reviewed_at }}</p>
    @endif
    <h2>{{ $event->resource_type }} #{{ $event->resource_id }}</h2>
    <p>{{ $event->created_at }} · {{ $event->action === 'created' ? 'Creación' : 'Actualización' }}</p>
    <p>Responsable: {{ $event->actor_id ? 'Usuario #'.$event->actor_id : 'Proceso del sistema' }}</p>
    <p>Campos modificados: {{ implode(', ', json_decode($event->changed_fields, true)) }}</p>
</article>
@empty
<p>No hay eventos registrados.</p>
@endforelse
{{ $events->links() }}
@endsection
