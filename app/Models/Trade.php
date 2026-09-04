<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Trade extends Model
{
    protected $fillable = ['reference', 'user_id', 'type', 'from_currency_id', 'to_currency_id', 'from_amount', 'gross_to_amount', 'fee_amount', 'to_amount', 'market_rate', 'profit_percentage', 'status'];
    protected $casts = ['from_amount' => 'decimal:18', 'gross_to_amount' => 'decimal:18', 'fee_amount' => 'decimal:18', 'to_amount' => 'decimal:18', 'market_rate' => 'decimal:18', 'profit_percentage' => 'decimal:4'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function fromCurrency(): BelongsTo { return $this->belongsTo(Currency::class, 'from_currency_id'); }
    public function toCurrency(): BelongsTo { return $this->belongsTo(Currency::class, 'to_currency_id'); }
}
