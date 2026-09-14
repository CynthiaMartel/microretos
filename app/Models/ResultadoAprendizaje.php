<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResultadoAprendizaje extends Model
{
    protected $table = 'resultados_aprendizaje';

    // Un RA pertenece a un Módulo
    public function modulo()
    {
        return $this->belongsTo(Modulo::class, 'idmodulo');
    }

    // Un RA tiene muchos Criterios de Evaluación (CE)
    // Nota: la FK se llamó históricamente `idmoduloRA` (legacy del dump SQL
    // importado) pese a no apuntar a `modulos` — se renombró a `idresultadoaprendizaje`
    // para reflejar la relación real (ver migración 2026_07_10_000001).
    public function criteriosEvaluacion()
    {
        return $this->hasMany(CriterioEvaluacion::class, 'idresultadoaprendizaje');
    }
}