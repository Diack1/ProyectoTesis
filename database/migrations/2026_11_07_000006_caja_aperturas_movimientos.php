<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('caja_aperturas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->default('Caja 01');
            $table->decimal('monto_inicial', 12, 2);
            $table->foreignId('operador_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('inicio_at');
            $table->unsignedTinyInteger('abierta_unica')->nullable()->unique();
            $table->timestamps();
        });
        Schema::create('caja_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caja_apertura_id')->constrained('caja_aperturas')->restrictOnDelete();
            $table->foreignId('operador_id')->constrained('users')->restrictOnDelete();
            $table->enum('tipo', ['ingreso', 'egreso']);
            $table->enum('metodo_pago', ['efectivo', 'tarjeta', 'yape', 'plin', 'manual']);
            $table->decimal('monto', 12, 2);
            $table->string('motivo', 255);
            $table->timestamps();
        });
        Schema::table('pagos', fn (Blueprint $table) => $table->foreignId('caja_apertura_id')->nullable()->constrained('caja_aperturas')->restrictOnDelete());
        Schema::table('caja_cierres', function (Blueprint $table) {
            $table->foreignId('caja_apertura_id')->nullable()->unique()->constrained('caja_aperturas')->restrictOnDelete();
            $table->decimal('monto_inicial', 12, 2)->default(0);
            $table->decimal('ingresos_manuales', 12, 2)->default(0);
            $table->decimal('egresos_manuales', 12, 2)->default(0);
            $table->string('observacion', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('caja_cierres', fn (Blueprint $table) => $table->dropConstrainedForeignId('caja_apertura_id'));
        Schema::table('caja_cierres', fn (Blueprint $table) => $table->dropColumn(['monto_inicial', 'ingresos_manuales', 'egresos_manuales', 'observacion']));
        Schema::table('pagos', fn (Blueprint $table) => $table->dropConstrainedForeignId('caja_apertura_id'));
        Schema::dropIfExists('caja_movimientos');
        Schema::dropIfExists('caja_aperturas');
    }
};
