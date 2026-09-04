<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class TradeQuote extends Model
{
    protected $fillable = ['token', 'user_id', 'from_currency_id', 'to_currency_id', 'type', 'from_amount', 'gross_to_amount', 'fee_amount', 'to_amount', 'market_rate', 'profit_percentage', 'expires_at', 'used_at'];
    protected function casts(): array { return ['from_amount' => 'decimal:18', 'gross_to_amount' => 'decimal:18', 'fee_amount' => 'decimal:18', 'to_amount' => 'decimal:18', 'market_rate' => 'decimal:18', 'profit_percentage' => 'decimal:4', 'expires_at' => 'datetime', 'used_at' => 'datetime']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function fromCurrency(): BelongsTo { return $this->belongsTo(Currency::class, 'from_currency_id'); }
    public function toCurrency(): BelongsTo { return $this->belongsTo(Currency::class, 'to_currency_id'); }
}
