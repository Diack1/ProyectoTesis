<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CheckPayments extends Command
{
    protected $signature = 'payments:check';
    protected $description = 'Diagnóstico de solo lectura del esquema de reservas y pagos, sin mostrar credenciales';

    public function handle(): int
    {
        $failed = false;
        try {
            $driver = DB::connection()->getDriverName();
            $this->line('Motor: '.$driver);
            foreach ([
                'reservas' => ['estado', 'placa', 'reserva_inmediata', 'expires_at', 'pagado_at', 'duracion_minutos', 'minutos_fraccion_snapshot', 'tolerancia_llegada_minutos'],
                'pagos' => ['reserva_id', 'estadia_id', 'user_id', 'enviado_at', 'revisado_por', 'revisado_at', 'operacion_unica'],
                'security_events' => ['actor_id', 'resource_type', 'resource_id', 'action', 'changed_fields', 'requires_attention'],
                'configuracion_pagos' => ['minutos_pago', 'qr_yape', 'qr_plin'],
            ] as $table => $columns) {
                $missing = array_diff($columns, Schema::getColumnListing($table));
                $this->line($table.': '.($missing ? 'faltan '.implode(', ', $missing) : 'columnas requeridas presentes'));
                $failed = $failed || (bool) $missing;
            }
            if ($driver === 'pgsql') {
                $constraints = DB::select("SELECT pg_get_constraintdef(oid) AS definition FROM pg_constraint WHERE conrelid = 'reservas'::regclass AND contype = 'c'");
                foreach ($constraints as $constraint) {
                    if (str_contains($constraint->definition, 'estado') && ! str_contains($constraint->definition, "'pendiente_pago'")) {
                        $this->error('CHECK de reservas.estado incompatible con pendiente_pago. Ejecuta las migraciones.');
                        $failed = true;
                    }
                }
            }
            $this->line('Comprobantes: disco local privado; directorio '.(is_writable(storage_path('app/private')) ? 'escribible' : 'no escribible o ausente'));
            $this->line('Resend: '.(filled(config('services.resend.key')) ? 'clave configurada' : 'clave no configurada'));
            $this->line('Este diagnóstico no envía correos, no crea reservas y no confirma pagos.');
        } catch (\Throwable $e) {
            $this->error('No se pudo completar el diagnóstico. Tipo: '.get_class($e).'. Revisa conexión y migraciones.');
            return self::FAILURE;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
