<?php
namespace App\Services;
use App\Contracts\MoneyRail;
use App\Models\Beneficiary;
use App\Models\MoneyMovement;
use App\Models\ComplianceCase;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class MoneyMovementService
{
    public function __construct(private readonly LedgerService $ledger, private readonly RiskService $risk, private readonly MoneyRail $rail) {}

    public function requestDeposit(User $user, Wallet $wallet, string $amount): MoneyMovement
    {
        return DB::transaction(function () use ($user, $wallet, $amount) {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->findOrFail($wallet->id);
            if ($wallet->status !== 'active' || bccomp($amount, '0', 18) <= 0) throw ValidationException::withMessages(['amount' => 'Enter a positive amount for an active wallet.']);
            $movement = MoneyMovement::create(['reference' => (string) Str::uuid(), 'user_id' => $user->id, 'wallet_id' => $wallet->id, 'direction' => 'deposit', 'rail' => $wallet->currency->type, 'amount' => $amount, 'fee_amount' => '0', 'net_amount' => $amount, 'status' => 'pending', 'provider' => config('wallet.money_rail', 'manual'), 'requested_by' => $user->id]);
            $prepared = $this->rail->prepareDeposit($movement); $movement->update(['provider_reference' => $prepared['provider_reference'] ?? null, 'metadata' => ['instructions' => $prepared['instructions'] ?? null]]);
            return $movement->fresh(['wallet.currency']);
        }, 3);
    }

    public function requestWithdrawal(User $user, Wallet $wallet, Beneficiary $beneficiary, string $amount): MoneyMovement
    {
        if (!$user->isKycApproved()) throw ValidationException::withMessages(['kyc' => 'KYC approval is required before withdrawals.']);
        if (!$user->hasTwoFactorEnabled()) throw ValidationException::withMessages(['two_factor' => 'Enable two-factor authentication before adding or using withdrawal destinations.']);
        abort_unless($beneficiary->user_id === $user->id, 404);
        return DB::transaction(function () use ($user, $wallet, $beneficiary, $amount) {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->findOrFail($wallet->id);
            if ($wallet->currency_id !== $beneficiary->currency_id || !$beneficiary->is_verified) throw ValidationException::withMessages(['beneficiary' => 'Choose a verified beneficiary for this currency.']);
            $today = MoneyMovement::where('user_id', $user->id)->where('direction', 'withdrawal')->whereNotIn('status', ['rejected', 'failed', 'cancelled'])->whereDate('created_at', today())->sum('amount');
            if (bccomp(bcadd((string) $today, $amount, 18), $user->daily_withdrawal_limit, 18) > 0) throw ValidationException::withMessages(['amount' => 'This withdrawal exceeds your daily limit.']);
            if (bccomp($wallet->available_balance, $amount, 18) < 0) throw ValidationException::withMessages(['amount' => 'Insufficient available balance.']);
            $wallet->update(['reserved_balance' => bcadd($wallet->reserved_balance, $amount, 18)]);
            $movement = MoneyMovement::create(['reference' => (string) Str::uuid(), 'user_id' => $user->id, 'wallet_id' => $wallet->id, 'beneficiary_id' => $beneficiary->id, 'direction' => 'withdrawal', 'rail' => $wallet->currency->type, 'amount' => $amount, 'fee_amount' => '0', 'net_amount' => $amount, 'status' => 'pending_review', 'provider' => config('wallet.money_rail', 'manual'), 'destination' => $beneficiary->destination, 'requested_by' => $user->id]);
            $this->risk->screenMovement($movement->load('user'));
            return $movement->fresh(['wallet.currency', 'beneficiary']);
        }, 3);
    }

    public function approve(MoneyMovement $movement, User $reviewer, ?string $notes): MoneyMovement
    {
        return DB::transaction(function () use ($movement, $reviewer, $notes) {
            $movement = MoneyMovement::lockForUpdate()->findOrFail($movement->id); abort_if($movement->requested_by === $reviewer->id, 422, 'A second person must approve this request.');
            if ($movement->status !== 'pending_review') throw ValidationException::withMessages(['movement' => 'This movement is not awaiting approval.']);
            if (ComplianceCase::where('subject_type', MoneyMovement::class)->where('subject_id', $movement->id)->whereIn('status', ['open', 'reviewing', 'escalated'])->exists()) throw ValidationException::withMessages(['movement' => 'Resolve the compliance case before approval.']);
            $provider = $this->rail->submitWithdrawal($movement); $movement->update(['status' => 'processing', 'reviewed_by' => $reviewer->id, 'review_notes' => $notes, 'reviewed_at' => now(), 'provider_reference' => $provider['provider_reference'] ?? $movement->provider_reference]); return $movement->fresh();
        }, 3);
    }

    public function reject(MoneyMovement $movement, User $reviewer, string $notes): MoneyMovement
    {
        return DB::transaction(function () use ($movement, $reviewer, $notes) { $movement = MoneyMovement::lockForUpdate()->findOrFail($movement->id); if (!in_array($movement->status, ['pending', 'pending_review', 'approved'])) throw ValidationException::withMessages(['movement' => 'This movement cannot be rejected.']); if ($movement->direction === 'withdrawal') { $wallet = Wallet::lockForUpdate()->findOrFail($movement->wallet_id); $wallet->update(['reserved_balance' => bcsub($wallet->reserved_balance, $movement->amount, 18)]); } $movement->update(['status' => 'rejected', 'reviewed_by' => $reviewer->id, 'review_notes' => $notes, 'reviewed_at' => now()]); return $movement->fresh(); }, 3);
    }

    public function complete(MoneyMovement $movement, User $actor): MoneyMovement
    {
        abort_if($movement->requested_by === $actor->id, 422, 'A second person must confirm settlement.');
        return DB::transaction(function () use ($movement, $actor) { $movement = MoneyMovement::lockForUpdate()->findOrFail($movement->id); $allowed = $movement->direction === 'deposit' ? ['pending', 'processing'] : ['processing']; if (!in_array($movement->status, $allowed)) throw ValidationException::withMessages(['movement' => 'This movement is not ready for settlement.']); $wallet = Wallet::lockForUpdate()->findOrFail($movement->wallet_id); $before = $wallet->balance; if ($movement->direction === 'deposit') { $after = bcadd($before, $movement->net_amount, 18); $signed = $movement->net_amount; $type = 'deposit'; $amount = $movement->net_amount; $wallet->update(['balance' => $after]); } else { $after = bcsub($before, $movement->amount, 18); if (bccomp($after, '0', 18) < 0) throw ValidationException::withMessages(['movement' => 'The reserved withdrawal can no longer be settled.']); $signed = '-'.$movement->amount; $type = 'withdrawal'; $amount = $movement->amount; $wallet->update(['balance' => $after, 'reserved_balance' => bcsub($wallet->reserved_balance, $movement->amount, 18)]); } $wallet->transactions()->create(['reference' => 'TXN-'.Str::upper(Str::random(12)), 'type' => $type, 'amount' => $amount, 'balance_before' => $before, 'balance_after' => $after, 'status' => 'completed', 'description' => ucfirst($type).' settlement', 'metadata' => ['money_movement_id' => $movement->id, 'movement_reference' => $movement->reference, 'provider_reference' => $movement->provider_reference], 'created_by' => $actor->id]); $this->ledger->adjustment($actor, $wallet, $signed); $movement->update(['status' => 'completed', 'completed_at' => now()]); return $movement->fresh(['wallet.currency']); }, 3);
    }
}
