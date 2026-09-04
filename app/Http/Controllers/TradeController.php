<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Services\TradeService;
use App\Services\AuditService;
use App\Notifications\TransactionNotification;
use Illuminate\Support\Facades\Cache;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\TradeQuote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TradeController extends Controller
{
    public function quote(Request $request, TradeService $service): JsonResponse
    {
        $data = $request->validate(['from_currency_id' => ['required', 'exists:currencies,id'], 'to_currency_id' => ['required', 'different:from_currency_id', 'exists:currencies,id'], 'amount' => ['required', 'numeric', 'gt:0']]);
        $quote = $service->quote($request->user(), Currency::findOrFail($data['from_currency_id']), Currency::findOrFail($data['to_currency_id']), (string) $data['amount']);
        $record = TradeQuote::create([...collect($quote)->except(['from_currency', 'to_currency'])->all(), 'token' => (string) Str::uuid(), 'user_id' => $request->user()->id, 'from_currency_id' => $data['from_currency_id'], 'to_currency_id' => $data['to_currency_id'], 'expires_at' => now()->addSeconds(config('wallet.quote_ttl_seconds', 30))]);
        return response()->json([...$quote, 'quote_token' => $record->token, 'expires_at' => $record->expires_at]);
    }
    public function store(Request $request, TradeService $service, AuditService $audit, WebhookService $webhooks): JsonResponse
    {
        $data = $request->validate(['quote_token' => ['required', 'uuid']]);
        $trade = DB::transaction(function () use ($request, $service, $data) { $locked = TradeQuote::where('user_id', $request->user()->id)->where('token', $data['quote_token'])->lockForUpdate()->firstOrFail(); if ($locked->used_at || $locked->expires_at->isPast()) throw ValidationException::withMessages(['quote' => 'This quote has expired or was already used.']); $from = Currency::findOrFail($locked->from_currency_id); $to = Currency::findOrFail($locked->to_currency_id); $snapshot = $locked->only(['type', 'from_amount', 'gross_to_amount', 'fee_amount', 'to_amount', 'market_rate', 'profit_percentage']) + ['from_currency' => $from, 'to_currency' => $to]; $trade = $service->execute($request->user(), $from, $to, $locked->from_amount, $snapshot); $locked->update(['used_at' => now()]); return $trade; }, 3);
        $audit->record($request, 'trade.completed', $trade, ['type' => $trade->type, 'from_currency_id' => $trade->from_currency_id, 'to_currency_id' => $trade->to_currency_id]);
        $request->user()->notify(new TransactionNotification(['id' => $trade->id, 'reference' => $trade->reference, 'amount' => $trade->to_amount, 'wallet_id' => null], 'trade_'.$trade->type)); Cache::forget('admin.stats');
        $webhooks->dispatch('trade.completed', ['reference' => $trade->reference, 'type' => $trade->type, 'from_currency_id' => $trade->from_currency_id, 'to_currency_id' => $trade->to_currency_id, 'from_amount' => $trade->from_amount, 'to_amount' => $trade->to_amount]);
        return response()->json(['message' => ucfirst($trade->type).' completed.', 'trade' => $trade], 201);
    }
}
