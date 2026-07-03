<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sesion extends Model
{
    use SoftDeletes;
    protected $table = 'sesiones';

    protected $fillable = [
        'user_id',
        'microreto_id',
        'microreto_titulo',
        'proyecto_titulo',
        'proyecto_uuid',
        'fecha',
        'centro_educativo',
        'ciclo_formativo',
        'curso',
        'grupo',
        'num_alumnos',
        'notas',
        'num_equipos',
        'alumnados',
        'codigo_clase',
    ];

    protected $casts = [
        'fecha'        => 'date:Y-m-d',
        'num_alumnos'  => 'integer',
        'num_equipos'  => 'integer',
        'microreto_id' => 'integer',
        'alumnados'    => 'array',
    ];

    public function docente()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function microreto()
    {
        return $this->belongsTo(Microreto::class);
    }

    public function microproyectos()
    {
        return $this->hasMany(Microproyecto::class);
    }
}
