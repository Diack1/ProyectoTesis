@extends('layouts.admin')

@section('title', 'Editar espacio - Cochera Tentación')
@section('page-title', 'Editar espacio')
@section('page-subtitle', 'Actualiza los datos del espacio, estado y vehículos permitidos')

@section('content')

@if($errors->any())
<div class="errors-box">
    <strong>Corrige los siguientes errores:</strong>
    <ul>
        @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="form-card">
    <form action="{{ route('admin.espacios.update', $espacio) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="form-grid">
            <div class="form-group">
                <label for="codigo">Código del espacio</label>
                <input type="text"
                    name="codigo"
                    id="codigo"
                    class="form-control"
                    value="{{ old('codigo', $espacio->codigo) }}"
                    required>
            </div>

            <div class="form-group">
                <label for="estado_actual">Estado actual</label>
                <select name="estado_actual" id="estado_actual" class="form-control" required>
                    <option value="libre" {{ old('estado_actual', $espacio->estado_actual) === 'libre' ? 'selected' : '' }}>Libre</option>
                    <option value="ocupado" {{ old('estado_actual', $espacio->estado_actual) === 'ocupado' ? 'selected' : '' }}>Ocupado</option>
                    <option value="reservado" {{ old('estado_actual', $espacio->estado_actual) === 'reservado' ? 'selected' : '' }}>Reservado</option>
                    <option value="mantenimiento" {{ old('estado_actual', $espacio->estado_actual) === 'mantenimiento' ? 'selected' : '' }}>Mantenimiento</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="descripcion">Descripción</label>
            <textarea name="descripcion"
                id="descripcion"
                class="form-control"
                rows="4">{{ old('descripcion', $espacio->descripcion) }}</textarea>
        </div>

        <div class="form-group">
            <label>Tipos de vehículo permitidos</label>

            <div class="checkbox-group">
                @foreach($vehiculoTipos as $tipo)
                <label class="checkbox-pill">
                    <input type="checkbox"
                        name="vehiculo_tipo_ids[]"
                        value="{{ $tipo->id }}"
                        {{ in_array(
                                    $tipo->id,
                                    old('vehiculo_tipo_ids', $espacio->vehiculoTipos->pluck('id')->toArray())
                               ) ? 'checked' : '' }}>
                    {{ $tipo->nombre }}
                </label>
                @endforeach
            </div>
        </div>

        <div class="form-group">
            <label class="checkbox-pill">
                <input type="checkbox"
                    name="activo"
                    value="1"
                    {{ old('activo', $espacio->activo) ? 'checked' : '' }}>
                Espacio activo
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                Actualizar espacio
            </button>

            <a href="{{ route('admin.espacios.index') }}" class="btn btn-secondary">
                Cancelar
            </a>
        </div>
    </form>
</div>

@endsection