<?php
namespace App\Console\Commands;
use App\Services\ReconciliationService;
use Illuminate\Console\Command;
class ReconcileWallets extends Command
{
    protected $signature = 'wallet:reconcile'; protected $description = 'Compare every wallet balance with its customer ledger account';
    public function handle(ReconciliationService $service): int { $run = $service->run(); $this->line("Checked {$run->checked_wallets} wallets; found {$run->discrepancy_count} discrepancies."); return $run->status === 'passed' ? self::SUCCESS : self::FAILURE; }
}
