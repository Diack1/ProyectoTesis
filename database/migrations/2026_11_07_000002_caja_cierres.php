<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('caja_cierres', function (Blueprint $t) {
            $t->id();
            $t->timestamp('inicio_at');
            $t->timestamp('fin_at');
            $t->foreignId('operador_id')->constrained('users')->restrictOnDelete();
            $t->decimal('efectivo_esperado', 12, 2);
            $t->decimal('efectivo_contado', 12, 2);
            $t->decimal('diferencia', 12, 2);
            $t->json('totales_por_medio');
            $t->unsignedInteger('pagos_count');
            $t->unsignedInteger('reembolsos_count')->default(0);
            $t->timestamps();
        });
        Schema::table('pagos', function (Blueprint $t) {
            $t->foreignId('caja_cierre_id')->nullable()->constrained('caja_cierres')->restrictOnDelete();
        });
        Schema::table('reembolsos', function (Blueprint $t) {
            $t->foreignId('caja_cierre_id')->nullable()->constrained('caja_cierres')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pagos', fn (Blueprint $t) => $t->dropConstrainedForeignId('caja_cierre_id'));
        Schema::table('reembolsos', fn (Blueprint $t) => $t->dropConstrainedForeignId('caja_cierre_id'));
        Schema::dropIfExists('caja_cierres');
    }
};
