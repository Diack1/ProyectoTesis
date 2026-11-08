<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $t) {
            $t->id();
            $t->string('origen_tipo', 16);
            $t->unsignedBigInteger('origen_id');
            $t->unsignedInteger('version')->default(1);
            $t->string('numero', 80)->unique();
            $t->dateTime('fecha');
            $t->string('cliente')->nullable();
            $t->string('documento', 20)->nullable();
            $t->string('tipo_comprobante', 7)->default('boleta');
            $t->string('tipo_impresion', 12)->default('detallado');
            $t->string('concepto')->nullable();
            $t->decimal('importe_base', 12, 2);
            $t->decimal('descuento', 12, 2)->default(0);
            $t->decimal('total', 12, 2);
            $t->string('estado', 12)->default('vigente');
            $t->foreignId('venta_anterior_id')->nullable()->constrained('ventas')->restrictOnDelete();
            $t->json('datos')->nullable();
            $t->string('sunat_estado', 20)->nullable();
            $t->string('sunat_referencia')->nullable();
            $t->timestamps();
            $t->unique(['origen_tipo', 'origen_id', 'version']);
            $t->index(['fecha', 'estado']);
        });
        Schema::table('pagos', fn (Blueprint $t) => $t->foreignId('venta_id')->nullable()->constrained('ventas')->restrictOnDelete());
        Schema::create('venta_correcciones', function (Blueprint $t) {
            $t->id();
            $t->foreignId('venta_id')->constrained('ventas')->restrictOnDelete();
            $t->foreignId('venta_nueva_id')->nullable()->constrained('ventas')->restrictOnDelete();
            $t->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $t->string('tipo', 24);
            $t->string('motivo', 500);
            $t->json('antes');
            $t->json('despues');
            $t->string('idempotencia', 80)->unique();
            $t->timestamps();
        });
        Schema::table('caja_movimientos', function (Blueprint $t) {
            $t->foreignId('venta_correccion_id')->nullable()->constrained('venta_correcciones')->restrictOnDelete();
            $t->boolean('ajuste_venta')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('caja_movimientos', function (Blueprint $t) { $t->dropConstrainedForeignId('venta_correccion_id'); $t->dropColumn('ajuste_venta'); });
        Schema::dropIfExists('venta_correcciones');
        Schema::table('pagos', fn (Blueprint $t) => $t->dropConstrainedForeignId('venta_id'));
        Schema::dropIfExists('ventas');
    }
};
