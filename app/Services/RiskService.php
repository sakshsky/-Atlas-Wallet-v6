<?php
namespace App\Services;
use App\Models\ComplianceCase;
use App\Models\MoneyMovement;
use Illuminate\Support\Str;
class RiskService
{
    public function screenMovement(MoneyMovement $movement): ?ComplianceCase
    {
        $signals = [];
        if ($movement->user->risk_level === 'high') $signals[] = 'high_risk_customer';
        if (bccomp($movement->amount, (string) config('wallet.enhanced_review_threshold', 5000), 18) >= 0) $signals[] = 'large_value_movement';
        if (!$signals) return null;
        return ComplianceCase::create(['reference' => (string) Str::uuid(), 'user_id' => $movement->user_id, 'subject_type' => MoneyMovement::class, 'subject_id' => $movement->id, 'severity' => in_array('high_risk_customer', $signals) ? 'high' : 'medium', 'status' => 'open', 'reason' => 'Movement requires enhanced review.', 'signals' => $signals]);
    }
}
