<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('kyc_status', ['not_submitted', 'pending', 'approved', 'rejected'])->default('not_submitted')->after('status')->index();
            $table->timestamp('kyc_verified_at')->nullable()->after('kyc_status');
        });
        Schema::table('currencies', function (Blueprint $table) {
            $table->string('code', 10)->change();
            $table->enum('type', ['fiat', 'crypto'])->default('fiat')->after('symbol')->index();
            $table->decimal('market_price_usd', 30, 12)->nullable()->after('precision');
            $table->boolean('is_tradeable')->default(false)->after('is_active');
        });
        Schema::create('kyc_submissions', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('legal_name'); $table->date('date_of_birth'); $table->char('country_code', 2);
            $table->enum('document_type', ['passport', 'national_id', 'drivers_license']);
            $table->string('document_number'); $table->string('document_front_path'); $table->string('document_back_path')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();
            $table->text('review_notes')->nullable(); $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('reviewed_at')->nullable();
            $table->timestamps(); $table->index(['user_id', 'created_at']);
        });
        Schema::create('trading_settings', function (Blueprint $table) {
            $table->id(); $table->decimal('profit_percentage', 7, 4)->default(1.5000); $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
        });
        Schema::create('trades', function (Blueprint $table) {
            $table->id(); $table->uuid('reference')->unique(); $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['buy', 'sell', 'swap'])->index(); $table->foreignId('from_currency_id')->constrained('currencies')->restrictOnDelete(); $table->foreignId('to_currency_id')->constrained('currencies')->restrictOnDelete();
            $table->decimal('from_amount', 30, 12); $table->decimal('gross_to_amount', 30, 12); $table->decimal('fee_amount', 30, 12); $table->decimal('to_amount', 30, 12);
            $table->decimal('market_rate', 30, 12); $table->decimal('profit_percentage', 7, 4); $table->enum('status', ['completed', 'failed', 'reversed'])->default('completed')->index(); $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('trades'); Schema::dropIfExists('trading_settings'); Schema::dropIfExists('kyc_submissions');
        Schema::table('currencies', function (Blueprint $table) { $table->dropColumn(['type', 'market_price_usd', 'is_tradeable']); $table->char('code', 3)->change(); });
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['kyc_status', 'kyc_verified_at']));
    }
};
