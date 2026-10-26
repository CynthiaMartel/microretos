<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Demo extends Model
{
    protected $fillable = [
        // Demo sobre el catálogo DuaLab: plantilla + configuración del Generador
        'empresa_id',
        'familia_id',
        'ciclo_id',
        'modulo_ids',
        'enfoque',
        // Columnas de la demo escrita a mano: en desuso, se borrarán
        'familia_profesional',
        'etiqueta',
        'empresa_nombre',
        'empresa_sector',
        'empresa_tamano',
        'empresa_web',
        'empresa_centro',
        'dia_a_normal',
        'friccion_area',
        'friccion_problema',
        'restricciones',
        'otra_limitacion',
        'lo_que_no_quieren',
        'consecuencias',
        'otra_consecuencia',
        'expectativas_alumno',
        'nivel_grupo',
        'curso_seleccionado',
        'duracion',
        'cantidad_microretos',
    ];

    protected $casts = [
        'modulo_ids'          => 'array',
        'restricciones'       => 'array',
        'consecuencias'       => 'array',
        // TODO: quitar al adaptar el Generador; con 'ambos_cursos' el cast devolvería 0
        'curso_seleccionado'  => 'integer',
        'cantidad_microretos' => 'integer',
    ];

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Empresa, $this> Plantilla del catálogo */
    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Familia, $this> */
    public function familia()
    {
        return $this->belongsTo(Familia::class, 'familia_id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<CicloFormativo, $this> */
    public function ciclo()
    {
        return $this->belongsTo(CicloFormativo::class, 'ciclo_id');
    }

    public function microretos()
    {
        return $this->hasMany(\App\Models\Microreto::class, 'demo_id');
    }
}