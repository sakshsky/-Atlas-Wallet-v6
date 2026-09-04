<?php

namespace App\Services;

use App\Models\ExchangeRate;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WalletService
{
    public function __construct(private readonly LedgerService $ledger) {}
    public function adjust(Wallet $wallet, string $amount, string $type, int $actorId, ?string $description = null, array $metadata = []): WalletTransaction
    {
        return DB::transaction(function () use ($wallet, $amount, $type, $actorId, $description, $metadata) {
            $locked = Wallet::query()->lockForUpdate()->findOrFail($wallet->id);
            if ($locked->status !== 'active') throw ValidationException::withMessages(['wallet' => 'This wallet is frozen.']);
            $before = $locked->balance;
            $after = bcadd($before, $amount, 18);
            if (bccomp($after, (string) $locked->reserved_balance, 18) < 0) throw ValidationException::withMessages(['amount' => 'Insufficient available funds; part of this balance is reserved.']);
            $locked->update(['balance' => $after]);
            $transaction = $locked->transactions()->create([
                'reference' => 'TXN-'.Str::upper(Str::random(12)), 'type' => $type,
                'amount' => ltrim($amount, '+-'), 'balance_before' => $before, 'balance_after' => $after,
                'status' => 'completed', 'description' => $description, 'metadata' => $metadata, 'created_by' => $actorId,
            ]);
            $this->ledger->adjustment(User::findOrFail($actorId), $locked, $amount);
            return $transaction;
        }, 3);
    }

    public function transfer(Wallet $source, Wallet $destination, string $amount, int $actorId, ?string $description = null): array
    {
        if ($source->id === $destination->id) throw ValidationException::withMessages(['wallet' => 'Choose a different destination wallet.']);
        if (bccomp($amount, '0', 18) <= 0) throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);

        return DB::transaction(function () use ($source, $destination, $amount, $actorId, $description) {
            [$firstId, $secondId] = collect([$source->id, $destination->id])->sort()->values()->all();
            $locked = Wallet::with('currency')->whereIn('id', [$firstId, $secondId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $from = $locked[$source->id]; $to = $locked[$destination->id];
            if ($from->status !== 'active' || $to->status !== 'active') throw ValidationException::withMessages(['wallet' => 'One of these wallets is frozen.']);
            $credit = $amount; $rate = '1';
            if ($from->currency_id !== $to->currency_id) {
                $rateModel = ExchangeRate::where('from_currency_id', $from->currency_id)->where('to_currency_id', $to->currency_id)->latest('effective_at')->first();
                if (!$rateModel || $rateModel->effective_at->lt(now()->subMinutes(config('wallet.rate_max_age_minutes', 15)))) throw ValidationException::withMessages(['currency' => 'A current exchange rate is unavailable for this pair.']);
                $rate = $rateModel->rate; $credit = bcmul($amount, $rate, 18);
            }
            $fromBefore = $from->balance; $toBefore = $to->balance;
            $fromAfter = bcsub($fromBefore, $amount, 18);
            if (bccomp($from->available_balance, $amount, 18) < 0) throw ValidationException::withMessages(['amount' => 'Insufficient available funds; part of this balance is reserved.']);
            $toAfter = bcadd($toBefore, $credit, 18);
            $group = (string) Str::uuid();
            $from->update(['balance' => $fromAfter]); $to->update(['balance' => $toAfter]);
            $debit = $from->transactions()->create(['reference' => 'TXN-'.Str::upper(Str::random(12)), 'type' => 'transfer_out', 'amount' => $amount, 'balance_before' => $fromBefore, 'balance_after' => $fromAfter, 'status' => 'completed', 'description' => $description, 'metadata' => ['group' => $group, 'destination_wallet_id' => $to->id, 'rate' => $rate], 'created_by' => $actorId]);
            $creditTx = $to->transactions()->create(['reference' => 'TXN-'.Str::upper(Str::random(12)), 'type' => 'transfer_in', 'amount' => $credit, 'balance_before' => $toBefore, 'balance_after' => $toAfter, 'status' => 'completed', 'description' => $description, 'metadata' => ['group' => $group, 'source_wallet_id' => $from->id, 'rate' => $rate], 'created_by' => $actorId]);
            $this->ledger->transfer(User::findOrFail($actorId), $from, $to, $amount, $credit);
            return [$debit, $creditTx];
        }, 3);
    }
}
