<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('staff_access_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('session_hash', 64);
            $table->string('credential_hash', 64);
            $table->string('owner_hash', 64);
            $table->string('kind', 20);
            $table->string('state', 20)->default('pending');
            $table->string('code_hash', 64)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['owner_id', 'state', 'expires_at']);
            $table->index(['user_id', 'session_hash']);
        });
    }

    public function down(): void { Schema::dropIfExists('staff_access_requests'); }
};
