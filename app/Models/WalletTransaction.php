<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    protected $fillable = ['wallet_id', 'reference', 'type', 'amount', 'balance_before', 'balance_after', 'status', 'description', 'metadata', 'created_by'];
    protected $casts = ['amount' => 'decimal:18', 'balance_before' => 'decimal:18', 'balance_after' => 'decimal:18', 'metadata' => 'array'];
    public function wallet(): BelongsTo { return $this->belongsTo(Wallet::class); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
