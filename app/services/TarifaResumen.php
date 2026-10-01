<?php

namespace App\Services;

class TarifaResumen
{
    /** Prices for the supported booking durations, calculated by the same billing service. */
    public static function opciones(iterable $tipos): array
    {
        $prices = [];
        $instant = now('America/Lima')->startOfMinute();
        $service = app(TarifaService::class);
        foreach ($tipos as $tipo) {
            foreach ([60, 120, 180, 240] as $minutes) {
                try {
                    $calculo = $service->calcularMonto($tipo, $instant->copy(), $minutes);
                    $prices[$tipo->id][$minutes] = [...self::presentar($calculo),
                        'total' => number_format($calculo['monto_total'], 2, '.', '')];
                } catch (\RuntimeException $e) {
                    // No active tariff: omit rather than invent a price.
                }
            }
        }
        return $prices;
    }

    public static function tiempo(int $minutes): string
    {
        return $minutes % 60 === 0
            ? ($minutes / 60).' '.($minutes === 60 ? 'hora' : 'horas')
            : $minutes.' minutos';
    }

    public static function presentar(array $calculo): array
    {
        $tarifa = $calculo['tarifa'];
        $money = fn ($value) => 'S/ '.number_format((float)$value, 2);
        $fixed = in_array($tarifa->tipo_tarifa, ['nocturna', 'diaria']) && (float)$tarifa->monto_base > 0;
        $duration = self::tiempo((int)$calculo['duracion_minutos']);
        if ($fixed) {
            $unit = $money($tarifa->monto_base).' · Tarifa '.($tarifa->tipo_tarifa === 'nocturna' ? 'nocturna' : 'diaria').' fija';
            $detail = 'Tiempo elegido: '.$duration.'. Se cobra el importe fijo, no un precio por hora. El tiempo contratado es el que seleccionaste.';
        } elseif ($tarifa->tipo_tarifa === 'fraccion' && $tarifa->monto_por_fraccion && $tarifa->minutos_fraccion) {
            $unit = $money($tarifa->monto_por_fraccion).' por cada '.self::tiempo((int)$tarifa->minutos_fraccion);
            $detail = 'Tiempo elegido: '.$duration.'. Las fracciones iniciadas se cobran completas.';
        } else {
            $unit = $money($tarifa->monto_por_hora).' por hora';
            $detail = 'Tiempo elegido: '.$duration.'.';
        }
        if (!$fixed && $calculo['minutos_cobro'] > $calculo['duracion_minutos']) {
            $detail .= ' Esta tarifa cobra un mínimo de '.self::tiempo((int)$tarifa->tiempo_minimo_minutos).'.';
        }
        $detail .= ' Precio calculado para reservar ahora; revisa el total antes de confirmar.';
        $fraction = max(1, (int)($tarifa->minutos_fraccion ?? 60));
        $excess = (float)$calculo['penalidad_por_fraccion'] > 0
            ? $money($calculo['penalidad_por_fraccion']).' por cada '.self::tiempo($fraction).' adicionales o fracción, después de '.(int)$calculo['tolerancia_minutos'].' minutos de tolerancia.'
            : 'Esta tarifa no tiene un recargo por exceso configurado.';
        return ['precio_unitario' => $unit, 'detalle_precio' => $detail, 'exceso' => $excess];
    }
}
