<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'activo',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    /* Comprueba si el usuario tiene el rol 'user'. */
      public function esUsuario(): bool
    {
        return $this->role === 'user';
    }

    /* Comprueba si el usuario tiene el rol 'admin'. */
    public function esAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /* Comprueba si el usuario tiene el rol 'super_Admin'. */

    public function esSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /* Comprueba si el usuario esta activo */

    public function estaActivo(): bool
    {
        return $this->activo === true;
    }

    /* Comprueba si el usuario tiene alguno de los roles proporcionados.
     * Ejemplo: $user->tieneRol('admin', 'super_admin') */


    public function tieneRol(...$roles): bool
    {
        return in_array($this->role, $roles);
    }

    public function reservas()
    {
        return $this->hasMany(Reserva::class, 'user_id');
    }

    public function pagos()
    {
        return $this->hasMany(Pago::class, 'user_id');
    }

    public function reembolsos()
    {
        return $this->hasMany(Reembolso::class, 'user_id');
    }

    public function reembolsosProcesados()
    {
        return $this->hasMany(Reembolso::class, 'procesado_por');
    }
}

