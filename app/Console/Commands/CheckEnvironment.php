<?php
namespace App\Console\Commands;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
class CheckEnvironment extends Command
{
    protected $signature = 'wallet:check'; protected $description = 'Check production environment readiness';
    public function handle(): int
    {
        $issues = []; if (app()->environment('production') && config('app.debug')) $issues[] = 'APP_DEBUG must be false in production.'; if (!config('app.key')) $issues[] = 'APP_KEY is missing.'; if (app()->environment('production') && parse_url(config('app.url'), PHP_URL_SCHEME) !== 'https') $issues[] = 'APP_URL must use HTTPS in production.'; if (app()->environment('production') && !config('session.secure')) $issues[] = 'SESSION_SECURE_COOKIE must be true.'; if (!is_writable(storage_path()) || !is_writable(base_path('bootstrap/cache'))) $issues[] = 'Laravel storage directories are not writable.';
        try { DB::select('SELECT 1'); if (!User::where('role', 'admin')->where('status', 'active')->exists()) $issues[] = 'No active administrator exists.'; } catch (\Throwable $error) { $issues[] = 'Database check failed: '.$error->getMessage(); }
        foreach ($issues as $issue) $this->error($issue); if (!$issues) { $this->info('Environment checks passed.'); return self::SUCCESS; } return self::FAILURE;
    }
}
