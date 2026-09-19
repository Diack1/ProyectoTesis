<?php

namespace App\Services;

use App\Models\Espacio;
use App\Models\Estadia;
use App\Models\LecturaSensor;
use App\Models\RegistroOcupacion;
use App\Models\Sensor;
use Illuminate\Support\Facades\DB;

class LecturaSensorService
{
    public function registrar(Sensor $sensor, array $data, string $token): array
    {
        return DB::transaction(function () use ($sensor, $data, $token) {
            $espacio = Espacio::lockForUpdate()->findOrFail($sensor->espacio_id);
            $sensor = Sensor::lockForUpdate()->findOrFail($sensor->id);
            abort_unless($sensor->integracion_iot && $sensor->token_hash && hash_equals($sensor->token_hash, hash('sha256', $token)), 401);
            abort_unless($sensor->estado === 'activo' && $espacio->activo, 409, 'Sensor o espacio inactivo.');
            $previous = LecturaSensor::find($data['evento_id']);
            $distancia = $data['distancia_cm'] === null ? null : round((float) $data['distancia_cm'], 2);
            if ($previous) {
                abort_unless($previous->sensor_id === $sensor->id && ($previous->distancia_cm === null ? $distancia === null : $distancia !== null && (float) $previous->distancia_cm === $distancia), 409, 'El identificador pertenece a otra lectura.');

                return ['ok' => true, 'duplicada' => true, 'resultado' => $previous->resultado];
            }
            $valida = $distancia !== null && $distancia >= $sensor->distancia_min_cm && $distancia <= $sensor->distancia_max_cm;
            $vigente = $sensor->ultima_comunicacion_at && $sensor->ultima_comunicacion_at->gte(now()->subSeconds($sensor->segundos_sin_senal));
            $sensor->ultima_comunicacion_at = now();
            $sensor->ultima_distancia_cm = $distancia;
            $resultado = 'lectura_invalida';
            if (! $valida || ! $sensor->calibrado) {
                $sensor->estado_candidato = null;
                $sensor->cantidad_candidata = 0;
                if ($valida) {
                    $resultado = 'sin_calibrar';
                }
            } else {
                $candidate = $distancia <= $sensor->umbral_ocupado_cm ? 'ocupado' : ($distancia >= $sensor->umbral_libre_cm ? 'libre' : null);
                if (! $candidate) {
                    $sensor->estado_candidato = null;
                    $sensor->cantidad_candidata = 0;
                    $resultado = 'zona_intermedia';
                } else {
                    $sensor->cantidad_candidata = $vigente && $sensor->estado_candidato === $candidate ? min($sensor->lecturas_confirmacion, $sensor->cantidad_candidata + 1) : 1;
                    $sensor->estado_candidato = $candidate;
                    $resultado = 'confirmando';
                    if ($sensor->cantidad_candidata >= $sensor->lecturas_confirmacion) {
                        $sensor->estado_estable = $candidate;
                        $sensor->ultima_lectura_valida_at = now();
                        $resultado = $candidate;
                        if ($espacio->modo_monitoreo === 'sensor' && $espacio->getRawOriginal('estado_actual') !== 'mantenimiento') {
                            if ($espacio->getRawOriginal('estado_actual') !== $candidate) {
                                $espacio->update(['estado_actual' => $candidate]);
                                RegistroOcupacion::create(['espacio_id' => $espacio->id, 'sensor_id' => $sensor->id, 'estado_detectado' => $candidate, 'distancia_cm' => $distancia, 'fecha_hora' => now(), 'origen' => 'sensor_iot']);
                            }
                            if ($candidate === 'ocupado') {
                                $estadia = Estadia::activas()->where('espacio_id', $espacio->id)->lockForUpdate()->first();
                                if ($estadia && ! $estadia->inicio_cobro) {
                                    $estadia->update(['inicio_cobro' => now(), 'fuente_inicio' => 'sensor']);
                                }
                            }
                        }
                    }
                }
            }
            // Candidate continuity uses consecutive communication; freshness remains tied to a confirmed state.
            $sensor->save();
            LecturaSensor::create(['evento_id' => $data['evento_id'], 'sensor_id' => $sensor->id, 'distancia_cm' => $distancia, 'valida' => $valida, 'resultado' => $resultado, 'recibido_at' => now()]);

            return ['ok' => true, 'duplicada' => false, 'resultado' => $resultado, 'estado_estable' => $sensor->estado_estable, 'control' => $espacio->modo_monitoreo];
        });
    }
}
