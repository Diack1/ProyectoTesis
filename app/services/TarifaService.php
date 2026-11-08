<?php

namespace App\Services;

use App\Models\Tarifa;
use App\Models\VehiculoTipo;
use Carbon\Carbon;
use RuntimeException;
use Illuminate\Validation\ValidationException;

class TarifaService
{
    public function tarifasIngreso()
    {
        $tarifas = Tarifa::where('activo', true)->whereIn('tipo_tarifa', ['por_hora', 'bloque_horas'])
            ->whereHas('vehiculoTipo', fn ($query) => $query->where('activo', true))
            ->orderBy('vehiculo_tipo_id')->orderBy('tipo_tarifa')->orderBy('nombre')->get();
        return $tarifas->filter(fn ($tarifa) => $tarifa->tipo_tarifa !== 'bloque_horas'
            || $tarifas->contains(fn ($hora) => $hora->vehiculo_tipo_id === $tarifa->vehiculo_tipo_id
                && $hora->tipo_tarifa === 'por_hora' && (int) $hora->minutos_fraccion > 0
                && (float) $hora->monto_por_fraccion > 0))->values();
    }

    public function snapshotIngreso(Tarifa $tarifa): array
    {
        if (!$tarifa->activo || !in_array($tarifa->tipo_tarifa, ['por_hora', 'bloque_horas'], true)) {
            throw ValidationException::withMessages(['tarifa_id' => 'Selecciona una tarifa activa de ingreso.']);
        }
        $snapshot = $tarifa->only(['nombre', 'tipo_tarifa', 'monto_por_hora', 'monto_base', 'monto_por_fraccion',
            'minutos_fraccion', 'tiempo_minimo_minutos', 'tolerancia_minutos', 'penalidad_por_fraccion',
            'hora_inicio', 'hora_fin', 'cobro_intervalos']);
        $snapshot['tarifa_id'] = $tarifa->id;
        if ($tarifa->tipo_tarifa === 'bloque_horas') {
            $hora = Tarifa::where('vehiculo_tipo_id', $tarifa->vehiculo_tipo_id)->where('tipo_tarifa', 'por_hora')
                ->where('activo', true)->orderByDesc('prioridad')->first();
            if (!$hora || (int) $hora->minutos_fraccion <= 0 || (float) $hora->monto_por_fraccion <= 0) {
                throw ValidationException::withMessages(['tarifa_id' => 'El bloque requiere una tarifa por hora activa con adicionales configurados.']);
            }
            $snapshot['minutos_fraccion'] = $hora->minutos_fraccion;
            $snapshot['monto_por_fraccion'] = $hora->monto_por_fraccion;
            $snapshot['tolerancia_minutos'] = $hora->tolerancia_minutos;
            $snapshot['penalidad_por_fraccion'] = $hora->monto_por_fraccion;
        }
        return $snapshot;
    }

    public function calcularMonto(VehiculoTipo $vehiculoTipo, Carbon $fechaHoraInicio, int $duracionMinutos): array
    {
        $tarifa = $this->obtenerTarifaActiva($vehiculoTipo, $fechaHoraInicio, $duracionMinutos);

        if (!$tarifa) {
            throw new RuntimeException('No existe una tarifa activa para el tipo de vehículo seleccionado.');
        }

        $horaCompartida = $tarifa->tipo_tarifa === 'bloque_horas'
            ? Tarifa::where('vehiculo_tipo_id', $vehiculoTipo->id)->where('tipo_tarifa','por_hora')->where('activo',true)->orderByDesc('prioridad')->first()
            : null;
        $tolerancia = (int) ($horaCompartida?->tolerancia_minutos ?? $tarifa->tolerancia_minutos);
        $minutosCobro = $tarifa->cobro_intervalos
            ? max((int) $tarifa->tiempo_minimo_minutos, $duracionMinutos - $tolerancia)
            : max($duracionMinutos, (int) $tarifa->tiempo_minimo_minutos);
        $montoTotal = 0;

        switch ($tarifa->tipo_tarifa) {
            case 'por_hora':
                $montoTotal = $tarifa->cobro_intervalos
                    ? (float)$tarifa->monto_por_hora + ceil(max(0,$minutosCobro-60)/max(1,(int)$tarifa->minutos_fraccion))*(float)$tarifa->monto_por_fraccion
                    : $this->calcularPorHora($tarifa, $minutosCobro);
                break;
            case 'bloque_horas':
                if (!$horaCompartida || !$horaCompartida->minutos_fraccion || !$horaCompartida->monto_por_fraccion) throw new RuntimeException('El bloque requiere una tarifa por hora activa con intervalos adicionales.');
                $montoTotal = (float)$tarifa->monto_base
                    + ceil(max(0,$minutosCobro-(int)$tarifa->tiempo_minimo_minutos)/max(1,(int)$horaCompartida->minutos_fraccion))*(float)$horaCompartida->monto_por_fraccion;
                break;

            case 'fraccion':
                $montoTotal = $this->calcularPorFraccion($tarifa, $minutosCobro);
                break;

            case 'diaria':
                $montoTotal = $this->calcularDiaria($tarifa);
                break;

            case 'nocturna':
                $montoTotal = $this->calcularNocturna($tarifa, $minutosCobro);
                break;

            default:
                throw new RuntimeException('Tipo de tarifa no válido.');
        }

        return [
            'tarifa' => $tarifa,
            'vehiculo_tipo' => $vehiculoTipo,
            'duracion_minutos' => $duracionMinutos,
            'minutos_cobro' => $minutosCobro,
            'tarifa_hora' => (float) ($horaCompartida?->monto_por_hora ?? $tarifa->monto_por_hora),
            'monto_total' => round($montoTotal, 2),
            'tolerancia_minutos' => $tolerancia,
            'penalidad_por_fraccion' => (float) ($horaCompartida?->monto_por_fraccion ?? $tarifa->penalidad_por_fraccion),
            'minutos_fraccion' => (int) ($horaCompartida?->minutos_fraccion ?? $tarifa->minutos_fraccion ?? 60),
        ];
    }

    private function obtenerTarifaActiva(VehiculoTipo $vehiculoTipo, Carbon $fechaHoraInicio, int $duracionMinutos): ?Tarifa
    {
        $tarifas = Tarifa::where('vehiculo_tipo_id', $vehiculoTipo->id)
            ->where('activo', true)
            ->orderByDesc('prioridad')
            ->get();
        if ($tarifas->contains(fn ($t) => $t->cobro_intervalos && $t->tipo_tarifa !== 'mensual')) {
            $tarifas = $tarifas->whereIn('tipo_tarifa', ['por_hora', 'bloque_horas']);
        }

        $tarifaNocturna = $tarifas
            ->where('tipo_tarifa', 'nocturna')
            ->first(function ($tarifa) use ($fechaHoraInicio) {
                return $this->horaEstaDentroDeRango(
                    $fechaHoraInicio,
                    $tarifa->hora_inicio,
                    $tarifa->hora_fin
                );
            });

        if ($tarifaNocturna) {
            return $tarifaNocturna;
        }

        $bloque = $tarifas->where('tipo_tarifa','bloque_horas')->first();
        if ($bloque && $duracionMinutos >= (int)$bloque->tiempo_minimo_minutos) return $bloque;

        if ($duracionMinutos >= 1440) {
            $tarifaDiaria = $tarifas->where('tipo_tarifa', 'diaria')->first();

            if ($tarifaDiaria) {
                return $tarifaDiaria;
            }
        }


        $tarifaPorHora = $tarifas->where('tipo_tarifa', 'por_hora')->first();

        if ($tarifaPorHora) {
            return $tarifaPorHora;
        }

        return $tarifas->where('tipo_tarifa', 'fraccion')->first();
    }

    private function horaEstaDentroDeRango(Carbon $fechaHoraInicio, ?string $horaInicio, ?string $horaFin): bool
    {
        if (!$horaInicio || !$horaFin) {
            return false;
        }

        $horaActual = $fechaHoraInicio->format('H:i:s');

        if ($horaInicio <= $horaFin) {
            return $horaActual >= $horaInicio && $horaActual <= $horaFin;
        }

        return $horaActual >= $horaInicio || $horaActual <= $horaFin;
    }

    private function calcularPorHora(Tarifa $tarifa, int $minutosCobro): float
    {
        $montoPorHora = (float) $tarifa->monto_por_hora;
        $horasCompletas = intdiv($minutosCobro, 60);
        $minutosRestantes = $minutosCobro % 60;

        if ($horasCompletas === 0) {
            $horasCompletas = 1;
            $minutosRestantes = 0;
        }

        $total = $horasCompletas * $montoPorHora;

        if ($minutosRestantes > 0) {
            if ($tarifa->monto_por_fraccion && $tarifa->minutos_fraccion) {
                $fracciones = ceil($minutosRestantes / $tarifa->minutos_fraccion);
                $total += $fracciones * (float) $tarifa->monto_por_fraccion;
            } else {
                $total += $montoPorHora;
            }
        }

        return $total;
    }

    private function calcularPorFraccion(Tarifa $tarifa, int $minutosCobro): float
    {
        if (!$tarifa->monto_por_fraccion || !$tarifa->minutos_fraccion) {
            return $this->calcularPorHora($tarifa, $minutosCobro);
        }

        $fracciones = ceil($minutosCobro / $tarifa->minutos_fraccion);

        return $fracciones * (float) $tarifa->monto_por_fraccion;
    }

    private function calcularDiaria(Tarifa $tarifa): float
    {
        if ((float) $tarifa->monto_base > 0) {
            return (float) $tarifa->monto_base;
        }

        return (float) $tarifa->monto_por_hora * 24;
    }

    private function calcularNocturna(Tarifa $tarifa, int $minutosCobro): float
    {
        if ((float) $tarifa->monto_base > 0) {
            return (float) $tarifa->monto_base;
        }

        return $this->calcularPorHora($tarifa, $minutosCobro);
    }
}
