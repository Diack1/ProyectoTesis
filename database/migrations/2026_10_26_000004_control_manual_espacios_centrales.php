<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $codes = [];
        foreach (range(27, 41) as $n) {
            $codes[] = 'T'.$n;
            $codes[] = 'E'.$n;
        }
        // Keep occupancy, activation, reservations and historical sensor records.
        // Sensor ingestion already rejects spaces whose mode is manual.
        DB::table('espacios')->whereIn('codigo', $codes)->update([
            'modo_monitoreo' => 'manual', 'incluido_estudio' => false,
        ]);
    }

    public function down(): void
    {
        // Do not invent sensor assignments when rolling back.
    }
};
