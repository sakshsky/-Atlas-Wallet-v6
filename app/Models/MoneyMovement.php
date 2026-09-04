<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class MoneyMovement extends Model
{
    protected $fillable = ['reference', 'user_id', 'wallet_id', 'beneficiary_id', 'direction', 'rail', 'amount', 'fee_amount', 'net_amount', 'status', 'provider', 'provider_reference', 'destination', 'metadata', 'requested_by', 'reviewed_by', 'review_notes', 'reviewed_at', 'completed_at'];
    protected $hidden = ['destination'];
    protected function casts(): array { return ['amount' => 'decimal:18', 'fee_amount' => 'decimal:18', 'net_amount' => 'decimal:18', 'destination' => 'encrypted', 'metadata' => 'array', 'reviewed_at' => 'datetime', 'completed_at' => 'datetime']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function wallet(): BelongsTo { return $this->belongsTo(Wallet::class); }
    public function beneficiary(): BelongsTo { return $this->belongsTo(Beneficiary::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}
