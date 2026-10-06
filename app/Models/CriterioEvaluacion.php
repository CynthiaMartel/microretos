<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// La tabla se crea con SQL en bruto (ver migración), así que Larastan no infiere las columnas.
/**
 * @property int    $id
 * @property int    $idresultadoaprendizaje
 * @property string $ce
 */
class CriterioEvaluacion extends Model
{
    protected $table = 'criterios_evaluacion';

    // Catálogo oficial del BOE, importado por SQL y de solo lectura en la app: ningún
    // campo es asignable en masa. Si algún día se edita desde la API, añadir aquí la
    // lista blanca concreta (nunca $guarded = []).
    protected $fillable = [];

    // Un CE pertenece a un RA
    // Nota: la columna se llamó históricamente `idmoduloRA` (legacy del dump SQL
    // importado) pese a no ser FK a `modulos` — se renombró a `idresultadoaprendizaje`
    // para reflejar la relación real (ver migración 2026_07_10_000001).
    /** @return BelongsTo<ResultadoAprendizaje, $this> */
    public function resultadoAprendizaje(): BelongsTo
    {
        return $this->belongsTo(ResultadoAprendizaje::class, 'idresultadoaprendizaje');
    }
}