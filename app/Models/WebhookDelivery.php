<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WebhookDelivery extends Model { protected $guarded = []; protected $casts = ['delivered_at' => 'datetime']; }
