<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
use App\Services\WalletService;
use App\Services\AuditService;
use App\Notifications\TransactionNotification;
use Illuminate\Support\Facades\Cache;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransferController extends Controller
{
    public function store(Request $request, WalletService $service, AuditService $audit, WebhookService $webhooks): JsonResponse
    {
        $data = $request->validate(['from_wallet_id' => ['required', 'integer'], 'to_wallet_id' => ['required', 'different:from_wallet_id', 'exists:wallets,id'], 'amount' => ['required', 'numeric', 'gt:0'], 'description' => ['nullable', 'string', 'max:255']]);
        $source = $request->user()->wallets()->findOrFail($data['from_wallet_id']);
        $destination = Wallet::findOrFail($data['to_wallet_id']);
        [$debit, $credit] = $service->transfer($source, $destination, (string) $data['amount'], $request->user()->id, $data['description'] ?? null);
        $audit->record($request, 'wallet.transfer.completed', $debit, ['destination_wallet_id' => $destination->id, 'credit_transaction_id' => $credit->id]);
        $request->user()->notify(new TransactionNotification(['id' => $debit->id, 'reference' => $debit->reference, 'amount' => $debit->amount, 'wallet_id' => $debit->wallet_id], 'transfer_sent'));
        $destination->user->notify(new TransactionNotification(['id' => $credit->id, 'reference' => $credit->reference, 'amount' => $credit->amount, 'wallet_id' => $credit->wallet_id], 'transfer_received')); Cache::forget('admin.stats');
        $webhooks->dispatch('transfer.completed', ['reference' => $debit->reference, 'source_wallet_id' => $source->id, 'destination_wallet_id' => $destination->id, 'amount' => $debit->amount]);
        return response()->json(['message' => 'Transfer completed.', 'debit' => $debit, 'credit' => $credit], 201);
    }
}
