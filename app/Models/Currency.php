<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Currency extends Model
{
    protected $fillable = ['code', 'name', 'symbol', 'type', 'precision', 'market_price_usd', 'is_active', 'is_tradeable'];
    protected function casts(): array { return ['is_active' => 'boolean', 'is_tradeable' => 'boolean', 'precision' => 'integer', 'market_price_usd' => 'decimal:18']; }
    public function wallets(): HasMany { return $this->hasMany(Wallet::class); }
}
