<?php
namespace App\Contracts;
use App\Models\MoneyMovement;
interface MoneyRail
{
    public function prepareDeposit(MoneyMovement $movement): array;
    public function submitWithdrawal(MoneyMovement $movement): array;
}
