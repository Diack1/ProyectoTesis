<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sensores', function (Blueprint $t) {
            $t->boolean('integracion_iot')->default(false);
            $t->string('token_hash', 64)->nullable();
            $t->decimal('umbral_ocupado_cm', 8, 2)->nullable();
            $t->decimal('umbral_libre_cm', 8, 2)->nullable();
            $t->decimal('distancia_min_cm', 8, 2)->default(1);
            $t->decimal('distancia_max_cm', 8, 2)->default(450);
            $t->unsignedTinyInteger('lecturas_confirmacion')->default(3);
            $t->unsignedSmallInteger('segundos_sin_senal')->default(60);
            $t->timestamp('ultima_comunicacion_at')->nullable();
            $t->timestamp('ultima_lectura_valida_at')->nullable();
            $t->decimal('ultima_distancia_cm', 8, 2)->nullable();
            $t->string('estado_estable', 15)->nullable();
            $t->string('estado_candidato', 15)->nullable();
            $t->unsignedTinyInteger('cantidad_candidata')->default(0);
        });
        Schema::create('lecturas_sensores', function (Blueprint $t) {
            $t->uuid('evento_id')->primary();
            $t->foreignId('sensor_id')->constrained('sensores')->cascadeOnDelete();
            $t->decimal('distancia_cm', 8, 2)->nullable();
            $t->boolean('valida');
            $t->string('resultado', 30);
            $t->timestamp('recibido_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lecturas_sensores');
        Schema::table('sensores', fn (Blueprint $t) => $t->dropColumn(['integracion_iot', 'token_hash', 'umbral_ocupado_cm', 'umbral_libre_cm', 'distancia_min_cm', 'distancia_max_cm', 'lecturas_confirmacion', 'segundos_sin_senal', 'ultima_comunicacion_at', 'ultima_lectura_valida_at', 'ultima_distancia_cm', 'estado_estable', 'estado_candidato', 'cantidad_candidata']));
    }
};
