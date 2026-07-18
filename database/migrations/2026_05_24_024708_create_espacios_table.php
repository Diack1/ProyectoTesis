<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('espacios', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique(); // Ejemplo: E01, E02, E03
            $table->string('descripcion')->nullable();
            $table->enum('estado_actual', ['libre', 'ocupado', 'reservado', 'mantenimiento'])->default('libre');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('espacios');
    }
};
