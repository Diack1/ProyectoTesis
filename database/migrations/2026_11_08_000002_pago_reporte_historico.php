<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pagos', fn (Blueprint $t) => $t->boolean('reportable_historico')->default(false));
    }
    public function down(): void
    {
        Schema::table('pagos', fn (Blueprint $t) => $t->dropColumn('reportable_historico'));
    }
};
