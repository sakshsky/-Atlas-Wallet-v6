<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ComplianceCase extends Model
{
    protected $fillable = ['reference', 'user_id', 'subject_type', 'subject_id', 'severity', 'status', 'reason', 'signals', 'assigned_to', 'resolved_by', 'resolution', 'resolved_at'];
    protected function casts(): array { return ['signals' => 'array', 'resolved_at' => 'datetime']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
