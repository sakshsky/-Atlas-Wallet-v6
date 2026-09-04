<?php
namespace App\Console\Commands;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\KycSubmission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
class MonitorHealth extends Command
{
    protected $signature = 'wallet:monitor'; protected $description = 'Check database, queues, KYC backlog, and stale pricing';
    public function handle(): int
    {
        $alerts = []; try { DB::select('SELECT 1'); if (DB::table('failed_jobs')->count() > 10) $alerts[] = 'More than 10 queue jobs have failed.'; if (KycSubmission::where('status', 'pending')->where('created_at', '<', now()->subDays(7))->exists()) $alerts[] = 'KYC submissions have been pending for more than seven days.'; if (ExchangeRate::where('effective_at', '<', now()->subMinutes(config('wallet.rate_max_age_minutes')))->exists()) $alerts[] = 'One or more fiat rates are stale.'; if (Currency::where('is_tradeable', true)->where('updated_at', '<', now()->subMinutes(config('wallet.market_price_max_age_minutes')))->exists()) $alerts[] = 'One or more market prices are stale.'; } catch (\Throwable $error) { $alerts[] = 'Database health checks failed: '.$error->getMessage(); }
        if ($alerts) { Log::warning('Atlas Wallet health alerts', ['alerts' => $alerts]); foreach ($alerts as $alert) $this->warn($alert); return self::FAILURE; } Log::info('Atlas Wallet health check passed.'); $this->info('Health check passed.'); return self::SUCCESS;
    }
}
