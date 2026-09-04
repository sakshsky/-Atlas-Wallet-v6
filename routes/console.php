<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('wallet:about', function () { $this->info('Atlas Wallet is ready.'); });

Schedule::command('wallet:monitor')->hourly()->withoutOverlapping();
Schedule::command('wallet:clean-idempotency')->daily()->withoutOverlapping();
Schedule::command('wallet:cache-warm')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('wallet:reconcile')->dailyAt('02:00')->withoutOverlapping();
