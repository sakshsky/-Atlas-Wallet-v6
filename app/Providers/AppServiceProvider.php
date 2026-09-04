<?php
namespace App\Providers;
use App\Contracts\MoneyRail;
use App\Services\ManualMoneyRail;
use Illuminate\Support\ServiceProvider;
class AppServiceProvider extends ServiceProvider
{
    public function register(): void { $this->app->bind(MoneyRail::class, fn ($app) => $app->make(config('wallet.money_rail_class', ManualMoneyRail::class))); }
    public function boot(): void {}
}
