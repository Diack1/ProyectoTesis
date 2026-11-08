<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('impresoras', function (Blueprint $t) {
            $t->id();
            $t->string('nombre', 80);
            $t->string('tipo', 20);
            $t->string('conexion', 20);
            $t->string('direccion_ip', 45)->nullable();
            $t->unsignedSmallInteger('puerto')->nullable();
            $t->unsignedSmallInteger('papel_mm')->nullable();
            $t->unsignedTinyInteger('predeterminada_unica')->nullable()->unique();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('impresoras'); }
};
