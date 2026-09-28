<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->json('two_factor_recovery_hashes')->nullable();
            $table->unsignedBigInteger('two_factor_last_step')->nullable();
        });
        Schema::table('security_events', function (Blueprint $table) {
            $table->boolean('requires_attention')->default(false)->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('security_events', fn (Blueprint $table) => $table->dropColumn(['requires_attention', 'reviewed_at', 'reviewed_by']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['two_factor_secret', 'two_factor_confirmed_at', 'two_factor_recovery_hashes', 'two_factor_last_step']));
    }
};
