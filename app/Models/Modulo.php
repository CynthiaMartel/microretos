<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// La tabla se crea con SQL en bruto (ver migración), así que Larastan no infiere las columnas.
/**
 * @property int    $id
 * @property int    $idAreaSC
 * @property int    $idcicloformativo
 * @property string $codigoBOE
 * @property string $nombre
 * @property int    $curso
 * @property int    $horastotales
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ResultadoAprendizaje> $ras
 */
class Modulo extends Model
{
    protected $table = 'modulos';

    // Catálogo oficial del BOE, importado por SQL y de solo lectura en la app: ningún
    // campo es asignable en masa. Si algún día se edita desde la API, añadir aquí la
    // lista blanca concreta (nunca $guarded = []).
    protected $fillable = [];

    // Un Módulo pertenece a un Ciclo
    /** @return BelongsTo<CicloFormativo, $this> */
    public function cicloFormativo(): BelongsTo
    {
        return $this->belongsTo(CicloFormativo::class, 'idcicloformativo');
    }

    // Un Módulo tiene muchos Resultados de Aprendizaje (RA)
    /** @return HasMany<ResultadoAprendizaje, $this> */
    public function ras(): HasMany
    {
        return $this->hasMany(ResultadoAprendizaje::class, 'idmodulo');
    }
}