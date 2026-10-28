<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->boolean('pago_al_llegar')->default(false);
        });
        // Existing reservation and stay snapshots keep their agreed conditions.
        DB::table('tarifas')->whereIn('vehiculo_tipo_id', DB::table('vehiculo_tipos')->select('id')->whereIn('codigo', ['auto', 'camioneta']))
            ->where('activo', true)->update(['tolerancia_minutos' => 5]);
    }

    public function down(): void
    {
        Schema::table('reservas', fn (Blueprint $table) => $table->dropColumn('pago_al_llegar'));
    }
};
