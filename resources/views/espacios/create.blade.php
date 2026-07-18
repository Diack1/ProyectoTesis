@extends('layouts.admin')

@section('title', 'Nuevo espacio - Cochera Tentación')
@section('page-title', 'Nuevo espacio')
@section('page-subtitle', 'Registra un nuevo espacio de estacionamiento y sus vehículos permitidos')

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
    <form action="{{ route('admin.espacios.store') }}" method="POST">
        @csrf

        <div class="form-grid">
            <div class="form-group">
                <label for="codigo">Código del espacio</label>
                <input type="text"
                    name="codigo"
                    id="codigo"
                    class="form-control"
                    value="{{ old('codigo') }}"
                    placeholder="Ejemplo: E01"
                    required>
            </div>

            <div class="form-group">
                <label for="estado_actual">Estado inicial</label>
                <select name="estado_actual" id="estado_actual" class="form-control" required>
                    <option value="libre" {{ old('estado_actual') === 'libre' ? 'selected' : '' }}>Libre</option>
                    <option value="ocupado" {{ old('estado_actual') === 'ocupado' ? 'selected' : '' }}>Ocupado</option>
                    <option value="reservado" {{ old('estado_actual') === 'reservado' ? 'selected' : '' }}>Reservado</option>
                    <option value="mantenimiento" {{ old('estado_actual') === 'mantenimiento' ? 'selected' : '' }}>Mantenimiento</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="descripcion">Descripción</label>
            <textarea name="descripcion"
                id="descripcion"
                class="form-control"
                rows="4"
                placeholder="Ejemplo: Espacio de estacionamiento E01">{{ old('descripcion') }}</textarea>
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label for="codigo_sensor">Código del sensor</label>
                <input type="text"
                    name="codigo_sensor"
                    id="codigo_sensor"
                    class="form-control"
                    value="{{ old('codigo_sensor') }}"
                    placeholder="Ejemplo: SENSOR_08"
                    required>
            </div>

            <div class="form-group">
                <label for="tipo_sensor">Tipo de sensor</label>
                <select name="tipo_sensor" id="tipo_sensor" class="form-control" required>
                    <option value="">Seleccione tipo de sensor</option>
                    <option value="ultrasonico" {{ old('tipo_sensor') === 'ultrasonico' ? 'selected' : '' }}>
                        Ultrasónico
                    </option>
                    <option value="infrarrojo" {{ old('tipo_sensor') === 'infrarrojo' ? 'selected' : '' }}>
                        Infrarrojo
                    </option>
                    <option value="simulado" {{ old('tipo_sensor') === 'simulado' ? 'selected' : '' }}>
                        Simulado
                    </option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Tipos de vehículo permitidos</label>

            <div class="checkbox-group">
                @foreach($vehiculoTipos as $tipo)
                <label class="checkbox-pill">
                    <input type="checkbox"
                        name="vehiculo_tipo_ids[]"
                        value="{{ $tipo->id }}"
                        {{ in_array($tipo->id, old('vehiculo_tipo_ids', [])) ? 'checked' : '' }}>
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
                    {{ old('activo', true) ? 'checked' : '' }}>
                Espacio activo
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                Guardar espacio
            </button>

            <a href="{{ route('admin.espacios.index') }}" class="btn btn-secondary">
                Cancelar
            </a>
        </div>
    </form>
</div>

@endsection