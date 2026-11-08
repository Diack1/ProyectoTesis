<?php

namespace App\Services;

use App\Models\Abono;
use App\Models\Espacio;
use Illuminate\Support\Collection;

class MapaOperativoService
{
    public function estados(Collection $espacios, ReservaDisponibilidadService $disponibilidad): Collection
    {
        $abonadosVigentes = Abono::vigentes()->pluck('abonado_id')->flip();

        return $espacios->mapWithKeys(function (Espacio $espacio) use ($disponibilidad, $abonadosVigentes) {
            $estado = $disponibilidad->estadoParaTarjeta($espacio);
            $estadia = $espacio->relationLoaded('estadias')
                ? $espacio->estadias->first()
                : $espacio->estadias()->activas()->with('abono')->first();

            if ($espacio->estado_actual !== 'ocupado' || !$estadia) {
                return [$espacio->id => $estado];
            }

            if ($espacio->modo_monitoreo === 'sensor'
                && (!$estadia->inicio_cobro || $espacio->getRawOriginal('estado_actual') !== 'ocupado')) {
                $estado['estado_visual'] = 'sin_senal';
                $estado['estado_texto'] = $estadia->inicio_cobro ? 'Revisar sensor' : 'Esperando sensor';
                $estado['puede_reservar'] = false;
                $estado['logicamente_disponible'] = false;
                return [$espacio->id => $estado];
            }

            if ($estadia->abono_id && $abonadosVigentes->has($estadia->abono?->abonado_id)) {
                $estado['estado_visual'] = 'abonado';
                $estado['estado_texto'] = 'Abonado';
            } elseif ($this->excedio($estadia)) {
                $estado['estado_visual'] = 'excedido';
                $estado['estado_texto'] = 'Tiempo excedido';
            } else {
                $estado['estado_visual'] = 'ocupado';
                $estado['estado_texto'] = 'Ocupado';
            }
            $estado['puede_reservar'] = false;
            $estado['logicamente_disponible'] = false;
            return [$espacio->id => $estado];
        });
    }

    private function excedio(\App\Models\Estadia $estadia): bool
    {
        if (!$estadia->inicio_cobro) return false;
        $tarifa = $estadia->tarifa_snapshot ?? [];
        $duracion = $estadia->reserva_id
            ? (int) $estadia->minutos_contratados
            : (int) ($tarifa['tiempo_minimo_minutos'] ?? $tarifa['bloque_minutos'] ?? 60);
        $limite = max(1, $duracion) + max(0, (int) ($tarifa['tolerancia_minutos'] ?? 0));
        return (int) ceil($estadia->inicio_cobro->diffInSeconds(now()) / 60) > $limite;
    }
}
