<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function () {
            $types = DB::table('vehiculo_tipos')->where('activo', true)->pluck('id');
            foreach (range(1, 41) as $n) {
                $space = DB::table('espacios')->where('codigo', 'T'.str_pad((string) $n, 2, '0', STR_PAD_LEFT))->first();
                if (! $space) { continue; }
                DB::table('espacios')->where('id', $space->id)->update(['activo' => true]);
                foreach ($types as $type) {
                    DB::table('espacio_vehiculo_tipo')->updateOrInsert(['espacio_id' => $space->id, 'vehiculo_tipo_id' => $type], ['created_at' => now(), 'updated_at' => now()]);
                }
            }
        });
    }

    public function down(): void
    {
        // Do not deactivate spaces that may now have reservations or vehicles.
    }
};
