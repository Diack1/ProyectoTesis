<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        Schema::table('espacios', fn (Blueprint $t) => $t->string('estado_actual')->default('libre')->change());

        Schema::table('registros_ocupacion', fn (Blueprint $t) => $t->string('estado_detectado')->change());
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

        Schema::table('espacios', fn (Blueprint $t) => $t->string('estado_actual')->default('libre')->change());

        Schema::table('registros_ocupacion', fn (Blueprint $t) => $t->string('estado_detectado')->change());
    }
};
