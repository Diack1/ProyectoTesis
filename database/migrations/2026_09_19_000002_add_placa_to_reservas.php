<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            // Las reservas históricas conservan null: no se puede inferir su vehículo.
            $table->string('placa', 10)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->dropIndex(['placa']);
            $table->dropColumn('placa');
        });
    }
};
