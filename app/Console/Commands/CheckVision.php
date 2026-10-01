<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class CheckVision extends Command
{
    protected $signature = 'vision:check';
    protected $description = 'Comprueba Python y carga los modelos de placas sin subir fotografías ni descargar archivos';

    public function handle(): int
    {
        $missing = false;
        foreach ([config('vision.python') => 'Python configurado en VISION_PYTHON',
            base_path('vision/models/manifest.json') => 'Modelos preparados (vision/models/manifest.json)'] as $path => $label) {
            if (!is_file($path)) {
                $this->error($label.': no encontrado.');
                $missing = true;
            } else {
                $this->info($label.': encontrado.');
            }
        }
        if ($missing) {
            $this->warn('Instala el motor durante la construcción del despliegue. Consulta vision/README.md.');
            return self::FAILURE;
        }
        $environment = PHP_OS_FAMILY === 'Windows' ? array_filter([
            'SystemRoot' => config('vision.system_root'), 'USERPROFILE' => config('vision.user_profile'),
        ]) : null;
        try {
            $process = new Process([config('vision.python'), base_path('vision/recognize.py'), '--check'], base_path('vision'), $environment);
            $process->setTimeout(config('vision.timeout'));
            $process->run();
            $result = json_decode($process->getOutput(), true);
            if (!$process->isSuccessful() || ($result['ready'] ?? false) !== true) {
                $this->error('El motor no pudo cargar: revisa dependencias, integridad de los modelos y memoria del servidor.');
                return self::FAILURE;
            }
        } catch (\Throwable $e) {
            $this->error('No se pudo ejecutar Python dentro del plazo configurado. Revisa permisos y recursos del servidor.');
            return self::FAILURE;
        }
        $this->info('Motor listo: detector y lector de placas cargados correctamente.');
        return self::SUCCESS;
    }
}
