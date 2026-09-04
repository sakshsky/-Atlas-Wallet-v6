<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class UserExport extends Model { protected $fillable = ['reference', 'user_id', 'status', 'path', 'expires_at', 'error']; protected $hidden = ['path']; protected $casts = ['expires_at' => 'datetime']; }
