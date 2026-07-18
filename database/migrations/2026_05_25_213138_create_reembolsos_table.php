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
        Schema::create('reembolsos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reserva_id')
                ->constrained('reservas')
                ->onDelete('cascade');

            $table->foreignId('pago_id')
                ->nullable()
                ->constrained('pagos')
                ->nullOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->decimal('monto', 8, 2);
            $table->text('motivo')->nullable();

            $table->enum('estado', [
                'solicitado',
                'aprobado',
                'rechazado',
                'procesado'
            ])->default('solicitado');

            $table->dateTime('solicitado_at')->nullable();
            $table->dateTime('procesado_at')->nullable();

            $table->foreignId('procesado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reembolsos');
    }
};
