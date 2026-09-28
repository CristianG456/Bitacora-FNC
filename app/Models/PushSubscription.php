<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushSubscription extends Model
{
    protected $fillable = [
        'user_id', 'endpoint_hash', 'endpoint', 'p256dh', 'auth',
        'content_encoding', 'user_agent', 'device_name', 'last_used_at',
    ];

    protected $casts = ['last_used_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function hashEndpoint(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }
}
