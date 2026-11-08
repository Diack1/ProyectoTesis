<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereIn('role', ['super_admin', 'junior', 'admin', 'operador'])
            ->update(['must_change_password' => false]);
    }

    public function down(): void
    {
        // A rollback must not force a password change on existing users.
    }
};
