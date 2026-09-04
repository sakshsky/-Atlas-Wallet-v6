<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class IdempotencyKey extends Model
{
    protected $fillable = ['user_id', 'operation', 'key', 'request_hash', 'owner_token', 'status', 'response_code', 'response_payload'];
    protected $casts = ['response_payload' => 'array'];
}
