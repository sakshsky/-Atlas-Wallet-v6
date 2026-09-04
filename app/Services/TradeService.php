<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Trade;
use App\Models\TradingSetting;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TradeService
{
    public function __construct(private readonly LedgerService $ledger) {}
    public function quote(User $user, Currency $from, Currency $to, string $amount): array
    {
        if (!$user->isKycApproved()) throw ValidationException::withMessages(['kyc' => 'Complete KYC verification before trading.']);
        if (!$from->is_active || !$to->is_active || !$from->is_tradeable || !$to->is_tradeable) throw ValidationException::withMessages(['currency' => 'This trading pair is not available.']);
        if ($from->id === $to->id || bccomp($amount, '0', 18) <= 0) throw ValidationException::withMessages(['amount' => 'Choose different assets and enter an amount greater than zero.']);
        if (!$from->market_price_usd || !$to->market_price_usd) throw ValidationException::withMessages(['currency' => 'Market pricing is unavailable for this pair.']);
        $maxAge = config('wallet.market_price_max_age_minutes', 5);
        if ($from->updated_at->lt(now()->subMinutes($maxAge)) || $to->updated_at->lt(now()->subMinutes($maxAge))) throw ValidationException::withMessages(['currency' => 'Market pricing has expired. Please request a fresh price.']);

        $type = match ([$from->type, $to->type]) {
            ['fiat', 'crypto'] => 'buy', ['crypto', 'fiat'] => 'sell', ['crypto', 'crypto'] => 'swap',
            default => throw ValidationException::withMessages(['currency' => 'Use the wallet transfer feature for fiat-to-fiat conversions.']),
        };
        $marketRate = bcdiv($from->market_price_usd, $to->market_price_usd, 18);
        $gross = bcmul($amount, $marketRate, 18);
        $profit = TradingSetting::current()->profit_percentage;
        $fee = bcmul($gross, bcdiv($profit, '100', 18), 18);
        $net = bcsub($gross, $fee, 18);
        return ['type' => $type, 'from_amount' => $amount, 'gross_to_amount' => $gross, 'fee_amount' => $fee, 'to_amount' => $net, 'market_rate' => $marketRate, 'profit_percentage' => $profit, 'from_currency' => $from, 'to_currency' => $to];
    }

    public function execute(User $user, Currency $from, Currency $to, string $amount, ?array $lockedQuote = null): Trade
    {
        return DB::transaction(function () use ($user, $from, $to, $amount, $lockedQuote) {
            $user = User::lockForUpdate()->findOrFail($user->id);
            $assets = Currency::whereIn('id', [$from->id, $to->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $from = $assets[$from->id]; $to = $assets[$to->id];
            if (!$user->isKycApproved() || !$from->is_active || !$to->is_active || !$from->is_tradeable || !$to->is_tradeable) throw ValidationException::withMessages(['trade' => 'This locked quote can no longer be executed.']);
            $quote = $lockedQuote ?? $this->quote($user, $from, $to, $amount);
            $source = Wallet::firstOrCreate(['user_id' => $user->id, 'currency_id' => $from->id], ['balance' => 0, 'status' => 'active']);
            $destination = Wallet::firstOrCreate(['user_id' => $user->id, 'currency_id' => $to->id], ['balance' => 0, 'status' => 'active']);
            $wallets = Wallet::whereIn('id', [$source->id, $destination->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $source = $wallets[$source->id]; $destination = $wallets[$destination->id];
            if ($source->status !== 'active' || $destination->status !== 'active') throw ValidationException::withMessages(['wallet' => 'One of these wallets is frozen.']);
            $sourceBefore = $source->balance; $destinationBefore = $destination->balance;
            $sourceAfter = bcsub($sourceBefore, $quote['from_amount'], 18);
            if (bccomp($source->available_balance, $quote['from_amount'], 18) < 0) throw ValidationException::withMessages(['amount' => 'Insufficient available funds; part of this balance is reserved.']);
            $destinationAfter = bcadd($destinationBefore, $quote['to_amount'], 18);
            $trade = Trade::create([...collect($quote)->except(['from_currency', 'to_currency'])->all(), 'reference' => (string) Str::uuid(), 'user_id' => $user->id, 'from_currency_id' => $from->id, 'to_currency_id' => $to->id, 'status' => 'completed']);
            $source->update(['balance' => $sourceAfter]); $destination->update(['balance' => $destinationAfter]);
            $source->transactions()->create(['reference' => 'TXN-'.Str::upper(Str::random(12)), 'type' => 'transfer_out', 'amount' => $quote['from_amount'], 'balance_before' => $sourceBefore, 'balance_after' => $sourceAfter, 'status' => 'completed', 'description' => ucfirst($quote['type']).' '.$from->code.' to '.$to->code, 'metadata' => ['trade_id' => $trade->id, 'trade_reference' => $trade->reference], 'created_by' => $user->id]);
            $destination->transactions()->create(['reference' => 'TXN-'.Str::upper(Str::random(12)), 'type' => 'transfer_in', 'amount' => $quote['to_amount'], 'balance_before' => $destinationBefore, 'balance_after' => $destinationAfter, 'status' => 'completed', 'description' => ucfirst($quote['type']).' '.$from->code.' to '.$to->code, 'metadata' => ['trade_id' => $trade->id, 'trade_reference' => $trade->reference, 'fee_amount' => $quote['fee_amount']], 'created_by' => $user->id]);
            $this->ledger->trade($user, $trade->id, $source, $destination, $quote['from_amount'], $quote['gross_to_amount'], $quote['to_amount'], $quote['fee_amount']);
            return $trade->load(['fromCurrency', 'toCurrency']);
        }, 3);
    }
}
