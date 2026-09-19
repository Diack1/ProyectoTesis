<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_pagos', function (Blueprint $t) {
            $t->unsignedSmallInteger('tolerancia_llegada')->default(15);
        });
        Schema::table('reservas', function (Blueprint $t) {
            $t->unsignedSmallInteger('tolerancia_llegada_minutos')->default(15);
            $t->unsignedInteger('minutos_fraccion_snapshot')->default(60);
            $t->timestamp('inasistencia_at')->nullable();
        });
        Schema::create('estadias', function (Blueprint $t) {
            $t->id();
            $t->string('codigo_ticket', 50)->unique();
            $t->string('placa', 15)->index();
            $t->string('placa_activa', 15)->nullable()->unique();
            $t->foreignId('espacio_id')->constrained('espacios')->restrictOnDelete();
            $t->foreignId('espacio_activo_id')->nullable()->unique()->constrained('espacios')->restrictOnDelete();
            $t->foreignId('vehiculo_tipo_id')->constrained('vehiculo_tipos')->restrictOnDelete();
            $t->foreignId('reserva_id')->nullable()->unique()->constrained('reservas')->restrictOnDelete();
            $t->foreignId('operador_ingreso_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('operador_salida_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->foreignId('inicio_confirmado_por')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('hora_ingreso')->index();
            $t->timestamp('inicio_cobro')->nullable();
            $t->timestamp('hora_salida')->nullable()->index();
            $t->timestamp('liquidado_hasta')->nullable();
            $t->string('fuente_inicio', 30);
            $t->text('motivo_inicio_manual')->nullable();
            $t->json('tarifa_snapshot');
            $t->unsignedInteger('minutos_contratados')->default(0);
            $t->decimal('monto_adelantado', 10, 2)->default(0);
            $t->unsignedInteger('minutos_cobrados')->nullable();
            $t->decimal('monto_total', 10, 2)->nullable();
            $t->decimal('monto_exceso', 10, 2)->default(0);
            $t->decimal('saldo_cobrado', 10, 2)->nullable();
            $t->decimal('efectivo_recibido', 10, 2)->nullable();
            $t->decimal('vuelto', 10, 2)->nullable();
            $t->timestamps();
        });
        Schema::table('pagos', function (Blueprint $t) {
            $t->foreignId('reserva_id')->nullable()->change();
            $t->foreignId('user_id')->nullable()->change();
            $t->foreignId('estadia_id')->nullable()->unique()->constrained('estadias')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $t) {
            $t->dropForeign(['estadia_id']);
            $t->dropColumn('estadia_id');
        });
        Schema::dropIfExists('estadias');
        Schema::table('reservas', fn (Blueprint $t) => $t->dropColumn(['tolerancia_llegada_minutos', 'minutos_fraccion_snapshot', 'inasistencia_at']));
        Schema::table('configuracion_pagos', fn (Blueprint $t) => $t->dropColumn('tolerancia_llegada'));
    }
};
