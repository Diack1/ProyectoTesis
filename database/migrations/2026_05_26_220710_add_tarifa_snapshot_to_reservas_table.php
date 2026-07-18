<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->foreignId('vehiculo_tipo_id')
                ->nullable()
                ->after('espacio_id')
                ->constrained('vehiculo_tipos')
                ->nullOnDelete();

            $table->foreignId('tarifa_id')
                ->nullable()
                ->after('vehiculo_tipo_id')
                ->constrained('tarifas')
                ->nullOnDelete();

            $table->string('tipo_vehiculo_nombre')
                ->nullable()
                ->after('tarifa_id');

            $table->string('tarifa_nombre')
                ->nullable()
                ->after('tipo_vehiculo_nombre');

            $table->string('tipo_tarifa')
                ->nullable()
                ->after('tarifa_nombre');

            $table->integer('tolerancia_minutos')
                ->default(0)
                ->after('monto_total');

            $table->decimal('penalidad_por_fraccion', 8, 2)
                ->default(0)
                ->after('tolerancia_minutos');

            $table->decimal('monto_penalidad', 8, 2)
                ->default(0)
                ->after('penalidad_por_fraccion');
        });
    }

    public function down(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->dropForeign(['vehiculo_tipo_id']);
            $table->dropForeign(['tarifa_id']);

            $table->dropColumn([
                'vehiculo_tipo_id',
                'tarifa_id',
                'tipo_vehiculo_nombre',
                'tarifa_nombre',
                'tipo_tarifa',
                'tolerancia_minutos',
                'penalidad_por_fraccion',
                'monto_penalidad',
            ]);
        });
    }
};
