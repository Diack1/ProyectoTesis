<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            // Rename in place: bookings, sensors and tickets keep their space IDs.
            $spaces = DB::table('espacios')->get();
            foreach ($spaces as $space) {
                if (preg_match('/^E0*([1-9][0-9]*)$/', $space->codigo, $m)) {
                    $code = 'T'.(int) $m[1];
                    if (DB::table('espacios')->where('codigo', $code)->where('id', '!=', $space->id)->exists()) {
                        throw new RuntimeException('Ya existe '.$code.'. Revisar la numeración antes de migrar.');
                    }
                    DB::table('espacios')->where('id', $space->id)->update(['codigo' => $code]);
                }
            }
            $auto = DB::table('vehiculo_tipos')->where('codigo', 'auto')->first();
            if ($auto) {
                if (!DB::table('vehiculo_tipos')->where('codigo', 'camioneta')->exists()) {
                    DB::table('vehiculo_tipos')->insert(['codigo' => 'camioneta', 'nombre' => 'Camioneta', 'activo' => true, 'created_at' => now(), 'updated_at' => now()]);
                }
                $camioneta = DB::table('vehiculo_tipos')->where('codigo', 'camioneta')->value('id');
                foreach (DB::table('espacio_vehiculo_tipo')->where('vehiculo_tipo_id', $auto->id)->pluck('espacio_id') as $spaceId) {
                    DB::table('espacio_vehiculo_tipo')->updateOrInsert(['espacio_id' => $spaceId, 'vehiculo_tipo_id' => $camioneta], ['created_at' => now(), 'updated_at' => now()]);
                }
            }
            $types = DB::table('vehiculo_tipos')->where('activo', true)->pluck('id');
            if ($spaces->isNotEmpty()) {
                for ($n = 1; $n <= 38; $n++) {
                    if (DB::table('espacios')->where('codigo', 'T'.$n)->exists()) {
                        continue;
                    }
                    $id = DB::table('espacios')->insertGetId(['codigo' => 'T'.$n, 'descripcion' => 'Techo '.$n,
                        'estado_actual' => 'libre', 'activo' => true, 'modo_monitoreo' => 'manual',
                        'incluido_estudio' => false, 'created_at' => now(), 'updated_at' => now()]);
                    foreach ($types as $type) {
                        DB::table('espacio_vehiculo_tipo')->insert(['espacio_id' => $id, 'vehiculo_tipo_id' => $type, 'created_at' => now(), 'updated_at' => now()]);
                    }
                }
            }
            $vehicles = DB::table('vehiculo_tipos')->whereIn('codigo', ['auto', 'camioneta'])->pluck('id');
            foreach ($vehicles as $vehicle) {
                // Existing bookings and stays retain their original tariff snapshots.
                DB::table('tarifas')->where('vehiculo_tipo_id', $vehicle)->update(['activo' => false]);
                DB::table('tarifas')->insert(['vehiculo_tipo_id' => $vehicle, 'nombre' => 'Hora o fracción · S/ 5',
                    'tipo_tarifa' => 'por_hora', 'monto_base' => 0, 'monto_por_hora' => 5,
                    'monto_por_fraccion' => 5, 'minutos_fraccion' => 60, 'tiempo_minimo_minutos' => 60,
                    'tolerancia_minutos' => 0, 'penalidad_por_fraccion' => 5, 'prioridad' => 100,
                    'activo' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
        });
    }

    public function down(): void
    {
        // Do not remove spaces or restore unknown prices after they have been used.
    }
};
