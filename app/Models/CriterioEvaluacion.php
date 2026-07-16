<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CriterioEvaluacion extends Model
{
    protected $table = 'criterios_evaluacion';

    // Un CE pertenece a un RA
    // Nota: la columna se llamó históricamente `idmoduloRA` (legacy del dump SQL
    // importado) pese a no ser FK a `modulos` — se renombró a `idresultadoaprendizaje`
    // para reflejar la relación real (ver migración 2026_07_10_000001).
    public function resultadoAprendizaje()
    {
        return $this->belongsTo(ResultadoAprendizaje::class, 'idresultadoaprendizaje');
    }
}