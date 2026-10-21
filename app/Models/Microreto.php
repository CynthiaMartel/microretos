<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Microreto extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'uuid',
        'titulo', 'subtitulo',
        'demo_id',          // FK → demos (para microretos de demo)
        'empresa_id',       // FK (nueva)
        'empresa_nombre',   // legacy — se mantiene hasta completar backfill
        'quien_es', 'dia_a_dia', 'pregunta_reto',
        'dificultades', 'que_necesitan', 'limitaciones', 'prototipos',
        'ods_sugeridos', 'soft_skills', 'evaluacion_oficial', 'tips_profesorado', 'variantes',
        'nivel_grupo',
        'curso',
        'ciclo_id',         // FK (nueva)
        'ciclo',            // legacy — se mantiene hasta completar backfill
        'modulo', 'multimodulo', 'ra_ce_origen', 'duracion', 'es_simulado',
        'visible_publico',
    ];

    // Genera un UUID automáticamente al crear cada microreto nuevo
    protected static function booted(): void
    {
        static::creating(function (Microreto $microreto) {
            if (empty($microreto->uuid)) {
                $microreto->uuid = (string) Str::uuid();
            }
        });

        // centro_id (no fillable) sale siempre de la empresa: así el reto sigue siendo del
        // centro aunque la empresa se purgue después (empresa_id → NULL)
        static::saving(function (Microreto $microreto) {
            if ($microreto->empresa_id && ($microreto->isDirty('empresa_id') || !$microreto->centro_id)) {
                $microreto->centro_id = Empresa::withTrashed()->whereKey($microreto->empresa_id)->value('centro_id');
            }
        });
    }

    public const EMPRESA_ACTIVA    = 'activa';
    public const EMPRESA_PAPELERA  = 'papelera';
    public const EMPRESA_ELIMINADA = 'eliminada';

    /** Estado de la empresa del reto (null en retos de demo, que no tienen empresa). */
    public function estadoEmpresa(): ?string
    {
        if ($this->empresa_id === null) {
            return $this->demo_id ? null : self::EMPRESA_ELIMINADA;
        }
        // La relación incluye la papelera; sin fila, se borró a mano de la BD
        return match (true) {
            $this->empresa === null      => self::EMPRESA_ELIMINADA,
            $this->empresa->trashed()    => self::EMPRESA_PAPELERA,
            default                      => self::EMPRESA_ACTIVA,
        };
    }

    /** Docente/admin: el reto es de su centro (propio o, en legacy sin centro_id, vía la empresa). */
    public function perteneceAlCentroDe(User $user): bool
    {
        if (!$user->centro_educativo_id) return false;
        if ($this->centro_id) return $this->centro_id === $user->centro_educativo_id;
        return (bool) $this->empresa?->perteneceAlCentroDe($user);
    }

    /**
     * Mismo criterio que perteneceAlCentroDe(), para filtrar listados.
     *
     * @param  Builder<Microreto>  $query
     * @return Builder<Microreto>
     */
    public function scopeDelCentroDe(Builder $query, User $user): Builder
    {
        $centroId     = $user->centro_educativo_id;
        $centroNombre = $user->centroEducativo?->nombre;

        return $query->where(function (Builder $q) use ($centroId, $centroNombre) {
            $q->where('microretos.centro_id', $centroId)
                ->orWhere(fn (Builder $legacy) => $legacy->whereNull('microretos.centro_id')
                    ->whereHas('empresa', function ($e) use ($centroId, $centroNombre) {
                        $e->where('centro_id', $centroId);
                        if ($centroNombre) $e->orWhere('centro_educativo', $centroNombre);
                    }));
        });
    }

    protected $casts = [
        'dificultades'       => 'array',
        'que_necesitan'      => 'array',
        'limitaciones'       => 'array',
        'prototipos'         => 'array',
        'ods_sugeridos'      => 'array',
        'soft_skills'        => 'array',
        'evaluacion_oficial' => 'array',
        'diagnostico_empresa' => 'array', // copia de la empresa al guardar (no fillable: la pone el backend)
        'tips_profesorado'   => 'array',
        'variantes'          => 'array',
        'es_simulado'        => 'boolean',
        'multimodulo'        => 'boolean',
        'visible_publico'    => 'boolean',
    ];

    public function demo()
    {
        return $this->belongsTo(Demo::class, 'demo_id');
    }

    /**
     * Incluye empresas en la papelera: al borrar una empresa sus retos siguen en la biblioteca
     * del centro (lo promete EliminarEmpresaModal) y los filtros por centro con
     * whereHas('empresa') no deben dejarlos fuera. El escaparate público sí los oculta.
     *
     * @return BelongsTo<Empresa, $this>
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id')->withTrashed();
    }

    public function cicloFormativo()
    {
        return $this->belongsTo(CicloFormativo::class, 'ciclo_id');
    }

    /** @return HasMany<Microproyecto, $this> */
    public function microproyectos(): HasMany
    {
        return $this->hasMany(Microproyecto::class, 'microreto_id');
    }
}
