<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class PlacaVisionService
{
    public function disponible(): bool
    {
        return is_file(config('vision.python')) && is_file(base_path('vision/models/manifest.json'));
    }

    public function analizar(string $path): array
    {
        if (! $this->disponible()) {
            throw ValidationException::withMessages(['imagen' => 'El reconocimiento local todavía no está preparado. Consulta la guía de instalación de visión.']);
        }

        // One CPU inference at a time, across operators. No shell interpolation of uploads.
        $lock = Cache::lock('vision-inferencia', config('vision.timeout') + 10);
        if (! $lock->get()) {
            throw ValidationException::withMessages(['imagen' => 'Hay otra fotografía en análisis. Intenta nuevamente en unos segundos.']);
        }

        try {
            $environment = PHP_OS_FAMILY === 'Windows' ? array_filter([
                'SystemRoot' => config('vision.system_root'), 'USERPROFILE' => config('vision.user_profile'),
            ]) : null;
            $process = new Process([config('vision.python'), base_path('vision/recognize.py'), '--image', $path], base_path('vision'), $environment);
            $process->setTimeout(config('vision.timeout'));
            $process->run();
            if (! $process->isSuccessful()) {
                $diagnostic = json_decode(trim($process->getErrorOutput()), true);
                Log::warning('Fallo de inferencia de placas', [
                    'exit_code' => $process->getExitCode(),
                    'error_type' => isset($diagnostic['error']) && preg_match('/\A[A-Za-z]{1,80}\z/', $diagnostic['error']) ? $diagnostic['error'] : 'ProcessError',
                ]);
                throw ValidationException::withMessages(['imagen' => 'No se pudo procesar la fotografía. Comprueba que la imagen sea legible y que los modelos locales estén instalados.']);
            }
            $result = json_decode($process->getOutput(), true);
            if (! is_array($result) || ($result['version'] ?? null) !== 1 || ! is_array($result['candidates'] ?? null) || count($result['candidates']) > 10) {
                throw ValidationException::withMessages(['imagen' => 'El reconocimiento devolvió una respuesta no válida.']);
            }

            return $result;
        } catch (ProcessTimedOutException $e) {
            throw ValidationException::withMessages(['imagen' => 'El análisis tardó demasiado. Prueba una foto más cercana del vehículo.']);
        } finally {
            $lock->release();
        }
    }
}
