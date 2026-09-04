<?php
namespace App\Services;
use App\Models\Currency;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class LedgerService
{
    private function customerAccount(Wallet $wallet): int
    {
        DB::table('ledger_accounts')->insertOrIgnore(['currency_id' => $wallet->currency_id, 'wallet_id' => $wallet->id, 'system_key' => null, 'type' => 'customer', 'name' => 'Wallet '.$wallet->id, 'created_at' => now(), 'updated_at' => now()]);
        return (int) DB::table('ledger_accounts')->where('wallet_id', $wallet->id)->value('id');
    }
    private function systemAccount(Currency|int $currency, string $type): int
    {
        $currencyId = $currency instanceof Currency ? $currency->id : $currency;
        $systemKey = $type.':'.$currencyId;
        DB::table('ledger_accounts')->insertOrIgnore(['currency_id' => $currencyId, 'wallet_id' => null, 'system_key' => $systemKey, 'type' => $type, 'name' => str_replace('_', ' ', ucfirst($type)), 'created_at' => now(), 'updated_at' => now()]);
        return (int) DB::table('ledger_accounts')->where('system_key', $systemKey)->value('id');
    }
    public function post(string $eventType, ?int $eventId, ?User $user, array $entries, array $metadata = []): string
    {
        $reference = (string) Str::uuid();
        $idempotencyKey = app()->bound('request') ? request()->header('Idempotency-Key') : null;
        $journalId = DB::table('ledger_journals')->insertGetId(['reference' => $reference, 'event_type' => $eventType, 'event_id' => $eventId, 'user_id' => $user?->id, 'idempotency_key' => $idempotencyKey, 'metadata' => json_encode($metadata), 'created_at' => now(), 'updated_at' => now()]);
        $totals = [];
        foreach ($entries as $entry) {
            [$accountId, $currencyId, $amount] = $entry;
            $totals[$currencyId] = bcadd($totals[$currencyId] ?? '0', $amount, 18);
            DB::table('ledger_entries')->insert(['ledger_journal_id' => $journalId, 'ledger_account_id' => $accountId, 'amount' => $amount, 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach ($totals as $total) if (bccomp($total, '0', 18) !== 0) throw new LogicException('Unbalanced ledger journal.');
        return $reference;
    }
    public function transfer(User $user, Wallet $from, Wallet $to, string $debit, string $credit): string
    {
        $entries = [[$this->customerAccount($from), $from->currency_id, '-'.$debit], [$this->customerAccount($to), $to->currency_id, $credit]];
        if ($from->currency_id !== $to->currency_id) { $entries[] = [$this->systemAccount($from->currency_id, 'treasury'), $from->currency_id, $debit]; $entries[] = [$this->systemAccount($to->currency_id, 'treasury'), $to->currency_id, '-'.$credit]; }
        return $this->post('wallet_transfer', null, $user, $entries);
    }
    public function adjustment(User $actor, Wallet $wallet, string $signedAmount): string
    {
        $inverse = str_starts_with($signedAmount, '-') ? ltrim($signedAmount, '-') : '-'.$signedAmount;
        return $this->post('wallet_adjustment', null, $actor, [[$this->customerAccount($wallet), $wallet->currency_id, $signedAmount], [$this->systemAccount($wallet->currency_id, 'external_clearing'), $wallet->currency_id, $inverse]]);
    }
    public function trade(User $user, int $tradeId, Wallet $from, Wallet $to, string $debit, string $gross, string $net, string $fee): string
    {
        return $this->post('trade', $tradeId, $user, [
            [$this->customerAccount($from), $from->currency_id, '-'.$debit], [$this->systemAccount($from->currency_id, 'treasury'), $from->currency_id, $debit],
            [$this->systemAccount($to->currency_id, 'treasury'), $to->currency_id, '-'.$gross], [$this->customerAccount($to), $to->currency_id, $net], [$this->systemAccount($to->currency_id, 'fee_revenue'), $to->currency_id, $fee],
        ]);
    }
}
