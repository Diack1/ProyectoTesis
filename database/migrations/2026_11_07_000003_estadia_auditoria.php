<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('estadia_auditorias', function (Blueprint $t) {
            $t->id();
            $t->foreignId('estadia_id')->constrained('estadias')->restrictOnDelete();
            $t->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $t->string('accion', 30);
            $t->json('valores_anteriores');
            $t->json('valores_nuevos');
            $t->string('motivo', 500);
            $t->timestamp('created_at')->useCurrent();
            $t->index(['estadia_id', 'created_at']);
        });
    }

    public function down(): void { Schema::dropIfExists('estadia_auditorias'); }
};
