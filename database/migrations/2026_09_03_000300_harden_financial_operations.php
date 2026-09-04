<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        $currentProfit = DB::table('trading_settings')->latest('updated_at')->value('profit_percentage') ?? '1.5000';
        DB::table('trading_settings')->updateOrInsert(['id' => 1], ['profit_percentage' => $currentProfit, 'updated_at' => now(), 'created_at' => now()]);
        DB::table('trading_settings')->where('id', '<>', 1)->delete();
        Schema::table('wallets', fn (Blueprint $table) => $table->decimal('balance', 36, 18)->default(0)->change());
        Schema::table('currencies', fn (Blueprint $table) => $table->decimal('market_price_usd', 36, 18)->nullable()->change());
        Schema::table('exchange_rates', fn (Blueprint $table) => $table->decimal('rate', 36, 18)->change());
        Schema::table('kyc_submissions', fn (Blueprint $table) => $table->text('document_number')->change());
        Schema::table('wallet_transactions', function (Blueprint $table) { $table->decimal('amount', 36, 18)->change(); $table->decimal('balance_before', 36, 18)->change(); $table->decimal('balance_after', 36, 18)->change(); });
        Schema::table('trades', function (Blueprint $table) { $table->decimal('from_amount', 36, 18)->change(); $table->decimal('gross_to_amount', 36, 18)->change(); $table->decimal('fee_amount', 36, 18)->change(); $table->decimal('to_amount', 36, 18)->change(); $table->decimal('market_rate', 36, 18)->change(); });

        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->string('operation', 80); $table->string('key', 100); $table->char('request_hash', 64); $table->uuid('owner_token'); $table->enum('status', ['processing', 'completed'])->default('processing'); $table->unsignedSmallInteger('response_code')->nullable(); $table->json('response_payload')->nullable(); $table->timestamps(); $table->unique(['user_id', 'operation', 'key'], 'idempotency_scope_unique');
        });
        Schema::create('ledger_journals', function (Blueprint $table) {
            $table->id(); $table->uuid('reference')->unique(); $table->string('event_type', 60)->index(); $table->unsignedBigInteger('event_id')->nullable(); $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); $table->string('idempotency_key', 100)->nullable(); $table->json('metadata')->nullable(); $table->timestamps();
        });
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id(); $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete(); $table->foreignId('wallet_id')->nullable()->constrained('wallets')->restrictOnDelete(); $table->string('system_key')->nullable()->unique(); $table->enum('type', ['customer', 'treasury', 'fee_revenue', 'external_clearing']); $table->string('name'); $table->timestamps(); $table->unique('wallet_id');
        });
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id(); $table->foreignId('ledger_journal_id')->constrained()->restrictOnDelete(); $table->foreignId('ledger_account_id')->constrained()->restrictOnDelete(); $table->decimal('amount', 36, 18); $table->timestamps(); $table->index(['ledger_account_id', 'created_at']);
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); $table->string('event', 100)->index(); $table->string('subject_type')->nullable(); $table->unsignedBigInteger('subject_id')->nullable(); $table->ipAddress('ip_address')->nullable(); $table->text('user_agent')->nullable(); $table->json('metadata')->nullable(); $table->timestamps(); $table->index(['subject_type', 'subject_id']);
        });
        DB::table('wallets')->where('balance', '<>', 0)->orderBy('id')->chunkById(100, function ($wallets) {
            foreach ($wallets as $wallet) {
                DB::table('ledger_accounts')->insertOrIgnore(['currency_id' => $wallet->currency_id, 'wallet_id' => $wallet->id, 'system_key' => null, 'type' => 'customer', 'name' => 'Wallet '.$wallet->id, 'created_at' => now(), 'updated_at' => now()]);
                $systemKey = 'external_clearing:'.$wallet->currency_id;
                DB::table('ledger_accounts')->insertOrIgnore(['currency_id' => $wallet->currency_id, 'wallet_id' => null, 'system_key' => $systemKey, 'type' => 'external_clearing', 'name' => 'External clearing', 'created_at' => now(), 'updated_at' => now()]);
                $journalId = DB::table('ledger_journals')->insertGetId(['reference' => (string) Str::uuid(), 'event_type' => 'opening_balance', 'event_id' => $wallet->id, 'user_id' => $wallet->user_id, 'metadata' => json_encode(['migration' => '2026_09_03_000300']), 'created_at' => now(), 'updated_at' => now()]);
                $customerId = DB::table('ledger_accounts')->where('wallet_id', $wallet->id)->value('id'); $clearingId = DB::table('ledger_accounts')->where('system_key', $systemKey)->value('id');
                DB::table('ledger_entries')->insert([['ledger_journal_id' => $journalId, 'ledger_account_id' => $customerId, 'amount' => $wallet->balance, 'created_at' => now(), 'updated_at' => now()], ['ledger_journal_id' => $journalId, 'ledger_account_id' => $clearingId, 'amount' => '-'.$wallet->balance, 'created_at' => now(), 'updated_at' => now()]]);
            }
        });
        DB::table('kyc_submissions')->select(['id', 'document_number'])->orderBy('id')->chunkById(100, function ($rows) { foreach ($rows as $row) DB::table('kyc_submissions')->where('id', $row->id)->update(['document_number' => Crypt::encryptString($row->document_number)]); });
    }
    public function down(): void
    {
        Schema::dropIfExists('audit_logs'); Schema::dropIfExists('ledger_entries'); Schema::dropIfExists('ledger_accounts'); Schema::dropIfExists('ledger_journals'); Schema::dropIfExists('idempotency_keys');
        Schema::table('exchange_rates', fn (Blueprint $table) => $table->decimal('rate', 24, 12)->change());
        Schema::table('currencies', fn (Blueprint $table) => $table->decimal('market_price_usd', 30, 12)->nullable()->change());
        Schema::table('trades', function (Blueprint $table) { $table->decimal('from_amount', 30, 12)->change(); $table->decimal('gross_to_amount', 30, 12)->change(); $table->decimal('fee_amount', 30, 12)->change(); $table->decimal('to_amount', 30, 12)->change(); $table->decimal('market_rate', 30, 12)->change(); });
        Schema::table('wallet_transactions', function (Blueprint $table) { $table->decimal('amount', 24, 8)->change(); $table->decimal('balance_before', 24, 8)->change(); $table->decimal('balance_after', 24, 8)->change(); });
        Schema::table('wallets', fn (Blueprint $table) => $table->decimal('balance', 24, 8)->default(0)->change());
    }
};
