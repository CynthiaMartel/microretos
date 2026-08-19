<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use App\Models\MicroproyectoRecurso;

class Microproyecto extends Model
{
    use SoftDeletes;

    // Heurística única para convertir "clases" (duración de fase) en calendario —
    // toda sugerencia/validación de fecha_fin de sesiones pasa por aquí.
    public const SEMANAS_POR_CLASE = 1;
    protected $fillable = [
        'uuid', 'user_id', 'microreto_id', 'empresa_id', 'centro_id', 'familia_id', 'ciclo_id',
        'titulo', 'curso',
        'datos_empresa', 'datos_centro', 'equipo', 'modulos_seleccionados', 'ra_ce',
        'evaluacion_oficial',
        'fundamentacion', 'diseno_reto', 'diseno_microproyecto', 'resumen',
        'objetivos', 'kpis', 'validacion_empresa',
        'paso_actual', 'estado', 'token_empresa', 'empresa_validado',
        'empresa_no_valida_aun', 'enviado_a_empresa_mail', 'docente_validado',
    ];

    protected $casts = [
        'datos_empresa'          => 'array',
        'datos_centro'           => 'array',
        'equipo'                 => 'array',
        'modulos_seleccionados'  => 'array',
        'ra_ce'                  => 'string',
        'evaluacion_oficial'     => 'array',
        'fundamentacion'         => 'array',
        'diseno_reto'            => 'array',
        'diseno_microproyecto'   => 'array',
        'resumen'                => 'array',
        'objetivos'              => 'array',
        'kpis'                   => 'array',
        'validacion_empresa'     => 'array',
        'empresa_validado'        => 'boolean',
        'empresa_no_valida_aun'   => 'boolean',
        'enviado_a_empresa_mail'  => 'boolean',
        'docente_validado'        => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Microproyecto $mp) {
            if (empty($mp->uuid)) {
                $mp->uuid = (string) Str::uuid();
            }
            if (empty($mp->token_empresa)) {
                $mp->token_empresa = Str::random(40);
            }
        });
    }

    public function recursos()
    {
        return $this->hasMany(MicroproyectoRecurso::class)->orderBy('created_at');
    }

    public function microreto()
    {
        return $this->belongsTo(Microreto::class);
    }

    public function encuentros()
    {
        return $this->hasMany(Encuentro::class);
    }

    public function docente()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    // El proyecto es visible si: eres el docente que lo creó, o tienes acceso (propio o
    // colaborador) a alguno de sus encuentros. Admin ve todo su centro; superadmin, todo.
    public function esVisiblePara(\App\Models\User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isAdmin()) {
            return $user->centro_educativo_id !== null && $this->centro_id === $user->centro_educativo_id;
        }

        if ($this->user_id === $user->id) {
            return true;
        }

        return $this->encuentros()->visiblesPara($user)->exists();
    }

    // Igual que esVisiblePara pero exige permiso de edición sobre el encuentro (no basta
    // con ser colaborador de solo lectura).
    public function esEditablePara(\App\Models\User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isAdmin()) {
            return $user->centro_educativo_id !== null && $this->centro_id === $user->centro_educativo_id;
        }

        if ($this->user_id === $user->id) {
            return true;
        }

        return $this->encuentros()->editablesPara($user)->exists();
    }

    // Nº de clases del calendario definido en el paso 5 del wizard (una clase
    // puede cubrir varias fases a la vez) — se usa para sugerir/validar la
    // fecha_fin de las sesiones asociadas.
    public function totalClasesEstimadas(): int
    {
        return count($this->diseno_microproyecto['clases'] ?? []);
    }

    // Fecha fin sugerida/mínima a partir de una fecha de inicio, según el total de
    // clases estimadas. Null si el proyecto no tiene fases con duración definida.
    public function fechaFinSugerida(Carbon $fechaInicio): ?Carbon
    {
        $totalClases = $this->totalClasesEstimadas();
        if ($totalClases <= 0) return null;

        return $fechaInicio->copy()->addWeeks($totalClases * self::SEMANAS_POR_CLASE);
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function centroEducativo()
    {
        return $this->belongsTo(CentroEducativo::class, 'centro_id');
    }

    public function familia()
    {
        return $this->belongsTo(Familia::class);
    }

    public function cicloFormativo()
    {
        return $this->belongsTo(CicloFormativo::class, 'ciclo_id');
    }

    public function equipos()
    {
        return $this->hasMany(Equipo::class)->orderBy('id');
    }
}
