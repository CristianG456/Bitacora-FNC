<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Observacion extends Model
{
    protected $table = 'observaciones';
    
    public $timestamps = false; 

    protected $fillable = [
        'tarea_id',
        'user_id',
        'contenido',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'contenido'  => 'encrypted',
    ];

    public function tarea()
    {
        return $this->belongsTo(Tarea::class, 'tarea_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
