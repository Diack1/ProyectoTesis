<?php

namespace App\Services;

use App\Models\Estadia;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class LiquidacionService
{
    public static function centavos($monto): int
    {
        return (int) round((float) $monto * 100);
    }

    public function calcular(Estadia $estadia, CarbonInterface $hasta): array
    {
        if (! $estadia->inicio_cobro) {
            throw ValidationException::withMessages(['salida' => 'Todavía no está definido el inicio del cobro. Confirma la lectura o registra una contingencia manual.']);
        }
        if ($hasta->lt($estadia->inicio_cobro)) {
            throw ValidationException::withMessages(['salida' => 'La hora de corte no puede ser anterior al inicio.']);
        }
        $minutos = max(1, (int) ceil($estadia->inicio_cobro->diffInSeconds($hasta) / 60));
        $t = $estadia->tarifa_snapshot;
        $tolerancia = max(0, (int) ($t['tolerancia_minutos'] ?? 0));
        $adelanto = self::centavos($estadia->monto_adelantado);
        $exceso = 0;
        if ($estadia->reserva_id) {
            $excedidos = max(0, $minutos - $estadia->minutos_contratados - $tolerancia);
            $fraccion = max(1, (int) ($t['minutos_fraccion'] ?? 60));
            $exceso = (int) ceil($excedidos / $fraccion) * self::centavos($t['penalidad_por_fraccion'] ?? 0);
            $total = $adelanto + $exceso;
            $detalle = $excedidos ? "Exceso de {$excedidos} min; fracciones de {$fraccion} min después de la tolerancia." : 'Dentro del tiempo contratado y su tolerancia.';
        } else {
            $cobrables = max((int) ($t['tiempo_minimo_minutos'] ?? 60), $minutos - $tolerancia, 1);
            $hora = self::centavos($t['monto_por_hora'] ?? 0);
            $fraccion = max(1, (int) ($t['minutos_fraccion'] ?? 60));
            $importeFraccion = self::centavos($t['monto_por_fraccion'] ?? 0);
            $base = self::centavos($t['monto_base'] ?? 0);
            $tipo = $t['tipo_tarifa'] ?? 'por_hora';
            if ($tipo === 'diaria') {
                $total = (int) ceil($cobrables / 1440) * ($base > 0 ? $base : $hora * 24);
            } elseif ($tipo === 'nocturna' && $base > 0) {
                $inicio = Carbon::parse($t['hora_inicio'] ?? '20:00');
                $fin = Carbon::parse($t['hora_fin'] ?? '06:00');
                if ($fin->lte($inicio)) {
                    $fin->addDay();
                }
                $bloque = max(1, (int) $inicio->diffInMinutes($fin));
                $total = (int) ceil($cobrables / $bloque) * $base;
            } elseif ($tipo === 'fraccion' && $importeFraccion > 0) {
                $total = (int) ceil($cobrables / $fraccion) * $importeFraccion;
            } else {
                $horas = max(1, intdiv($cobrables, 60));
                $resto = $cobrables >= 60 ? $cobrables % 60 : 0;
                $total = $horas * $hora + ($resto ? ($importeFraccion > 0 ? (int) ceil($resto / $fraccion) * $importeFraccion : $hora) : 0);
            }
            $detalle = "{$cobrables} min tarifados, aplicando mínimo y tolerancia de la tarifa registrada al ingresar.";
        }

        return ['minutos' => $minutos, 'total_centavos' => $total, 'adelanto_centavos' => $adelanto, 'exceso_centavos' => $exceso,
            'saldo_centavos' => max(0, $total - $adelanto), 'detalle' => $detalle, 'hasta' => $hasta];
    }
}
