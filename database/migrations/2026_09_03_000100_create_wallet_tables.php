<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('currencies', function (Blueprint $table) { $table->id(); $table->char('code', 3)->unique(); $table->string('name', 80); $table->string('symbol', 8); $table->unsignedTinyInteger('precision')->default(2); $table->boolean('is_active')->default(true)->index(); $table->timestamps(); });
        Schema::create('wallets', function (Blueprint $table) { $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->foreignId('currency_id')->constrained()->restrictOnDelete(); $table->decimal('balance', 24, 8)->default(0); $table->enum('status', ['active', 'frozen'])->default('active'); $table->timestamps(); $table->unique(['user_id', 'currency_id']); });
        Schema::create('exchange_rates', function (Blueprint $table) { $table->id(); $table->foreignId('from_currency_id')->constrained('currencies')->cascadeOnDelete(); $table->foreignId('to_currency_id')->constrained('currencies')->cascadeOnDelete(); $table->decimal('rate', 24, 12); $table->timestamp('effective_at')->index(); $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps(); $table->index(['from_currency_id', 'to_currency_id', 'effective_at'], 'rate_pair_effective_idx'); });
        Schema::create('wallet_transactions', function (Blueprint $table) { $table->id(); $table->foreignId('wallet_id')->constrained()->restrictOnDelete(); $table->string('reference', 32)->unique(); $table->enum('type', ['deposit', 'withdrawal', 'adjustment', 'transfer_in', 'transfer_out']); $table->decimal('amount', 24, 8); $table->decimal('balance_before', 24, 8); $table->decimal('balance_after', 24, 8); $table->enum('status', ['pending', 'completed', 'failed', 'reversed'])->default('completed')->index(); $table->string('description')->nullable(); $table->json('metadata')->nullable(); $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps(); $table->index(['wallet_id', 'created_at']); });
    }
    public function down(): void { Schema::dropIfExists('wallet_transactions'); Schema::dropIfExists('exchange_rates'); Schema::dropIfExists('wallets'); Schema::dropIfExists('currencies'); }
};
