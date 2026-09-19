<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cliente_vehiculos', function (Blueprint $table) {
            $table->id();
            $table->string('placa', 10)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nombre', 150);
            $table->string('telefono', 40)->nullable();
            $table->boolean('activo')->default(true);
            $table->foreignId('registrado_por')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_vehiculos');
    }
};
