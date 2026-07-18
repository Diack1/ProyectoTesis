<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehiculo_tipos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre'); // Auto, Moto
            $table->string('codigo')->unique(); // auto, moto
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehiculo_tipos');
    }
};
