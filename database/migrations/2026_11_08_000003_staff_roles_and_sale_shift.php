<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->boolean('must_change_password')->default(false));
        Schema::table('ventas', function (Blueprint $table) {
            $table->foreignId('caja_apertura_id')->nullable()->constrained('caja_aperturas')->restrictOnDelete();
            $table->index(['caja_apertura_id', 'fecha']);
        });
        DB::table('users')->whereIn('role', ['admin', 'operador'])->update(['role' => 'junior']);
        DB::table('ventas')->whereNull('caja_apertura_id')->update([
            'caja_apertura_id' => DB::raw('(select caja_apertura_id from pagos where pagos.venta_id = ventas.id and caja_apertura_id is not null order by pagos.id limit 1)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropIndex(['caja_apertura_id', 'fecha']);
            $table->dropConstrainedForeignId('caja_apertura_id');
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('must_change_password'));
    }
};
