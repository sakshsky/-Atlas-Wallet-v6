<?php
namespace App\Services;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReversalService
{
    public function __construct(private readonly WalletService $walletService) {}
    public function reverse(WalletTransaction $transaction, User $actor, string $reason): WalletTransaction
    {
        return DB::transaction(function () use ($transaction, $actor, $reason) {
            $original = WalletTransaction::lockForUpdate()->findOrFail($transaction->id);
            if ($original->status !== 'completed') throw ValidationException::withMessages(['transaction' => 'Only completed transactions can be reversed.']);
            if (in_array($original->type, ['transfer_in', 'transfer_out'])) throw ValidationException::withMessages(['transaction' => 'Linked transfers and trades must be reversed as a complete journal.']);
            $signedReversal = bcsub($original->balance_before, $original->balance_after, 18);
            $reversal = $this->walletService->adjust($original->wallet, $signedReversal, 'adjustment', $actor->id, 'Reversal of '.$original->reference.': '.$reason, ['reversed_transaction_id' => $original->id, 'reason' => $reason]);
            $original->update(['status' => 'reversed', 'metadata' => [...($original->metadata ?? []), 'reversed_by' => $actor->id, 'reversed_at' => now()->toIso8601String(), 'reversal_transaction_id' => $reversal->id, 'reversal_reason' => $reason]]);
            return $reversal;
        }, 3);
    }
}
