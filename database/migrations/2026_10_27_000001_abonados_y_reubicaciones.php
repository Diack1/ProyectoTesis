<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('abonados', function (Blueprint $t) {
            $t->id(); $t->string('nombre'); $t->string('telefono', 40)->nullable();
            $t->json('placas'); $t->timestamps();
        });
        Schema::create('abonos', function (Blueprint $t) {
            $t->id(); $t->foreignId('abonado_id')->constrained()->restrictOnDelete();
            $t->foreignId('espacio_id')->constrained()->restrictOnDelete();
            $t->timestamp('inicio'); $t->timestamp('fin');
            $t->decimal('monto', 10, 2); $t->string('zona', 10);
            $t->foreignId('registrado_por')->constrained('users')->restrictOnDelete();
            $t->timestamps(); $t->index(['espacio_id', 'inicio', 'fin']);
        });
        Schema::table('pagos', fn (Blueprint $t) => $t->foreignId('abono_id')->nullable()->unique()->constrained()->restrictOnDelete());
        Schema::table('estadias', function (Blueprint $t) {
            $t->foreignId('abono_id')->nullable()->constrained()->restrictOnDelete();
            $t->boolean('autoriza_movimiento')->default(false);
            $t->boolean('llave_recibida')->default(false);
            $t->string('ubicacion_llave', 100)->nullable();
            $t->timestamp('salida_prevista')->nullable();
        });
        Schema::create('reubicaciones', function (Blueprint $t) {
            $t->id(); $t->foreignId('estadia_id')->constrained()->restrictOnDelete();
            $t->foreignId('origen_id')->constrained('espacios')->restrictOnDelete();
            $t->foreignId('destino_id')->constrained('espacios')->restrictOnDelete();
            $t->foreignId('operador_id')->constrained('users')->restrictOnDelete();
            $t->string('motivo', 500); $t->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('reubicaciones');
        Schema::table('estadias', function (Blueprint $t) {
            $t->dropConstrainedForeignId('abono_id');
            $t->dropColumn(['autoriza_movimiento', 'llave_recibida', 'ubicacion_llave', 'salida_prevista']);
        });
        Schema::table('pagos', fn (Blueprint $t) => $t->dropConstrainedForeignId('abono_id'));
        Schema::dropIfExists('abonos'); Schema::dropIfExists('abonados');
    }
};
