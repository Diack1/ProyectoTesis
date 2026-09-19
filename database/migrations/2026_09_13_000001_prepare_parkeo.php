<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->string('role')->default('user')->change());
        Schema::table('espacios', function (Blueprint $t) {
            $t->boolean('incluido_estudio')->default(false);
            $t->string('modo_monitoreo')->default('manual');
        });
        Schema::table('pagos', function (Blueprint $t) {
            $t->timestamp('enviado_at')->nullable();
            $t->timestamp('revisado_at')->nullable();
            $t->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $t->text('motivo_revision')->nullable();
            $t->string('operacion_unica')->nullable()->unique();
        });
        Schema::create('configuracion_pagos', function (Blueprint $t) {
            $t->id();
            $t->string('titular')->nullable();
            $t->string('telefono', 30)->nullable();
            $t->string('qr_yape')->nullable();
            $t->string('qr_plin')->nullable();
            $t->unsignedInteger('minutos_pago')->default(10);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_pagos');
        Schema::table('pagos', function (Blueprint $t) {
            $t->dropForeign(['revisado_por']);
            $t->dropColumn(['enviado_at', 'revisado_at', 'revisado_por', 'motivo_revision', 'operacion_unica']);
        });
        Schema::table('espacios', fn (Blueprint $t) => $t->dropColumn(['incluido_estudio', 'modo_monitoreo']));
    }
};
