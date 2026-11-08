<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('abonos', function (Blueprint $table) {
            $table->string('espacio_contratado_codigo', 20)->nullable();
            $table->string('tarifa_contratada_nombre')->nullable();
        });
        foreach (DB::table('abonos')->select('id', 'espacio_id', 'tarifa_id')->get() as $abono) {
            DB::table('abonos')->where('id', $abono->id)->update([
                'espacio_contratado_codigo' => DB::table('espacios')->where('id', $abono->espacio_id)->value('codigo'),
                'tarifa_contratada_nombre' => $abono->tarifa_id
                    ? DB::table('tarifas')->where('id', $abono->tarifa_id)->value('nombre') : null,
            ]);
        }
        Schema::create('abonado_ediciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('abonado_id')->constrained()->restrictOnDelete();
            $table->foreignId('abono_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->json('antes');
            $table->json('despues');
            $table->string('motivo', 500);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('abonado_ediciones');
        Schema::table('abonos', fn (Blueprint $table) => $table->dropColumn(['espacio_contratado_codigo', 'tarifa_contratada_nombre']));
    }
};
