<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\KycSubmission;
use App\Models\TradingSetting;
use App\Models\Trade;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use App\Models\MoneyMovement;
use App\Models\ComplianceCase;
use App\Models\ReconciliationRun;

class AdminDashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'stats' => Cache::remember('admin.stats', 60, fn () => ['users' => User::count(), 'wallets' => Wallet::count(), 'currencies' => Currency::where('is_active', true)->count(), 'transactions' => WalletTransaction::where('status', 'completed')->count(), 'trades' => Trade::where('status', 'completed')->count(), 'pending_kyc' => KycSubmission::where('status', 'pending')->count(), 'pending_movements' => MoneyMovement::whereIn('status', ['pending', 'pending_review', 'processing'])->count(), 'open_cases' => ComplianceCase::whereIn('status', ['open', 'reviewing', 'escalated'])->count()]),
            'users' => User::withCount('wallets')->latest()->limit(20)->get(),
            'currencies' => Currency::orderBy('code')->get(),
            'kyc_submissions' => KycSubmission::with('user:id,name,email')->latest()->limit(30)->get(),
            'trading_setting' => TradingSetting::current(),
            'money_movements' => MoneyMovement::with(['user:id,name,email,risk_level', 'wallet.currency'])->latest()->limit(30)->get(),
            'compliance_cases' => ComplianceCase::with('user:id,name,email,risk_level')->latest()->limit(30)->get(),
            'reconciliation_runs' => ReconciliationRun::latest()->limit(10)->get(),
        ]);
    }
}
