<?php

namespace App\Services;

use App\Models\Tarifa;
use App\Models\VehiculoTipo;
use Carbon\Carbon;
use RuntimeException;

class TarifaService
{
    public function calcularMonto(VehiculoTipo $vehiculoTipo, Carbon $fechaHoraInicio, int $duracionMinutos): array
    {
        $tarifa = $this->obtenerTarifaActiva($vehiculoTipo, $fechaHoraInicio, $duracionMinutos);

        if (!$tarifa) {
            throw new RuntimeException('No existe una tarifa activa para el tipo de vehículo seleccionado.');
        }

        $minutosCobro = max($duracionMinutos, (int) $tarifa->tiempo_minimo_minutos);
        $montoTotal = 0;

        switch ($tarifa->tipo_tarifa) {
            case 'por_hora':
                $montoTotal = $this->calcularPorHora($tarifa, $minutosCobro);
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
            'tarifa_hora' => (float) $tarifa->monto_por_hora,
            'monto_total' => round($montoTotal, 2),
            'tolerancia_minutos' => (int) $tarifa->tolerancia_minutos,
            'penalidad_por_fraccion' => (float) $tarifa->penalidad_por_fraccion,
        ];
    }

    private function obtenerTarifaActiva(VehiculoTipo $vehiculoTipo, Carbon $fechaHoraInicio, int $duracionMinutos): ?Tarifa
    {
        $tarifas = Tarifa::where('vehiculo_tipo_id', $vehiculoTipo->id)
            ->where('activo', true)
            ->orderByDesc('prioridad')
            ->get();

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
