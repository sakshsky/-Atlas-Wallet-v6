<?php
namespace App\Console\Commands;
use App\Models\Currency;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
class WarmWalletCache extends Command { protected $signature = 'wallet:cache-warm'; protected $description = 'Warm frequently read wallet reference data'; public function handle(): int { Cache::put('currencies.active', Currency::where('is_active', true)->orderBy('code')->get(), 60); $this->info('Wallet cache warmed.'); return self::SUCCESS; } }
