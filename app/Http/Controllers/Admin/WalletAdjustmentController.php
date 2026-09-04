<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Services\WalletService;
use App\Services\AuditService;
use App\Notifications\TransactionNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WalletAdjustmentController extends Controller
{
    public function store(Request $request, Wallet $wallet, WalletService $service, AuditService $audit): JsonResponse
    {
        $data = $request->validate(['type' => ['required', Rule::in(['deposit', 'withdrawal', 'adjustment'])], 'amount' => ['required', 'numeric', 'gt:0'], 'description' => ['required', 'string', 'max:255']]);
        $amount = $data['type'] === 'deposit' ? (string) $data['amount'] : '-'.(string) $data['amount'];
        $transaction = $service->adjust($wallet, $amount, $data['type'], $request->user()->id, $data['description']);
        $audit->record($request, 'wallet.'.$data['type'], $transaction, ['wallet_id' => $wallet->id]);
        $wallet->user->notify(new TransactionNotification(['id' => $transaction->id, 'reference' => $transaction->reference, 'amount' => $transaction->amount, 'wallet_id' => $wallet->id], $data['type'])); Cache::forget('admin.stats');
        return response()->json($transaction, 201);
    }
}
