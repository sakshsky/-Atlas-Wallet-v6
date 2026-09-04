<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycSubmission extends Model
{
    protected $fillable = ['user_id', 'legal_name', 'date_of_birth', 'country_code', 'document_type', 'document_number', 'document_front_path', 'document_back_path', 'status', 'review_notes', 'reviewed_by', 'reviewed_at'];
    protected $hidden = ['document_number', 'document_front_path', 'document_back_path'];
    protected function casts(): array { return ['date_of_birth' => 'date', 'document_number' => 'encrypted', 'reviewed_at' => 'datetime']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}
