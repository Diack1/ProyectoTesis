<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('clientes_cobro', function (Blueprint $t) {
            $t->id();
            $t->string('nombres', 100);
            $t->string('apellidos', 100);
            $t->string('celular', 15)->nullable();
            $t->string('tipo_documento', 3);
            $t->string('numero_documento', 11)->unique();
            $t->timestamps();
        });
        Schema::table('estadias', function (Blueprint $t) {
            $t->foreignId('cliente_cobro_id')->nullable()->constrained('clientes_cobro')->restrictOnDelete();
            $t->string('tipo_comprobante', 7)->nullable();
            $t->string('tipo_impresion', 12)->nullable();
            $t->decimal('descuento_porcentaje', 5, 2)->default(0);
            $t->decimal('descuento_monto', 10, 2)->default(0);
            $t->string('motivo_descuento', 255)->nullable();
        });
        Schema::table('pagos', function (Blueprint $t) {
            $t->dropUnique('pagos_estadia_id_unique');
            $t->decimal('efectivo_recibido', 10, 2)->nullable();
            $t->decimal('vuelto', 10, 2)->nullable();
        });
        Schema::table('configuracion_pagos', function (Blueprint $t) {
            $t->string('razon_social')->nullable();
            $t->string('ruc', 11)->nullable();
            $t->string('direccion_fiscal')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_pagos', fn (Blueprint $t) => $t->dropColumn(['razon_social', 'ruc', 'direccion_fiscal']));
        Schema::table('pagos', function (Blueprint $t) {
            $t->dropColumn(['efectivo_recibido', 'vuelto']);
            $t->unique('estadia_id');
        });
        Schema::table('estadias', function (Blueprint $t) {
            $t->dropConstrainedForeignId('cliente_cobro_id');
            $t->dropColumn(['tipo_comprobante', 'tipo_impresion', 'descuento_porcentaje', 'descuento_monto', 'motivo_descuento']);
        });
        Schema::dropIfExists('clientes_cobro');
    }
};
