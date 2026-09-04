<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) { $table->text('two_factor_secret')->nullable(); $table->text('two_factor_recovery_codes')->nullable(); $table->timestamp('two_factor_confirmed_at')->nullable(); });
        Schema::create('notifications', function (Blueprint $table) { $table->uuid('id')->primary(); $table->string('type'); $table->morphs('notifiable'); $table->text('data'); $table->timestamp('read_at')->nullable(); $table->timestamps(); });
        Schema::create('webhooks', function (Blueprint $table) { $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->string('url', 2048); $table->json('events'); $table->text('secret'); $table->boolean('is_active')->default(true)->index(); $table->timestamp('last_triggered_at')->nullable(); $table->timestamps(); });
        Schema::create('webhook_deliveries', function (Blueprint $table) { $table->id(); $table->foreignId('webhook_id')->constrained()->cascadeOnDelete(); $table->uuid('delivery_id')->unique(); $table->string('event'); $table->unsignedSmallInteger('response_status')->nullable(); $table->unsignedTinyInteger('attempts')->default(0); $table->enum('status', ['pending', 'delivered', 'failed'])->default('pending')->index(); $table->text('error')->nullable(); $table->timestamp('delivered_at')->nullable(); $table->timestamps(); });
        Schema::create('user_exports', function (Blueprint $table) { $table->id(); $table->uuid('reference')->unique(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->enum('status', ['pending', 'ready', 'failed'])->default('pending')->index(); $table->string('path')->nullable(); $table->timestamp('expires_at')->nullable(); $table->text('error')->nullable(); $table->timestamps(); });
    }
    public function down(): void
    {
        Schema::dropIfExists('user_exports'); Schema::dropIfExists('webhook_deliveries'); Schema::dropIfExists('webhooks'); Schema::dropIfExists('notifications');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']));
    }
};
