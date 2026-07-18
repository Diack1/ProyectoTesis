<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reserva_id')
                ->constrained('reservas')
                ->onDelete('cascade');

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->string('codigo_pago')->unique();

            $table->enum('metodo_pago', [
                'yape',
                'plin',
                'tarjeta',
                'efectivo',
                'manual',
                'simulado'
            ])->default('simulado');

            $table->decimal('monto', 8, 2);

            $table->enum('estado', [
                'pendiente',
                'aprobado',
                'rechazado',
                'cancelado',
                'reembolsado'
            ])->default('pendiente');

            $table->string('referencia_pago')->nullable();
            $table->string('comprobante')->nullable();
            $table->dateTime('pagado_at')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
