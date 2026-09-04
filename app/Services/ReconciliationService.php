<?php
namespace App\Services;
use App\Models\ReconciliationDiscrepancy;
use App\Models\ReconciliationRun;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class ReconciliationService
{
    public function run(): ReconciliationRun
    {
        $run = ReconciliationRun::create(['reference' => (string) Str::uuid(), 'status' => 'running']); $checked = 0; $differences = 0;
        Wallet::orderBy('id')->chunkById(100, function ($wallets) use ($run, &$checked, &$differences) { foreach ($wallets as $wallet) { $checked++; $ledger = (string) (DB::table('ledger_entries')->join('ledger_accounts', 'ledger_accounts.id', '=', 'ledger_entries.ledger_account_id')->where('ledger_accounts.wallet_id', $wallet->id)->sum('ledger_entries.amount') ?? '0'); $difference = bcsub($wallet->balance, $ledger, 18); if (bccomp($difference, '0', 18) !== 0) { $differences++; ReconciliationDiscrepancy::create(['reconciliation_run_id' => $run->id, 'wallet_id' => $wallet->id, 'wallet_balance' => $wallet->balance, 'ledger_balance' => $ledger, 'difference' => $difference]); } } });
        $run->update(['status' => $differences ? 'failed' : 'passed', 'checked_wallets' => $checked, 'discrepancy_count' => $differences, 'summary' => ['scope' => 'customer_wallet_to_ledger'], 'completed_at' => now()]); return $run->fresh('discrepancies');
    }
}
