<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

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
        'modulo', 'multimodulo', 'duracion', 'es_simulado',
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
        'tips_profesorado'   => 'array',
        'variantes'          => 'array',
        'es_simulado'        => 'boolean',
        'multimodulo'        => 'boolean',
    ];

    public function demo()
    {
        return $this->belongsTo(Demo::class, 'demo_id');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function cicloFormativo()
    {
        return $this->belongsTo(CicloFormativo::class, 'ciclo_id');
    }
}
