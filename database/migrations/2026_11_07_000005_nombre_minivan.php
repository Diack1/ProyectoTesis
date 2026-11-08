<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('vehiculo_tipos')->where('codigo','camioneta')->where('nombre','Camioneta')->update(['nombre'=>'Miniván']);
    }

    public function down(): void
    {
        DB::table('vehiculo_tipos')->where('codigo','camioneta')->where('nombre','Miniván')->update(['nombre'=>'Camioneta']);
    }
};
