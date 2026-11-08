<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('configuracion_pagos', function (Blueprint $table) {
            $table->unsignedSmallInteger('cantidad_casilleros')->default(12);
        });
        Schema::table('estadias', function (Blueprint $table) {
            $table->string('casillero_activo', 30)->nullable()->unique();
        });
    }

    public function down(): void {
        Schema::table('estadias', fn (Blueprint $table) => $table->dropColumn('casillero_activo'));
        Schema::table('configuracion_pagos', fn (Blueprint $table) => $table->dropColumn('cantidad_casilleros'));
    }
};
