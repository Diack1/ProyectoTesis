<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registros_ocupacion', function (Blueprint $table) {
            $table->id();

            $table->foreignId('espacio_id')
                ->constrained('espacios')
                ->onDelete('cascade');

            $table->foreignId('sensor_id')
                ->nullable()
                ->constrained('sensores')
                ->nullOnDelete();

            $table->enum('estado_detectado', ['libre', 'ocupado', 'reservado', 'mantenimiento']);
            $table->decimal('distancia_cm', 8, 2)->nullable();

            $table->dateTime('fecha_hora');
            $table->string('origen')->default('simulado'); // simulado, sensor, manual

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registros_ocupacion');
    }
};
