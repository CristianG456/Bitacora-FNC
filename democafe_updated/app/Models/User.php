<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'area',
        'rol_id',
        'activo',
        'force_password_change',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'        => 'datetime',
            'password'                 => 'hashed',
            'activo'                   => 'boolean',
            'force_password_change'    => 'boolean',
        ];
    }

    // ─── Relaciones ────────────────────────────────────────────────

    public function role()
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }

    public function casos()
    {
        return $this->belongsToMany(Caso::class, 'caso_usuario')
                    ->withPivot('estado', 'fecha_asignacion', 'activo')
                    ->withTimestamps();
    }

    public function tareas()
    {
        return $this->hasMany(Tarea::class, 'user_id');
    }

    public function notificaciones()
    {
        return $this->hasMany(Notificacion::class, 'user_id');
    }

    public function bitacoras()
    {
        return $this->hasMany(Bitacora::class, 'user_id');
    }

    // ─── Helpers de rol ────────────────────────────────────────────

    public function esAdministrador(): bool
    {
        return $this->role && $this->role->nombre === 'Administrador';
    }

    public function esJuridica(): bool
    {
        return $this->role && $this->role->nombre === 'Juridica';
    }

    public function esConsultor(): bool
    {
        return $this->role && $this->role->nombre === 'Consultor';
    }

    public function tieneRol(string $rol): bool
    {
        return $this->role && $this->role->nombre === $rol;
    }

    public function tieneAlgunRol(array $roles): bool
    {
        return $this->role && in_array($this->role->nombre, $roles);
    }

    public function notificacionesSinLeer(): int
    {
        return $this->notificaciones()->where('leido', false)->count();
    }
}
