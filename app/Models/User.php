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
    // Capta empresas reales en entrevista (app comercial.dualab.es): sin centro ni acceso a la app de docentes
    const ROLE_COMERCIAL  = 5;
    // Roles de DuaLab Central, con el nombre del espacio que abren (Cynthia, 19-nov):
    // red_dualab: el espacio «Red DuaLab» — formularios y correo de info@, solicitudes de retos,
    // captaciones y soporte (Base de datos, Usuarios y Papelera siguen siendo solo del superadmin).
    // Antes se llamaba «atención al cliente»; el número no cambia. Sin centro ni app de docentes.
    const ROLE_RED_DUALAB = 6;
    // gestion_practicas: el espacio «Seguimiento de prácticas» — ve los encuentros y planes de todos
    // los centros, marca destacados y crea, valida y fecha los planes de proyecto (no las propuestas
    // del taller). En la app de docentes solo entra en las rutas de prácticas (EnsureIsDocente).
    const ROLE_GESTION_PRACTICAS = 7;
    // admin_central_dualab: los dos espacios anteriores (Red DuaLab y Seguimiento de prácticas) y Usuarios
    // (cualquier cuenta menos superadmin). Base de datos y Papelera, información sensible, solo del superadmin.
    const ROLE_ADMIN_CENTRAL_DUALAB = 8;

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
    public function isComercial(): bool  { return $this->role === self::ROLE_COMERCIAL; }
    public function isRedDualab(): bool { return $this->role === self::ROLE_RED_DUALAB; }
    public function isGestionPracticas(): bool { return $this->role === self::ROLE_GESTION_PRACTICAS; }
    public function isAdminCentralDualab(): bool { return $this->role === self::ROLE_ADMIN_CENTRAL_DUALAB; }

    /**
     * Trabaja en el espacio «Red DuaLab» de Central (sin el superadmin, que lo ve todo por su cuenta).
     * Las comprobaciones de acceso usan esto, no el rol suelto: central_dualab tiene los dos espacios.
     */
    public function trabajaEnRedDualab(): bool
    {
        return $this->isRedDualab() || $this->isAdminCentralDualab();
    }

    /** Trabaja en el espacio «Seguimiento de prácticas» (sin el superadmin). */
    public function trabajaEnPracticas(): bool
    {
        return $this->isGestionPracticas() || $this->isAdminCentralDualab();
    }

    /**
     * Gestiona las cuentas de todos los centros (Central → Usuarios). El admin de centro solo las del suyo;
     * crear superadmins y tocar sus cuentas sigue siendo solo del superadmin (requests y checkScope).
     */
    public function gestionaTodosLosUsuarios(): bool
    {
        return $this->isSuperAdmin() || $this->isAdminCentralDualab();
    }

    /** Crea planes de cualquier centro a nombre del docente de la propuesta (DuaLab). */
    public function gestionaTodosLosPlanes(): bool
    {
        return $this->isSuperAdmin() || $this->trabajaEnPracticas();
    }

    /** Roles sin centro ni empresa: trabajan para DuaLab (app comercial, Central). */
    public function esPersonalDualab(): bool
    {
        return in_array($this->role, [self::ROLE_COMERCIAL, self::ROLE_RED_DUALAB, self::ROLE_GESTION_PRACTICAS, self::ROLE_ADMIN_CENTRAL_DUALAB], true);
    }

    public function roleName(): string
    {
        return match($this->role) {
            self::ROLE_DOCENTE    => 'Docente',
            self::ROLE_EMPRESA    => 'Empresa',
            self::ROLE_ADMIN      => 'Administrador',
            self::ROLE_SUPERADMIN => 'Superadministrador',
            self::ROLE_COMERCIAL  => 'Comercial',
            self::ROLE_RED_DUALAB => 'Red DuaLab',
            self::ROLE_GESTION_PRACTICAS => 'Gestión de prácticas',
            self::ROLE_ADMIN_CENTRAL_DUALAB => 'Admin Central DuaLab',
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
