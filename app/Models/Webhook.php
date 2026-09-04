<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Webhook extends Model
{
    protected $fillable = ['user_id', 'url', 'events', 'secret', 'is_active', 'last_triggered_at'];
    protected $hidden = ['secret'];
    protected $casts = ['events' => 'array', 'secret' => 'encrypted', 'is_active' => 'boolean', 'last_triggered_at' => 'datetime'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
