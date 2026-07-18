<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;


return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->integer('duracion_minutos')->default(60)->after('hora_fin');
            $table->decimal('tarifa_hora', 8, 2)->default(0)->after('duracion_minutos');
            $table->decimal('monto_total', 8, 2)->default(0)->after('tarifa_hora');

            $table->dateTime('expires_at')->nullable()->after('estado');
            $table->dateTime('pagado_at')->nullable()->after('expires_at');
            $table->dateTime('cancelado_at')->nullable()->after('pagado_at');
            $table->dateTime('expirado_at')->nullable()->after('cancelado_at');
        });

        DB::statement("ALTER TABLE reservas MODIFY estado ENUM(
            'pendiente_pago',
            'confirmada',
            'cancelada',
            'expirada',
            'finalizada',
            'reembolso_solicitado',
            'reembolso_aprobado',
            'reembolso_rechazado'
        ) DEFAULT 'pendiente_pago'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE reservas MODIFY estado ENUM(
            'pendiente',
            'confirmada',
            'cancelada',
            'finalizada'
        ) DEFAULT 'pendiente'");

        Schema::table('reservas', function (Blueprint $table) {
            $table->dropColumn([
                'duracion_minutos',
                'tarifa_hora',
                'monto_total',
                'expires_at',
                'pagado_at',
                'cancelado_at',
                'expirado_at',
            ]);
        });
    }
};
