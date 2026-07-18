<?php

namespace App\Services;

use App\Models\Espacio;
use App\Models\Reserva;
use App\Models\VehiculoTipo;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class ReservaDisponibilidadService
{
    public function tieneReservaBloqueante(Espacio $espacio, bool $lock = false): bool
    {
        $query = $this->queryReservasBloqueantes($espacio->id);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get()->contains(fn (Reserva $reserva) => $this->reservaBloqueanteVigente($reserva));
    }

    public function existeConflictoHorario(Espacio $espacio, Carbon $inicioNuevo, Carbon $finNuevo, bool $lock = false): bool
    {
        return $this->reservaEnConflicto($espacio, $inicioNuevo, $finNuevo, $lock) !== null;
    }

    public function reservaEnConflicto(Espacio $espacio, Carbon $inicioNuevo, Carbon $finNuevo, bool $lock = false): ?Reserva
    {
        $query = $this->queryReservasBloqueantes($espacio->id);

        if ($lock) {
            $query->lockForUpdate();
        }

        /** @var Collection<int, Reserva> $reservas */
        $reservas = $query->get();

        foreach ($reservas as $reserva) {
            if (!$this->reservaBloqueanteVigente($reserva)) {
                continue;
            }

            [$inicioExistente, $finExistente] = $this->rangoReserva($reserva);

            if ($inicioExistente->lt($finNuevo) && $finExistente->gt($inicioNuevo)) {
                return $reserva;
            }
        }

        return null;
    }

    public function verificarEspacioParaInicio(Espacio $espacio, SensoresApiService $sensoresApiService): array
    {
        $base = $this->verificarBaseEspacio($espacio);

        if (!$base['disponible']) {
            return $base;
        }

        if ($this->tieneReservaBloqueante($espacio)) {
            return $this->respuesta(false, 'reservado', 'El espacio acaba de ser reservado por otro usuario.');
        }

        $configuracion = $this->verificarConfiguracionReserva($espacio);

        if (!$configuracion['disponible']) {
            return $configuracion;
        }

        return $this->verificarSensor($espacio, $sensoresApiService);
    }

    public function verificarEspacioParaHorario(
        Espacio $espacio,
        Carbon $inicio,
        Carbon $fin,
        SensoresApiService $sensoresApiService,
        bool $lock = false
    ): array {
        $base = $this->verificarBaseEspacio($espacio);

        if (!$base['disponible']) {
            return $base;
        }

        if ($this->existeConflictoHorario($espacio, $inicio, $fin, $lock)) {
            return $this->respuesta(false, 'conflicto_horario', 'Ya existe una reserva para ese horario.');
        }

        $configuracion = $this->verificarConfiguracionReserva($espacio);

        if (!$configuracion['disponible']) {
            return $configuracion;
        }

        return $this->verificarSensor($espacio, $sensoresApiService);
    }

    public function verificarSensor(Espacio $espacio, SensoresApiService $sensoresApiService): array
    {
        $espacio->loadMissing('sensor');

        if (!$espacio->sensor) {
            return $this->respuesta(false, 'sin_sensor', 'El espacio no tiene un sensor asociado.');
        }

        $resultadoSensor = $sensoresApiService->obtenerSensorPorCodigo($espacio->sensor->codigo_sensor);

        if (!$resultadoSensor['disponible']) {
            return $this->respuesta(false, $resultadoSensor['estado'], $resultadoSensor['message'], [
                'sensor' => $resultadoSensor['sensor'],
            ]);
        }

        return $this->respuesta(true, 'disponible', 'El espacio esta disponible.', [
            'sensor' => $resultadoSensor['sensor'],
        ]);
    }

    public function estadoParaTarjeta(Espacio $espacio, ?array $sensorExterno = null): array
    {
        $baseDisponible = $this->espacioBaseDisponible($espacio);
        $tieneReserva = $this->tieneReservaBloqueante($espacio);
        $configuracion = $this->verificarConfiguracionReserva($espacio);
        $configuracionDisponible = $configuracion['disponible'];
        $logicamenteDisponible = $baseDisponible
            && !$tieneReserva
            && $configuracionDisponible;

        $sensorDisponible = $sensorExterno
            && ($sensorExterno['ocupado'] ?? null) === false
            && ($sensorExterno['datos_disponibles'] ?? null) === true;

        $sensorOcupado = $sensorExterno
            && ($sensorExterno['ocupado'] ?? null) === true
            && ($sensorExterno['datos_disponibles'] ?? null) === true;

        $estadoVisual = match (true) {
            $sensorOcupado => 'ocupado',
            !$sensorExterno || !$sensorDisponible => 'sin_datos',
            !$baseDisponible && $espacio->estado_actual === 'mantenimiento' => 'mantenimiento',
            $tieneReserva => 'reservado',
            !$configuracionDisponible => $configuracion['estado'],
            default => 'libre',
        };

        return [
            'estado_visual' => $estadoVisual,
            'estado_texto' => match ($estadoVisual) {
                'libre' => 'Libre',
                'ocupado' => 'Ocupado',
                'mantenimiento' => 'Mantenimiento',
                'reservado' => 'Reservado',
                'sin_tipos' => 'Sin tipos de vehiculo',
                'sin_tarifa' => 'Sin tarifa configurada',
                default => 'Sin conexion',
            },
            'sensor_disponible' => $sensorDisponible,
            'logicamente_disponible' => $logicamenteDisponible,
            'configuracion_disponible' => $configuracionDisponible,
            'bloqueo_motivo' => $logicamenteDisponible ? null : $this->mensajeBloqueo($espacio, $tieneReserva, $configuracion),
            'puede_reservar' => $sensorDisponible && $logicamenteDisponible,
        ];
    }

    public function tiposPermitidosActivos(Espacio $espacio)
    {
        $espacio->loadMissing('vehiculoTipos.tarifas');

        return $espacio->vehiculoTipos
            ->filter(fn (VehiculoTipo $tipo) => $tipo->activo)
            ->values();
    }

    public function tiposPermitidosConTarifa(Espacio $espacio)
    {
        return $this->tiposPermitidosActivos($espacio)
            ->filter(fn (VehiculoTipo $tipo) => $tipo->tarifas->contains(fn ($tarifa) => $tarifa->activo))
            ->values();
    }

    public function queryReservasBloqueantes(int $espacioId)
    {
        return Reserva::where('espacio_id', $espacioId)
            ->whereIn('estado', Reserva::ESTADOS_BLOQUEANTES);
    }

    private function verificarBaseEspacio(Espacio $espacio): array
    {
        if (!$espacio->activo) {
            return $this->respuesta(false, 'inactivo', 'El espacio no esta habilitado.');
        }

        if ($espacio->estado_actual === 'mantenimiento') {
            return $this->respuesta(false, 'mantenimiento', 'El espacio esta en mantenimiento.');
        }

        return $this->respuesta(true, 'base_disponible', 'El espacio esta habilitado.');
    }

    private function espacioBaseDisponible(Espacio $espacio): bool
    {
        return $espacio->activo && $espacio->estado_actual !== 'mantenimiento';
    }

    private function verificarConfiguracionReserva(Espacio $espacio): array
    {
        $tiposActivos = $this->tiposPermitidosActivos($espacio);

        if ($tiposActivos->isEmpty()) {
            return $this->respuesta(false, 'sin_tipos', 'No existen tipos de vehiculo activos para este espacio.');
        }

        if ($this->tiposPermitidosConTarifa($espacio)->isEmpty()) {
            return $this->respuesta(false, 'sin_tarifa', 'No existe una tarifa activa para los tipos de vehiculo de este espacio.');
        }

        return $this->respuesta(true, 'configuracion_disponible', 'El espacio tiene tipos y tarifas disponibles.');
    }

    private function mensajeBloqueo(Espacio $espacio, bool $tieneReserva, array $configuracion): ?string
    {
        if (!$espacio->activo) {
            return 'El espacio no esta habilitado.';
        }

        if ($espacio->estado_actual === 'mantenimiento') {
            return 'El espacio esta en mantenimiento.';
        }

        if ($tieneReserva) {
            return 'El espacio tiene una reserva activa.';
        }

        if (!$configuracion['disponible']) {
            return $configuracion['message'];
        }

        return null;
    }

    private function rangoReserva(Reserva $reserva): array
    {
        $inicio = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $reserva->fecha_reserva->format('Y-m-d') . ' ' . $this->horaConSegundos($reserva->hora_inicio),
            'America/Lima'
        );

        $fin = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $reserva->fecha_reserva->format('Y-m-d') . ' ' . $this->horaConSegundos($reserva->hora_fin),
            'America/Lima'
        );

        if ($fin->lessThanOrEqualTo($inicio)) {
            $fin->addDay();
        }

        return [$inicio, $fin];
    }

    private function reservaBloqueanteVigente(Reserva $reserva): bool
    {
        if (!$reserva->esBloqueante()) {
            return false;
        }

        if ($reserva->estado !== 'pendiente_pago') {
            return true;
        }

        return $reserva->expires_at === null
            || $reserva->expires_at->timezone('America/Lima')->greaterThan(now('America/Lima'));
    }

    private function horaConSegundos(string $hora): string
    {
        return strlen($hora) === 5 ? $hora . ':00' : $hora;
    }

    private function respuesta(bool $disponible, string $estado, string $message, array $extra = []): array
    {
        return array_merge([
            'disponible' => $disponible,
            'estado' => $estado,
            'message' => $message,
        ], $extra);
    }
}
