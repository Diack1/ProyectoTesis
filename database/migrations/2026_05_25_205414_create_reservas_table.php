<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->foreignId('espacio_id')
                ->constrained('espacios')
                ->onDelete('cascade');

            $table->string('codigo_reserva')->unique();

            $table->date('fecha_reserva');
            $table->time('hora_inicio');
            $table->time('hora_fin');

            $table->enum('estado', [
                'pendiente',
                'confirmada',
                'cancelada',
                'finalizada'
            ])->default('pendiente');

            $table->text('observacion')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservas');
    }
};