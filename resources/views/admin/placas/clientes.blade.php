@extends('layouts.admin')
@section('page-title', 'Clientes y vehículos')
@section('page-subtitle', 'Asocia una matrícula revisada con el cliente para reconocer próximas visitas.')
@section('content')
@include('admin.estadias.messages')

<div class="management-stats"><x-admin.stat label="Vehículos registrados" :value="$totalVehiculos" icon="car"/><x-admin.stat label="Asociaciones activas" :value="$vehiculosActivos" icon="users"/><x-admin.stat label="Vehículos nuevos este mes" :value="$nuevosMes" icon="plus"/></div>
<details class="admin-page-card" @if($editar || $errors->any()) open @endif><summary>{{ $editar ? 'Editar asociación' : '+ Registrar vehículo de un cliente' }}</summary>
<h2>{{ $editar ? 'Editar asociación' : 'Registrar vehículo de un cliente' }}</h2>
<p>Confirma los datos con el cliente. La cámara busca esta asociación; no identifica a la persona que conduce. Un vehículo se indica como frecuente desde dos visitas finalizadas.</p>
<form method="post" action="{{ $editar ? route('admin.clientes-vehiculos.update', $editar) : route('admin.clientes-vehiculos.store') }}">@csrf @if($editar) @method('PUT') @endif
<div class="form-grid">
<div class="form-group"><label for="cliente-placa">Placa</label><input id="cliente-placa" name="placa" value="{{ old('placa', $editar?->placa) }}" maxlength="12" required @readonly($editar)></div>
<div class="form-group"><label for="cliente-nombre">Nombre del cliente</label><input id="cliente-nombre" name="nombre" value="{{ old('nombre', $editar?->nombre) }}" maxlength="150" required></div>
<div class="form-group"><label for="cliente-telefono">Teléfono (opcional)</label><input id="cliente-telefono" name="telefono" value="{{ old('telefono', $editar?->telefono) }}" maxlength="40"></div>
<div class="form-group"><label for="cliente-cuenta">Correo de su cuenta web (opcional)</label><input id="cliente-cuenta" name="email_cuenta" type="email" value="{{ old('email_cuenta', $editar?->usuario?->email) }}"><small>Permite consultar sus reservas. Si lo vinculas, se mostrará el nombre de esa cuenta.</small></div>
<div class="form-group"><label for="cliente-activo">Estado de la asociación</label><select id="cliente-activo" name="activo"><option value="1" @selected(old('activo', $editar?->activo ?? true))>Activa</option><option value="0" @selected(!old('activo', $editar?->activo ?? true))>Inactiva</option></select></div>
</div>
<label class="checkbox-row"><input type="checkbox" name="revisado" value="1" required>He comprobado la placa y los datos del cliente.</label>
<button class="btn btn-primary">Guardar asociación</button> @if($editar)<a href="{{ route('admin.clientes-vehiculos.index') }}">Cancelar edición</a>@endif
</form></details>
<section class="admin-page-card"><h2>Vehículos registrados</h2>
<form method="get" class="management-toolbar"><input name="buscar" aria-label="Buscar por placa, nombre, teléfono o correo" value="{{ request('buscar') }}" placeholder="Placa, nombre, teléfono o correo"><button class="btn btn-secondary">Buscar</button></form>
<div class="table-responsive"><table class="data-table mobile-records" data-mobile-details="2,4"><thead><tr><th>Placa</th><th>Cliente asociado</th><th>Teléfono</th><th>Visitas finalizadas</th><th>Última visita finalizada</th><th>Estado</th><th>Acción</th></tr></thead><tbody>
@forelse($vehiculos as $v)<tr><td data-label="Placa"><span class="plate-tag">{{ $v->placa }}</span></td><td data-label="Cliente asociado">{{ $v->usuario?->name ?? $v->nombre }}</td><td data-label="Teléfono">{{ $v->telefono ?? '—' }}</td><td data-label="Visitas finalizadas">{{ $v->visitas_count }} @if($v->visitas_count >= 2)<span class="badge badge-success">Frecuente</span>@endif</td><td data-label="Última visita finalizada">{{ $v->visitas_max_hora_ingreso ? \Carbon\Carbon::parse($v->visitas_max_hora_ingreso)->format('d/m/Y H:i') : 'Sin visitas finalizadas' }}</td><td data-label="Estado">{{ $v->activo ? 'Activa' : 'Inactiva' }}</td><td data-label="Acción"><a class="btn btn-secondary" href="{{ route('admin.clientes-vehiculos.index', ['editar' => $v->id]) }}">Editar</a></td></tr>@empty<tr><td colspan="7">No hay vehículos registrados. Añade el primero para probar la identificación del cliente.</td></tr>@endforelse
</tbody></table></div>{{ $vehiculos->links() }}</section>
@endsection
