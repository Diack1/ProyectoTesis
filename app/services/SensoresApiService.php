<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

class SensoresApiService
{
    private const TIMEZONE = 'America/Lima';
    private const MAX_LECTURA_MINUTOS = 10;

    public function obtenerEstado(): array
    {
        $url = config('services.sensores.url');

        if (!is_string($url) || trim($url) === '') {
            Log::error('Sensores API sin URL configurada.');

            return $this->respuestaError('configuracion', 'No se pudo consultar el estado de sensores en este momento.');
        }

        try {
            $request = Http::acceptJson()
                ->timeout(10);

            $token = config('services.sensores.token');

            if (is_string($token) && trim($token) !== '') {
                $request = $request->withToken($token);
            }

            $response = $request->get($url);
        } catch (ConnectionException $exception) {
            Log::error('Sensores API error de conexion.', [
                'message' => $exception->getMessage(),
            ]);

            return $this->respuestaError('conexion', 'No se pudo actualizar el estado de sensores.');
        } catch (Throwable $exception) {
            Log::error('Sensores API error inesperado.', [
                'message' => $exception->getMessage(),
            ]);

            return $this->respuestaError('inesperado', 'No se pudo actualizar el estado de sensores.');
        }

        if (!$response->successful()) {
            Log::error('Sensores API devolvio error HTTP.', [
                'status' => $response->status(),
            ]);

            return $this->respuestaError(
                'http_' . $response->status(),
                $this->mensajeParaEstadoHttp($response->status())
            );
        }

        try {
            $payload = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            Log::error('Sensores API devolvio JSON invalido.', [
                'message' => $exception->getMessage(),
            ]);

            return $this->respuestaError('json_invalido', 'La API de sensores no devolvio datos validos.');
        }

        if (!is_array($payload)) {
            Log::error('Sensores API devolvio una respuesta no estructurada.');

            return $this->respuestaError('respuesta_invalida', 'La API de sensores no devolvio datos validos.');
        }

        if (($payload['ok'] ?? null) !== true) {
            Log::error('Sensores API respondio ok=false.');

            return $this->respuestaError('ok_false', 'La API de sensores no confirmo la lectura.');
        }

        if (!array_key_exists('sensores', $payload) || !is_array($payload['sensores'])) {
            Log::error('Sensores API no incluyo el arreglo sensores.');

            return $this->respuestaError('sensores_inexistente', 'La API de sensores no devolvio la lista esperada.');
        }

        return [
            'ok' => true,
            'error' => null,
            'message' => count($payload['sensores']) === 0
                ? 'La API respondio correctamente, pero no hay sensores registrados.'
                : null,
            'sensores' => $this->normalizarSensores($payload['sensores']),
            'fetched_at' => $this->formatearFecha(now(self::TIMEZONE)->toIso8601String()),
        ];
    }

    public function sensorPorCodigo(string $codigo): ?array
    {
        $resultado = $this->obtenerSensorPorCodigo($codigo);

        return $resultado['sensor'];
    }

    public function obtenerSensorPorCodigo(string $codigo): array
    {
        $estado = $this->obtenerEstado();

        if (!$estado['ok']) {
            return [
                'ok' => false,
                'encontrado' => false,
                'disponible' => false,
                'estado' => 'api_no_disponible',
                'message' => 'El sensor no pudo confirmar que el espacio este libre.',
                'sensor' => null,
            ];
        }

        foreach ($estado['sensores'] as $sensor) {
            if (($sensor['codigo'] ?? null) !== $codigo) {
                continue;
            }

            if (($sensor['datos_disponibles'] ?? null) !== true) {
                return [
                    'ok' => true,
                    'encontrado' => true,
                    'disponible' => false,
                    'estado' => 'sin_datos',
                    'message' => 'El sensor no pudo confirmar que el espacio este libre.',
                    'sensor' => $sensor,
                ];
            }

            if (($sensor['ocupado'] ?? null) === true) {
                return [
                    'ok' => true,
                    'encontrado' => true,
                    'disponible' => false,
                    'estado' => 'ocupado',
                    'message' => 'El espacio acaba de ser ocupado y ya no esta disponible.',
                    'sensor' => $sensor,
                ];
            }

            if (($sensor['ocupado'] ?? null) === false) {
                return [
                    'ok' => true,
                    'encontrado' => true,
                    'disponible' => true,
                    'estado' => 'libre',
                    'message' => 'El sensor confirmo que el espacio esta libre.',
                    'sensor' => $sensor,
                ];
            }
        }

        return [
            'ok' => true,
            'encontrado' => false,
            'disponible' => false,
            'estado' => 'sensor_no_encontrado',
            'message' => 'No se encontro el sensor asociado al espacio.',
            'sensor' => null,
        ];
    }

    private function normalizarSensores(array $sensores): array
    {
        $normalizados = [];

        foreach ($sensores as $indice => $sensor) {
            if (!is_array($sensor)) {
                Log::error('Sensores API incluyo un sensor no estructurado.', [
                    'indice' => $indice,
                ]);

                continue;
            }

            $ocupado = $sensor['ocupado'] ?? null;
            $ocupadoValido = is_bool($ocupado);
            $codigo = $sensor['codigo'] ?? null;
            $ultimaLecturaAt = is_string($sensor['ultima_lectura_at'] ?? null) ? $sensor['ultima_lectura_at'] : null;
            $lecturaReciente = $this->lecturaEsReciente($ultimaLecturaAt);
            $datosDisponibles = $ocupadoValido && $lecturaReciente;

            if (!is_string($codigo) || trim($codigo) === '') {
                $codigo = isset($sensor['id'])
                    ? 'SENSOR_' . $sensor['id']
                    : 'SENSOR_SIN_CODIGO_' . ($indice + 1);
            }

            $distancia = $sensor['distancia_cm'] ?? null;

            $normalizados[] = [
                'id' => $sensor['id'] ?? null,
                'codigo' => trim($codigo),
                'ocupado' => $ocupadoValido ? $ocupado : null,
                'estado' => $datosDisponibles ? ($ocupado ? 'ocupado' : 'libre') : 'sin_datos',
                'estado_texto' => $datosDisponibles ? ($ocupado ? 'Ocupado' : 'Libre') : 'Estado desconocido',
                'datos_disponibles' => $datosDisponibles,
                'lectura_reciente' => $lecturaReciente,
                'conectado' => $datosDisponibles,
                'conexion_texto' => $datosDisponibles ? 'Datos disponibles' : 'Sin conexion',
                'distancia_cm' => is_numeric($distancia) ? (float) $distancia : null,
                'distancia_texto' => is_numeric($distancia) ? $this->formatearDistancia((float) $distancia) : null,
                'ultima_lectura_at' => $ultimaLecturaAt,
                'ultima_lectura_formateada' => $this->formatearFecha($ultimaLecturaAt),
            ];
        }

        return $normalizados;
    }

    private function lecturaEsReciente(?string $fecha): bool
    {
        if (!$fecha) {
            return false;
        }

        try {
            return CarbonImmutable::parse($fecha)
                ->greaterThanOrEqualTo(now(self::TIMEZONE)->subMinutes(self::MAX_LECTURA_MINUTOS));
        } catch (Throwable $exception) {
            Log::error('Sensores API incluyo una fecha invalida para validar vigencia.', [
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function formatearFecha(mixed $fecha): ?string
    {
        if (!is_string($fecha) || trim($fecha) === '') {
            return null;
        }

        try {
            $fechaLima = CarbonImmutable::parse($fecha)
                ->setTimezone(self::TIMEZONE);
        } catch (Throwable $exception) {
            Log::error('Sensores API incluyo una fecha invalida.', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        $meridiano = $fechaLima->format('A') === 'AM' ? 'a. m.' : 'p. m.';

        return $fechaLima->format('d/m/Y h:i ') . $meridiano;
    }

    private function formatearDistancia(float $distancia): string
    {
        return rtrim(rtrim(number_format($distancia, 2, '.', ''), '0'), '.') . ' cm';
    }

    private function mensajeParaEstadoHttp(int $status): string
    {
        return match ($status) {
            401, 403 => 'No se pudo autenticar la consulta de sensores.',
            404 => 'No se encontro el servicio de sensores configurado.',
            500 => 'El servicio de sensores no esta disponible temporalmente.',
            default => 'No se pudo actualizar el estado de sensores.',
        };
    }

    private function respuestaError(string $error, string $message): array
    {
        return [
            'ok' => false,
            'error' => $error,
            'message' => $message,
            'sensores' => [],
            'fetched_at' => $this->formatearFecha(now(self::TIMEZONE)->toIso8601String()),
        ];
    }
}
