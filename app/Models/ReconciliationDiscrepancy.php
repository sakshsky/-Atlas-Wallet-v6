<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ReconciliationDiscrepancy extends Model
{
    protected $fillable = ['reconciliation_run_id', 'wallet_id', 'wallet_balance', 'ledger_balance', 'difference'];
    protected function casts(): array { return ['wallet_balance' => 'decimal:18', 'ledger_balance' => 'decimal:18', 'difference' => 'decimal:18']; }
    public function run(): BelongsTo { return $this->belongsTo(ReconciliationRun::class, 'reconciliation_run_id'); }
    public function wallet(): BelongsTo { return $this->belongsTo(Wallet::class); }
}
