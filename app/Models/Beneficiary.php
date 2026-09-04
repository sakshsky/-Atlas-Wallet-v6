<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Beneficiary extends Model
{
    protected $fillable = ['user_id', 'currency_id', 'label', 'destination', 'network', 'is_verified', 'verified_at'];
    protected $hidden = ['destination'];
    protected function casts(): array { return ['destination' => 'encrypted', 'is_verified' => 'boolean', 'verified_at' => 'datetime']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function currency(): BelongsTo { return $this->belongsTo(Currency::class); }
}
