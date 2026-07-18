<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE espacios MODIFY estado_actual ENUM('libre', 'ocupado', 'reservado', 'mantenimiento') DEFAULT 'libre'");

        DB::statement("ALTER TABLE registros_ocupacion MODIFY estado_detectado ENUM('libre', 'ocupado', 'reservado', 'mantenimiento')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("UPDATE espacios SET estado_actual = 'libre' WHERE estado_actual IN ('reservado', 'mantenimiento')");

        DB::statement("UPDATE registros_ocupacion SET estado_detectado = 'libre' WHERE estado_detectado IN ('reservado', 'mantenimiento')");

        DB::statement("ALTER TABLE espacios MODIFY estado_actual ENUM('libre', 'ocupado') DEFAULT 'libre'");

        DB::statement("ALTER TABLE registros_ocupacion MODIFY estado_detectado ENUM('libre', 'ocupado')");
    }
};
