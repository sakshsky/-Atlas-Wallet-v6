<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\WalletTransaction;
use App\Notifications\TransactionNotification;
use App\Services\AuditService;
use App\Services\ReversalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\WebhookService;
class ReversalController extends Controller
{
    public function store(Request $request, WalletTransaction $walletTransaction, ReversalService $service, AuditService $audit, WebhookService $webhooks): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']]); $reversal = $service->reverse($walletTransaction, $request->user(), $data['reason']); $audit->record($request, 'wallet.transaction_reversed', $reversal, ['original_transaction_id' => $walletTransaction->id, 'reason' => $data['reason']]); $reversal->wallet->user->notify(new TransactionNotification(['id' => $reversal->id, 'reference' => $reversal->reference, 'amount' => $reversal->amount, 'wallet_id' => $reversal->wallet_id], 'reversal')); $webhooks->dispatch('reversal.completed', ['original_transaction_id' => $walletTransaction->id, 'reversal_reference' => $reversal->reference]); return response()->json($reversal, 201);
    }
}
