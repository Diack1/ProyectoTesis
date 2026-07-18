<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tarifas', function (Blueprint $table) {
            $table->foreignId('vehiculo_tipo_id')
                ->nullable()
                ->after('id')
                ->constrained('vehiculo_tipos')
                ->nullOnDelete();

            $table->enum('tipo_tarifa', [
                'por_hora',
                'fraccion',
                'diaria',
                'nocturna'
            ])->default('por_hora')->after('nombre');

            $table->decimal('monto_base', 8, 2)->default(0)->after('tipo_tarifa');

            $table->decimal('monto_por_fraccion', 8, 2)
                ->nullable()
                ->after('monto_por_hora');

            $table->integer('minutos_fraccion')
                ->nullable()
                ->after('monto_por_fraccion');

            $table->integer('tiempo_minimo_minutos')
                ->default(60)
                ->after('minutos_fraccion');

            $table->integer('tolerancia_minutos')
                ->default(0)
                ->after('tiempo_minimo_minutos');

            $table->decimal('penalidad_por_fraccion', 8, 2)
                ->default(0)
                ->after('tolerancia_minutos');

            $table->time('hora_inicio')
                ->nullable()
                ->after('penalidad_por_fraccion');

            $table->time('hora_fin')
                ->nullable()
                ->after('hora_inicio');

            $table->integer('prioridad')
                ->default(1)
                ->after('activo');
        });
    }

    public function down(): void
    {
        Schema::table('tarifas', function (Blueprint $table) {
            $table->dropForeign(['vehiculo_tipo_id']);

            $table->dropColumn([
                'vehiculo_tipo_id',
                'tipo_tarifa',
                'monto_base',
                'monto_por_fraccion',
                'minutos_fraccion',
                'tiempo_minimo_minutos',
                'tolerancia_minutos',
                'penalidad_por_fraccion',
                'hora_inicio',
                'hora_fin',
                'prioridad',
            ]);
        });
    }
};
