<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use App\Notifications\RestablecerContrasena;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    const ROLE_SUPERADMIN = 1;
    const ROLE_DOCENTE    = 2;
    const ROLE_EMPRESA    = 3;
    const ROLE_ADMIN      = 4;

    // role, is_blocked y email_verified_at quedan fuera a propósito: son campos
    // sensibles y solo se asignan de forma explícita (ver AdminUserController).
    protected $fillable = [
        'name',
        'email',
        'password',
        'centro_educativo_id',
        'empresa_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    // "Recordarme" deja en el navegador una cookie que vuelve a abrir sesión aunque se
    // borren las filas de `sessions`. Todo lo que hoy cierra sesiones (cambio de
    // contraseña propio o por un admin, restablecimiento, bloqueo) debe invalidarla
    // también: se rota remember_token aquí para cubrir todos esos caminos a la vez.
    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if (!$user->exists) return;
            if ($user->isDirty('password') || ($user->isDirty('is_blocked') && $user->is_blocked)) {
                $user->setRememberToken(Str::random(60));
            }
        });
    }

    // "Recordarme" en el login: no para superadmin (acceso total, siempre sesión corta
    // con cierre por inactividad)
    public function puedeRecordarSesion(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_DOCENTE, self::ROLE_EMPRESA], true);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new RestablecerContrasena($token));
    }

    protected function casts(): array
    {
        return [
            'email_verified_at'   => 'datetime',
            'password'            => 'hashed',
            'password_changed_at' => 'datetime',
            'role'                => 'integer',
            'is_blocked'          => 'boolean',
        ];
    }

    public function isSuperAdmin(): bool { return $this->role === self::ROLE_SUPERADMIN; }
    public function isAdmin(): bool      { return $this->role === self::ROLE_ADMIN; }
    public function isDocente(): bool    { return $this->role === self::ROLE_DOCENTE; }
    public function isEmpresa(): bool    { return $this->role === self::ROLE_EMPRESA; }

    public function roleName(): string
    {
        return match($this->role) {
            self::ROLE_DOCENTE    => 'Docente',
            self::ROLE_EMPRESA    => 'Empresa',
            self::ROLE_ADMIN      => 'Administrador',
            self::ROLE_SUPERADMIN => 'Superadministrador',
            default               => 'Desconocido',
        };
    }

    // El superadmin siempre es operativo. Todos los demás necesitan activación y no estar bloqueados.
    /** @return BelongsTo<CentroEducativo, $this> */
    public function centroEducativo(): BelongsTo
    {
        return $this->belongsTo(\App\Models\CentroEducativo::class, 'centro_educativo_id');
    }

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Empresa::class, 'empresa_id');
    }

    /**
     * Módulos que imparte el docente (pivot docente_modulo). La familia se deduce del ciclo
     * de cada módulo. Ver ModulosDocenteService.
     *
     * @return BelongsToMany<Modulo, $this>
     */
    public function modulos(): BelongsToMany
    {
        return $this->belongsToMany(Modulo::class, 'docente_modulo', 'user_id', 'modulo_id')->withTimestamps();
    }

    public function isOperational(): bool
    {
        if ($this->isSuperAdmin()) return true;
        return !$this->is_blocked && $this->email_verified_at !== null;
    }
}
