<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pending_email')->nullable();
            $table->string('email_change_hash', 64)->nullable();
            $table->timestamp('email_change_expires_at')->nullable();
            $table->unsignedTinyInteger('email_change_attempts')->default(0);
        });
        Schema::create('security_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('resource_type', 80);
            $table->unsignedBigInteger('resource_id');
            $table->string('action', 30);
            $table->json('changed_fields');
            $table->timestamp('created_at')->useCurrent();
        });
    }
    public function down(): void {
        Schema::dropIfExists('security_events');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['pending_email', 'email_change_hash', 'email_change_expires_at', 'email_change_attempts']));
    }
};
