<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// La tabla se crea con SQL en bruto (ver migración), así que Larastan no infiere las columnas.
/**
 * @property int    $id
 * @property int    $idmodulo
 * @property string $ra
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CriterioEvaluacion> $criteriosEvaluacion
 */
class ResultadoAprendizaje extends Model
{
    protected $table = 'resultados_aprendizaje';

    // Catálogo oficial del BOE, importado por SQL y de solo lectura en la app: ningún
    // campo es asignable en masa. Si algún día se edita desde la API, añadir aquí la
    // lista blanca concreta (nunca $guarded = []).
    protected $fillable = [];

    // Un RA pertenece a un Módulo
    /** @return BelongsTo<Modulo, $this> */
    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class, 'idmodulo');
    }

    // Un RA tiene muchos Criterios de Evaluación (CE)
    // Nota: la FK se llamó históricamente `idmoduloRA` (legacy del dump SQL
    // importado) pese a no apuntar a `modulos` — se renombró a `idresultadoaprendizaje`
    // para reflejar la relación real (ver migración 2026_07_10_000001).
    /** @return HasMany<CriterioEvaluacion, $this> */
    public function criteriosEvaluacion(): HasMany
    {
        return $this->hasMany(CriterioEvaluacion::class, 'idresultadoaprendizaje');
    }
}