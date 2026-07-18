<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('espacio_vehiculo_tipo', function (Blueprint $table) {
            $table->id();

            $table->foreignId('espacio_id')
                ->constrained('espacios')
                ->onDelete('cascade');

            $table->foreignId('vehiculo_tipo_id')
                ->constrained('vehiculo_tipos')
                ->onDelete('cascade');

            $table->timestamps();

            $table->unique(['espacio_id', 'vehiculo_tipo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('espacio_vehiculo_tipo');
    }
};
