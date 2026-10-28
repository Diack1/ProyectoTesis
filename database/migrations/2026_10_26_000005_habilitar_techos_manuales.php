<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function () {
            $spaces = DB::table('espacios')->get();
            // Fresh installs receive their spaces from the normal seeders.
            if ($spaces->isEmpty()) { return; }
            foreach ($spaces as $space) {
                if (! preg_match('/^[ET]0*([1-9][0-9]*)$/', $space->codigo, $match)) { continue; }
                $code = 'T'.str_pad($match[1], 2, '0', STR_PAD_LEFT);
                if (DB::table('espacios')->where('codigo', $code)->where('id', '!=', $space->id)->exists()) {
                    throw new RuntimeException('Hay dos espacios con el mismo número. Revisa sus códigos antes de migrar.');
                }
                DB::table('espacios')->where('id', $space->id)->update(['codigo' => $code]);
            }
            $types = DB::table('vehiculo_tipos')->where('activo', true)->pluck('id');
            foreach (range(27, 41) as $n) {
                $code = 'T'.$n;
                $id = DB::table('espacios')->where('codigo', $code)->value('id');
                if (! $id) {
                    $id = DB::table('espacios')->insertGetId(['codigo' => $code, 'descripcion' => 'Techo '.$n,
                        'estado_actual' => 'libre', 'activo' => true, 'modo_monitoreo' => 'manual',
                        'incluido_estudio' => false, 'created_at' => now(), 'updated_at' => now()]);
                }
                // Never clear an occupied/reserved/maintenance state to enable booking.
                DB::table('espacios')->where('id', $id)->update(['activo' => true, 'modo_monitoreo' => 'manual', 'incluido_estudio' => false]);
                foreach ($types as $type) {
                    DB::table('espacio_vehiculo_tipo')->updateOrInsert(['espacio_id' => $id, 'vehiculo_tipo_id' => $type], ['created_at' => now(), 'updated_at' => now()]);
                }
            }
        });
    }

    public function down(): void
    {
        // Keep codes and spaces already used by reservations and tickets.
    }
};
