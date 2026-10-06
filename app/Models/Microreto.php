<?php

namespace App\Models;

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

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
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
