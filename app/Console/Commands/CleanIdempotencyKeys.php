<?php
namespace App\Console\Commands;
use App\Models\IdempotencyKey;
use Illuminate\Console\Command;
class CleanIdempotencyKeys extends Command { protected $signature = 'wallet:clean-idempotency'; protected $description = 'Delete completed idempotency records older than seven days'; public function handle(): int { $deleted = IdempotencyKey::where('status', 'completed')->where('created_at', '<', now()->subDays(7))->delete(); $this->info("Deleted {$deleted} records."); return self::SUCCESS; } }
