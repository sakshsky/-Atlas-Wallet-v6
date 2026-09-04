<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class ReconciliationRun extends Model
{
    protected $fillable = ['reference', 'status', 'checked_wallets', 'discrepancy_count', 'summary', 'completed_at'];
    protected function casts(): array { return ['summary' => 'array', 'completed_at' => 'datetime']; }
    public function discrepancies(): HasMany { return $this->hasMany(ReconciliationDiscrepancy::class); }
}
