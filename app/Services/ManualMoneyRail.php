<?php
namespace App\Services;
use App\Contracts\MoneyRail;
use App\Models\MoneyMovement;
class ManualMoneyRail implements MoneyRail
{
    public function prepareDeposit(MoneyMovement $movement): array { return ['provider_reference' => 'MANUAL-'.$movement->reference, 'instructions' => 'Await administrator or provider settlement confirmation.']; }
    public function submitWithdrawal(MoneyMovement $movement): array { return ['provider_reference' => 'MANUAL-'.$movement->reference, 'status' => 'processing']; }
}
