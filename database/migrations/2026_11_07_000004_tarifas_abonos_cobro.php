<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tarifas', function (Blueprint $t) {
            $t->string('tipo_tarifa', 24)->default('por_hora')->change();
            $t->boolean('cobro_intervalos')->default(false);
        });
        Schema::table('abonados', function (Blueprint $t) {
            $t->string('dni', 8)->nullable()->unique();
        });
        Schema::table('abonos', function (Blueprint $t) {
            $t->foreignId('tarifa_id')->nullable()->constrained('tarifas')->restrictOnDelete();
            $t->foreignId('cliente_cobro_id')->nullable()->constrained('clientes_cobro')->restrictOnDelete();
            $t->unsignedTinyInteger('meses')->default(1);
            $t->decimal('monto_base', 10, 2)->nullable();
            $t->decimal('descuento_porcentaje', 5, 2)->default(0);
            $t->decimal('descuento_monto', 10, 2)->default(0);
            $t->string('motivo_descuento', 255)->nullable();
            $t->string('tipo_comprobante', 7)->nullable();
            $t->string('tipo_impresion', 12)->nullable();
            $t->decimal('efectivo_recibido', 10, 2)->nullable();
            $t->decimal('vuelto', 10, 2)->nullable();
        });
        Schema::table('pagos', fn (Blueprint $t) => $t->dropUnique('pagos_abono_id_unique'));
    }

    public function down(): void
    {
        Schema::table('pagos', fn (Blueprint $t) => $t->unique('abono_id'));
        Schema::table('abonos', function (Blueprint $t) {
            $t->dropConstrainedForeignId('tarifa_id');
            $t->dropConstrainedForeignId('cliente_cobro_id');
            $t->dropColumn(['meses', 'monto_base', 'descuento_porcentaje', 'descuento_monto', 'motivo_descuento',
                'tipo_comprobante', 'tipo_impresion', 'efectivo_recibido', 'vuelto']);
        });
        Schema::table('abonados', fn (Blueprint $t) => $t->dropColumn('dni'));
        Schema::table('tarifas', fn (Blueprint $t) => $t->dropColumn('cobro_intervalos'));
    }
};
