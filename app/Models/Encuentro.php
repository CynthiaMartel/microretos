<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Encuentro extends Model
{
    use SoftDeletes;
    protected $table = 'encuentros';

    protected $fillable = [
        'user_id',
        'microproyecto_id',
        'fecha',
        'fecha_fin',
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
        'fecha'            => 'date:Y-m-d',
        'fecha_fin'        => 'date:Y-m-d',
        'num_alumnos'      => 'integer',
        'num_equipos'      => 'integer',
        'microproyecto_id' => 'integer',
        'alumnados'        => 'array',
    ];

    public function docente()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function microproyecto()
    {
        return $this->belongsTo(Microproyecto::class);
    }
}
