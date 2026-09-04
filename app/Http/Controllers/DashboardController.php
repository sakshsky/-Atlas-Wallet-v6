<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\TradingSetting;
use App\Models\WalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user()->load(['wallets.currency']);
        $walletIds = $user->wallets->pluck('id');
        return response()->json([
            'user' => $user,
            'wallets' => $user->wallets,
            'currencies' => Cache::remember('currencies.active', 60, fn () => Currency::where('is_active', true)->orderBy('code')->get()),
            'recent_transactions' => WalletTransaction::with('wallet.currency')->whereIn('wallet_id', $walletIds)->latest()->limit(10)->get(),
            'recent_trades' => $user->trades()->with(['fromCurrency', 'toCurrency'])->latest()->limit(10)->get(),
            'trading' => ['profit_percentage' => TradingSetting::current()->profit_percentage],
            'kyc_submission' => $user->kycSubmissions()->latest()->first(),
            'beneficiaries' => $user->beneficiaries()->with('currency')->latest()->get(),
            'money_movements' => $user->moneyMovements()->with(['wallet.currency', 'beneficiary'])->latest()->limit(20)->get(),
        ]);
    }
}
