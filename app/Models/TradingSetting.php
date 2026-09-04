<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TradingSetting extends Model
{
    protected $fillable = ['profit_percentage', 'updated_by'];
    protected $casts = ['profit_percentage' => 'decimal:4'];
    public static function current(): self { return static::firstOrCreate(['id' => 1], ['profit_percentage' => config('wallet.default_profit_percentage', '1.5000')]); }
}
