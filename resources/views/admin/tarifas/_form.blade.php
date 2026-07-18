@php
$tarifa = $tarifa ?? null;
@endphp

<div class="form-grid">
    <div class="form-group">
        <label for="vehiculo_tipo_id">Tipo de vehículo</label>
        <select name="vehiculo_tipo_id" id="vehiculo_tipo_id" class="form-control" required>
            <option value="">Seleccione tipo de vehículo</option>
            @foreach($vehiculoTipos as $tipo)
            <option value="{{ $tipo->id }}"
                {{ old('vehiculo_tipo_id', $tarifa->vehiculo_tipo_id ?? '') == $tipo->id ? 'selected' : '' }}>
                {{ $tipo->nombre }}
            </option>
            @endforeach
        </select>
    </div>

    <div class="form-group">
        <label for="tipo_tarifa">Tipo de tarifa</label>
        <select name="tipo_tarifa" id="tipo_tarifa" class="form-control" required>
            <option value="">Seleccione tipo</option>
            <option value="por_hora" {{ old('tipo_tarifa', $tarifa->tipo_tarifa ?? '') === 'por_hora' ? 'selected' : '' }}>Por hora</option>
            <option value="fraccion" {{ old('tipo_tarifa', $tarifa->tipo_tarifa ?? '') === 'fraccion' ? 'selected' : '' }}>Por fracción</option>
            <option value="diaria" {{ old('tipo_tarifa', $tarifa->tipo_tarifa ?? '') === 'diaria' ? 'selected' : '' }}>Diaria</option>
            <option value="nocturna" {{ old('tipo_tarifa', $tarifa->tipo_tarifa ?? '') === 'nocturna' ? 'selected' : '' }}>Nocturna</option>
        </select>
    </div>
</div>

<div class="form-group">
    <label for="nombre">Nombre de la tarifa</label>
    <input type="text"
        name="nombre"
        id="nombre"
        class="form-control"
        value="{{ old('nombre', $tarifa->nombre ?? '') }}"
        placeholder="Ejemplo: Tarifa auto por hora"
        required>
</div>

<div class="form-grid">
    <div class="form-group">
        <label for="monto_base">Monto base</label>
        <input type="number"
            step="0.01"
            min="0"
            name="monto_base"
            id="monto_base"
            class="form-control"
            value="{{ old('monto_base', $tarifa->monto_base ?? 0) }}">
        <small class="form-help">Usado principalmente para tarifa diaria o nocturna.</small>
    </div>

    <div class="form-group">
        <label for="monto_por_hora">Monto por hora</label>
        <input type="number"
            step="0.01"
            min="0"
            name="monto_por_hora"
            id="monto_por_hora"
            class="form-control"
            value="{{ old('monto_por_hora', $tarifa->monto_por_hora ?? 0) }}">
    </div>
</div>

<div class="form-grid">
    <div class="form-group">
        <label for="monto_por_fraccion">Monto por fracción</label>
        <input type="number"
            step="0.01"
            min="0"
            name="monto_por_fraccion"
            id="monto_por_fraccion"
            class="form-control"
            value="{{ old('monto_por_fraccion', $tarifa->monto_por_fraccion ?? '') }}">
    </div>

    <div class="form-group">
        <label for="minutos_fraccion">Minutos por fracción</label>
        <input type="number"
            min="1"
            name="minutos_fraccion"
            id="minutos_fraccion"
            class="form-control"
            value="{{ old('minutos_fraccion', $tarifa->minutos_fraccion ?? '') }}"
            placeholder="Ejemplo: 30">
    </div>
</div>

<div class="form-grid">
    <div class="form-group">
        <label for="tiempo_minimo_minutos">Tiempo mínimo de cobro</label>
        <input type="number"
            min="1"
            name="tiempo_minimo_minutos"
            id="tiempo_minimo_minutos"
            class="form-control"
            value="{{ old('tiempo_minimo_minutos', $tarifa->tiempo_minimo_minutos ?? 60) }}"
            required>
    </div>

    <div class="form-group">
        <label for="tolerancia_minutos">Tiempo de tolerancia</label>
        <input type="number"
            min="0"
            name="tolerancia_minutos"
            id="tolerancia_minutos"
            class="form-control"
            value="{{ old('tolerancia_minutos', $tarifa->tolerancia_minutos ?? 10) }}"
            required>
    </div>
</div>

<div class="form-grid">
    <div class="form-group">
        <label for="penalidad_por_fraccion">Penalidad por exceso</label>
        <input type="number"
            step="0.01"
            min="0"
            name="penalidad_por_fraccion"
            id="penalidad_por_fraccion"
            class="form-control"
            value="{{ old('penalidad_por_fraccion', $tarifa->penalidad_por_fraccion ?? 0) }}">
    </div>

    <div class="form-group">
        <label for="prioridad">Prioridad</label>
        <input type="number"
            min="1"
            max="100"
            name="prioridad"
            id="prioridad"
            class="form-control"
            value="{{ old('prioridad', $tarifa->prioridad ?? 1) }}"
            required>
        <small class="form-help">Mientras mayor sea, más preferencia tendrá.</small>
    </div>
</div>

<div class="form-grid">
    <div class="form-group">
        <label for="hora_inicio">Hora inicio</label>
        <input type="time"
            name="hora_inicio"
            id="hora_inicio"
            class="form-control"
            value="{{ old('hora_inicio', $tarifa && $tarifa->hora_inicio ? substr($tarifa->hora_inicio, 0, 5) : '') }}">
        <small class="form-help">Útil para tarifa nocturna.</small>
    </div>

    <div class="form-group">
        <label for="hora_fin">Hora fin</label>
        <input type="time"
            name="hora_fin"
            id="hora_fin"
            class="form-control"
            value="{{ old('hora_fin', $tarifa && $tarifa->hora_fin ? substr($tarifa->hora_fin, 0, 5) : '') }}">
    </div>
</div>

<div class="form-group">
    <label class="checkbox-pill">
        <input type="checkbox"
            name="activo"
            id="activo"
            value="1"
            {{ old('activo', $tarifa->activo ?? true) ? 'checked' : '' }}>
        Tarifa activa
    </label>

    <small class="form-help">
        Si activas esta tarifa, el sistema desactivará otra tarifa activa del mismo vehículo y del mismo tipo.
    </small>
</div>