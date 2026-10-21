<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// La tabla se crea con SQL en bruto (ver migración), así que Larastan no infiere las columnas.
/**
 * @property int         $id
 * @property int         $idCiclo
 * @property string      $nombre
 * @property string|null $familia       nombre legacy; la relación es familia()
 * @property int|null    $familia_id
 * @property string|null $grado
 * @property string|null $referenciaBOE
 * @property string      $siglasGrado
 */
class CicloFormativo extends Model
{
    use SoftDeletes;
    protected $table    = 'ciclos_formativos';
    // La tabla (del dump SQL original) no tiene created_at/updated_at: sin esto, crear o
    // editar un ciclo desde «Base de datos» fallaba con un 500.
    public $timestamps = false;
    protected $fillable = ['idCiclo', 'nombre', 'familia', 'familia_id', 'grado', 'referenciaBOE', 'siglasGrado'];

    public function familia()
    {
        return $this->belongsTo(Familia::class, 'familia_id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<Modulo, $this> */
    public function modulos()
    {
        return $this->hasMany(Modulo::class, 'idcicloformativo');
    }

    public function centros()
    {
        return $this->belongsToMany(CentroEducativo::class, 'centro_ciclo', 'ciclo_id', 'centro_id');
    }

    public function microretos()
    {
        return $this->hasMany(Microreto::class, 'ciclo_id');
    }

    // Ciclos con lo mínimo para generar un reto: módulos con RA y criterios de evaluación
    // (es lo que el generador envía a la IA). Con $ciclosIds, solo entre esos ciclos.
    /**
     * @param  \Illuminate\Database\Eloquent\Builder<CicloFormativo>  $query
     * @param  Collection<array-key, mixed>|null  $ciclosIds
     * @return \Illuminate\Database\Eloquent\Builder<CicloFormativo>
     */
    public function scopeConDatosParaRetos($query, ?Collection $ciclosIds = null)
    {
        if ($ciclosIds !== null) {
            $query->whereIn($this->qualifyColumn('id'), $ciclosIds);
        }
        return $query->whereHas('modulos.ras.criteriosEvaluacion');
    }

    /**
     * Ids de los ciclos que imparte un centro, o null si el centro aún no tiene ciclos
     * vinculados: en ese caso no se filtra (centro recién creado o sin configurar) y la
     * empresa sigue siendo usable con cualquier ciclo de su familia.
     *
     * @return Collection<int, mixed>|null
     */
    public static function idsDelCentro(?string $centroNombre): ?Collection
    {
        if (!$centroNombre) {
            return null;
        }

        $centro = CentroEducativo::where('nombre', $centroNombre)->first();
        $ids = $centro
            ? DB::table('centro_ciclo')->where('centro_id', $centro->id)->pluck('ciclo_id')
            : DB::table('centro_ciclo')->where('centro_educativo', $centroNombre)->pluck('ciclo_id'); // legacy

        return $ids->isNotEmpty() ? $ids : null;
    }
}
