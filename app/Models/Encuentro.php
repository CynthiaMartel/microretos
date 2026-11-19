<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Encuentro extends Model
{
    use SoftDeletes;
    protected $table = 'encuentros';

    // Etapa del encuentro (columna `tipo`): encuentro creativo del Taller de ideas (aula) o hito
    // del cronograma del plan de prácticas (empresa). Ver CLAUDE.md «Etapas».
    public const TIPO_CREATIVO = 'creativo_etapa1';
    public const TIPO_PLAN     = 'plan_practicas_etapa2';
    public const TIPOS         = [self::TIPO_CREATIVO, self::TIPO_PLAN];

    protected $fillable = [
        'user_id',
        'microproyecto_id',
        'fecha',
        'fecha_fin',
        'planificacion_sesiones',
        'centro_educativo',
        'centro_educativo_id',
        'ciclo_formativo',
        'curso',
        'grupo',
        'num_alumnos',
        'notas',
        'num_equipos',
        'alumnados',
        'codigo_clase',
        'codigo_ia',
    ];

    protected $casts = [
        'fecha'            => 'date:Y-m-d',
        'fecha_fin'        => 'date:Y-m-d',
        'num_alumnos'      => 'integer',
        'num_equipos'      => 'integer',
        'microproyecto_id' => 'integer',
        'planificacion_sesiones' => 'array',
        // Nombres del alumnado: cifrados igual que equipo_miembros.nombre (datos personales)
        'alumnados'        => 'encrypted:array',
    ];

    /**
     * Sesiones del encuentro ([{fases: [0..4]}]). Los encuentros antiguos sin planificación propia
     * usan el calendario que tenía su propuesta (microproyectos.diseno_microproyecto.clases).
     *
     * @return array<int, array{fases: array<int, int>}>
     */
    public function sesiones(): array
    {
        $sesiones = $this->planificacion_sesiones;
        if (!is_array($sesiones) || $sesiones === []) {
            $sesiones = $this->microproyecto?->diseno_microproyecto['clases'] ?? [];
        }

        return array_values(array_filter((array) $sesiones, 'is_array'));
    }

    public function totalSesiones(): int
    {
        return count($this->sesiones());
    }

    // Fecha fin sugerida/mínima a partir de una fecha de inicio: con hitos fechados (cronograma de un
    // plan), la del último hito; si no, según el nº de sesiones (Microproyecto::SEMANAS_POR_CLASE).
    public function fechaFinSugerida(Carbon $fechaInicio): ?Carbon
    {
        $fechas = collect($this->sesiones())->pluck('fecha')->filter();
        if ($fechas->isNotEmpty()) {
            return Carbon::parse($fechas->max());
        }

        $total = $this->totalSesiones();
        if ($total <= 0) return null;

        return $fechaInicio->copy()->addWeeks($total * Microproyecto::SEMANAS_POR_CLASE);
    }

    /** @return BelongsTo<User, $this> */
    public function docente(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    /** @return BelongsTo<Microproyecto, $this> */
    public function microproyecto(): BelongsTo
    {
        return $this->belongsTo(Microproyecto::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasOne<EncuentroValoracionEmpresa, $this> */
    public function valoracionEmpresa(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(EncuentroValoracionEmpresa::class);
    }

    /** @return HasMany<Equipo, $this> */
    public function equipos(): HasMany
    {
        return $this->hasMany(\App\Models\Equipo::class);
    }

    // Docentes con los que el propietario ha compartido explícitamente este encuentro.
    // El pivot 'puede_editar' decide si el colaborador solo puede ver o también mutar.
    /** @return BelongsToMany<User, $this> */
    public function colaboradores(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\User::class, 'encuentro_colaboradores')
            ->withPivot('puede_editar')
            ->withTimestamps();
    }

    // Encuentros que el usuario puede VER: los suyos, o aquellos donde es colaborador
    // (con cualquier nivel de permiso). Admin ve todo su centro; superadmin, todo.
    public function scopeVisiblesPara($query, \App\Models\User $user)
    {
        // Gestión de prácticas ve los encuentros de todos los centros (elige alumnado y sigue planes)
        if ($user->isSuperAdmin() || $user->trabajaEnPracticas()) {
            return $query;
        }

        if ($user->isAdmin() && $user->centro_educativo_id) {
            return $query->where('centro_educativo_id', $user->centro_educativo_id);
        }

        return $query->where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->orWhereHas('colaboradores', fn ($cq) => $cq->where('user_id', $user->id));
        });
    }

    /**
     * Misma regla que scopeEditablesPara() sobre un encuentro ya cargado (sin consulta si
     * 'colaboradores' viene cargada). Decide quién recibe los códigos y tokens de acceso al
     * workspace del alumnado: un colaborador de solo lectura ve el progreso, nunca los
     * códigos (con ellos podría entrar en el workspace y modificar el trabajo del grupo).
     */
    public function esEditablePor(?\App\Models\User $user): bool
    {
        if (!$user) {
            return false;
        }
        if ($user->isSuperAdmin()) {
            return true;
        }
        // Gestión de prácticas solo edita los cronogramas de los planes, no los encuentros del taller
        if ($user->trabajaEnPracticas()) {
            return $this->tipo === self::TIPO_PLAN;
        }
        if ($user->isAdmin() && $user->centro_educativo_id) {
            return (int) $this->centro_educativo_id === (int) $user->centro_educativo_id;
        }
        if ((int) $this->user_id === (int) $user->id) {
            return true;
        }

        $colaboradores = $this->relationLoaded('colaboradores') ? $this->colaboradores : $this->colaboradores()->get();

        return $colaboradores->contains(fn ($c) => (int) $c->id === (int) $user->id && (bool) data_get($c, 'pivot.puede_editar'));
    }

    // Encuentros que el usuario puede MUTAR: los suyos, o aquellos donde es colaborador
    // con 'puede_editar' explícito. Un colaborador de solo lectura no pasa este scope.
    public function scopeEditablesPara($query, \App\Models\User $user)
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        if ($user->trabajaEnPracticas()) {
            return $query->where('tipo', self::TIPO_PLAN);
        }

        if ($user->isAdmin() && $user->centro_educativo_id) {
            return $query->where('centro_educativo_id', $user->centro_educativo_id);
        }

        return $query->where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->orWhereHas('colaboradores', fn ($cq) => $cq->where('user_id', $user->id)->where('puede_editar', true));
        });
    }
}
