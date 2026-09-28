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
    use HasFactory, \Laravel\Sanctum\HasApiTokens, Notifiable;

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
        'verification_code_hash',
        'verification_code_expires_at',
        'verification_code_attempts',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_hashes',
        'two_factor_last_step',
        'email_change_hash',
        'pending_email',
        'email_change_expires_at',
        'email_change_attempts',
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
            'verification_code_expires_at' => 'datetime',
            'email_change_expires_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_hashes' => 'array',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_last_step' => 'integer',
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
    public function revokeAccess(): void
    {
        $this->forceFill(['pending_email' => null, 'email_change_hash' => null,
            'email_change_expires_at' => null, 'email_change_attempts' => 0])->save();
        $this->tokens()->delete();
        if (config('session.driver') === 'database') {
            \Illuminate\Support\Facades\DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))->where('user_id', $this->id)->delete();
        }
    }
}
