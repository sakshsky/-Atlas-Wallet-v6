<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) { $table->decimal('reserved_balance', 36, 18)->default(0)->after('balance'); });
        Schema::table('users', function (Blueprint $table) { $table->enum('risk_level', ['low', 'medium', 'high'])->default('low')->index(); $table->decimal('daily_withdrawal_limit', 36, 18)->default(10000); });

        Schema::create('beneficiaries', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->foreignId('currency_id')->constrained()->restrictOnDelete(); $table->string('label'); $table->text('destination'); $table->string('network')->nullable(); $table->boolean('is_verified')->default(false); $table->timestamp('verified_at')->nullable(); $table->timestamps(); $table->index(['user_id', 'currency_id']);
        });
        Schema::create('money_movements', function (Blueprint $table) {
            $table->id(); $table->uuid('reference')->unique(); $table->foreignId('user_id')->constrained()->restrictOnDelete(); $table->foreignId('wallet_id')->constrained()->restrictOnDelete(); $table->foreignId('beneficiary_id')->nullable()->constrained()->nullOnDelete(); $table->enum('direction', ['deposit', 'withdrawal'])->index(); $table->enum('rail', ['fiat', 'crypto']); $table->decimal('amount', 36, 18); $table->decimal('fee_amount', 36, 18)->default(0); $table->decimal('net_amount', 36, 18); $table->enum('status', ['pending', 'pending_review', 'approved', 'processing', 'completed', 'rejected', 'failed', 'cancelled'])->default('pending')->index(); $table->string('provider')->default('manual'); $table->string('provider_reference')->nullable()->index(); $table->text('destination')->nullable(); $table->json('metadata')->nullable(); $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete(); $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete(); $table->text('review_notes')->nullable(); $table->timestamp('reviewed_at')->nullable(); $table->timestamp('completed_at')->nullable(); $table->timestamps(); $table->index(['user_id', 'created_at']);
        });
        Schema::create('trade_quotes', function (Blueprint $table) {
            $table->id(); $table->uuid('token')->unique(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->foreignId('from_currency_id')->constrained('currencies')->restrictOnDelete(); $table->foreignId('to_currency_id')->constrained('currencies')->restrictOnDelete(); $table->string('type'); $table->decimal('from_amount', 36, 18); $table->decimal('gross_to_amount', 36, 18); $table->decimal('fee_amount', 36, 18); $table->decimal('to_amount', 36, 18); $table->decimal('market_rate', 36, 18); $table->decimal('profit_percentage', 8, 4); $table->timestamp('expires_at')->index(); $table->timestamp('used_at')->nullable(); $table->timestamps();
        });
        Schema::create('compliance_cases', function (Blueprint $table) {
            $table->id(); $table->uuid('reference')->unique(); $table->foreignId('user_id')->constrained()->restrictOnDelete(); $table->string('subject_type'); $table->unsignedBigInteger('subject_id'); $table->enum('severity', ['low', 'medium', 'high', 'critical'])->index(); $table->enum('status', ['open', 'reviewing', 'cleared', 'escalated', 'closed'])->default('open')->index(); $table->string('reason'); $table->json('signals')->nullable(); $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete(); $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete(); $table->text('resolution')->nullable(); $table->timestamp('resolved_at')->nullable(); $table->timestamps(); $table->index(['subject_type', 'subject_id']);
        });
        Schema::create('reconciliation_runs', function (Blueprint $table) {
            $table->id(); $table->uuid('reference')->unique(); $table->enum('status', ['running', 'passed', 'failed'])->default('running')->index(); $table->unsignedInteger('checked_wallets')->default(0); $table->unsignedInteger('discrepancy_count')->default(0); $table->json('summary')->nullable(); $table->timestamp('completed_at')->nullable(); $table->timestamps();
        });
        Schema::create('reconciliation_discrepancies', function (Blueprint $table) {
            $table->id(); $table->foreignId('reconciliation_run_id')->constrained()->cascadeOnDelete(); $table->foreignId('wallet_id')->constrained()->restrictOnDelete(); $table->decimal('wallet_balance', 36, 18); $table->decimal('ledger_balance', 36, 18); $table->decimal('difference', 36, 18); $table->timestamps();
        });
        Schema::create('provider_events', function (Blueprint $table) {
            $table->id(); $table->string('provider'); $table->string('external_id'); $table->string('event_type'); $table->char('payload_hash', 64); $table->json('payload'); $table->timestamp('processed_at')->nullable(); $table->timestamps(); $table->unique(['provider', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_events'); Schema::dropIfExists('reconciliation_discrepancies'); Schema::dropIfExists('reconciliation_runs'); Schema::dropIfExists('compliance_cases'); Schema::dropIfExists('trade_quotes'); Schema::dropIfExists('money_movements'); Schema::dropIfExists('beneficiaries');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['risk_level', 'daily_withdrawal_limit']));
        Schema::table('wallets', fn (Blueprint $table) => $table->dropColumn('reserved_balance'));
    }
};
