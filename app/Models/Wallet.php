<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    protected $fillable = ['user_id', 'currency_id', 'balance', 'reserved_balance', 'status'];
    protected $casts = ['balance' => 'decimal:18', 'reserved_balance' => 'decimal:18'];
    protected $appends = ['available_balance'];
    public function getAvailableBalanceAttribute(): string { return bcsub((string) $this->balance, (string) $this->reserved_balance, 18); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function currency(): BelongsTo { return $this->belongsTo(Currency::class); }
    public function transactions(): HasMany { return $this->hasMany(WalletTransaction::class); }
    public function movements(): HasMany { return $this->hasMany(MoneyMovement::class); }
}
