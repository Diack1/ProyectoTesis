<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function () {
            // Preserve the original left row (E21–27) and office row (E28–30).
            // Migration 000001 changed E codes to T, without changing their IDs.
            $map = [];
            foreach (range(1, 6) as $old) { $map[$old] = 17 - $old; }
            foreach (range(10, 19) as $old) { $map[$old] = 20 - $old; }
            foreach (range(21, 27) as $old) { $map[$old] = $old - 4; }
            foreach (range(28, 30) as $old) { $map[$old] = $old - 4; }
            foreach ([7 => 27, 8 => 28, 9 => 29, 20 => 30] as $old => $new) { $map[$old] = $new; }
            $rows = DB::table('espacios')->whereIn('codigo', array_map(fn ($n) => 'T'.$n, array_keys($map)))->get();
            foreach ($rows as $row) {
                DB::table('espacios')->where('id', $row->id)->update(['codigo' => 'TMP-T'.$row->id]);
            }
            foreach ($rows as $row) {
                $number = $map[(int) substr($row->codigo, 1)];
                DB::table('espacios')->where('id', $row->id)->update(['codigo' => 'T'.$number]);
            }
            if (DB::table('espacios')->exists()) {
                $types = DB::table('vehiculo_tipos')->where('activo', true)->pluck('id');
                foreach (range(1, 41) as $n) {
                    if (DB::table('espacios')->where('codigo', 'T'.$n)->exists()) { continue; }
                    $id = DB::table('espacios')->insertGetId(['codigo' => 'T'.$n, 'descripcion' => 'Techo '.$n,
                        'estado_actual' => 'libre', 'activo' => true, 'modo_monitoreo' => 'manual',
                        'incluido_estudio' => false, 'created_at' => now(), 'updated_at' => now()]);
                    foreach ($types as $type) {
                        DB::table('espacio_vehiculo_tipo')->insert(['espacio_id' => $id, 'vehiculo_tipo_id' => $type, 'created_at' => now(), 'updated_at' => now()]);
                    }
                }
            }
        });
    }

    public function down(): void
    {
        // Do not delete reservable spaces or undo codes after tickets have used them.
    }
};
