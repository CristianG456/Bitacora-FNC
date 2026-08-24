<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mensaje extends Model
{
    protected $table = 'mensajes';

    public $timestamps = false;

    protected $fillable = [
        'caso_id',
        'user_id',
        'mensaje',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function caso()
    {
        return $this->belongsTo(Caso::class, 'caso_id');
    }

    public function autor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
