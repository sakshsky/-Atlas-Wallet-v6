<?php
namespace Database\Seeders;
use App\Models\Currency;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) throw new RuntimeException('DemoSeeder is disabled in production.');
        $admin = User::updateOrCreate(['email' => 'admin@atlas.test'], ['name' => 'Maya Chen', 'password' => Hash::make('password'), 'role' => 'admin', 'status' => 'active', 'email_verified_at' => now(), 'kyc_status' => 'approved', 'kyc_verified_at' => now()]);
        $member = User::updateOrCreate(['email' => 'member@atlas.test'], ['name' => 'Noah Williams', 'password' => Hash::make('password'), 'role' => 'user', 'status' => 'active', 'email_verified_at' => now(), 'kyc_status' => 'approved', 'kyc_verified_at' => now()]);
        foreach (['USD' => 4820, 'EUR' => 1740, 'INR' => 68000, 'BTC' => .0382, 'ETH' => 1.42, 'USDT' => 820] as $code => $balance) {
            $currency = Currency::where('code', $code)->firstOrFail(); $wallet = Wallet::firstOrCreate(['user_id' => $member->id, 'currency_id' => $currency->id], ['balance' => $balance]);
            if (!$wallet->transactions()->exists()) WalletTransaction::create(['wallet_id' => $wallet->id, 'reference' => 'DEMO-'.$wallet->id, 'type' => 'deposit', 'amount' => $balance, 'balance_before' => 0, 'balance_after' => $balance, 'status' => 'completed', 'description' => 'Demo opening balance', 'created_by' => $admin->id]);
        }
    }
}
