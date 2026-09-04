<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\User;
use App\Models\TradingSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = null; $adminEmail = env('ADMIN_EMAIL'); $adminPassword = env('ADMIN_PASSWORD');
        if ($adminEmail || $adminPassword) {
            if (!$adminEmail || !$adminPassword || strlen($adminPassword) < 12 || strtolower($adminPassword) === 'password') throw new RuntimeException('ADMIN_EMAIL and a unique ADMIN_PASSWORD of at least 12 characters are required together.');
            $admin = User::updateOrCreate(['email' => $adminEmail], ['name' => env('ADMIN_NAME') ?: 'Wallet Administrator', 'password' => Hash::make($adminPassword), 'role' => 'admin', 'status' => 'active', 'email_verified_at' => now()]);
        }
        $currencies = collect([
            ['USD','US Dollar','$','fiat',2,1], ['EUR','Euro','€','fiat',2,1.1628], ['GBP','British Pound','£','fiat',2,1.31], ['INR','Indian Rupee','₹','fiat',2,0.01135],
            ['BTC','Bitcoin','₿','crypto',8,110500], ['ETH','Ethereum','Ξ','crypto',8,4380], ['USDT','Tether','₮','crypto',6,1],
        ])->mapWithKeys(function ($c) { $model = Currency::updateOrCreate(['code' => $c[0]], ['name' => $c[1], 'symbol' => $c[2], 'type' => $c[3], 'precision' => $c[4], 'market_price_usd' => $c[5], 'is_active' => true, 'is_tradeable' => true]); return [$c[0] => $model]; });
        foreach ([['USD','EUR',0.86], ['EUR','USD',1.1628], ['USD','INR',88.12], ['INR','USD',0.01135]] as $rate) ExchangeRate::updateOrCreate(['from_currency_id' => $currencies[$rate[0]]->id, 'to_currency_id' => $currencies[$rate[1]]->id], ['rate' => $rate[2], 'effective_at' => now(), 'created_by' => $admin?->id]);
        TradingSetting::updateOrCreate(['id' => 1], ['profit_percentage' => config('wallet.default_profit_percentage'), 'updated_by' => $admin?->id]);
    }
}
