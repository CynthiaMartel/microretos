<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read \Illuminate\Database\Eloquent\Relations\Pivot|null $pivot  solo al cargarla desde Empresa::familias()
 */
class Familia extends Model
{
    use SoftDeletes;
    protected $table    = 'familias';
    protected $fillable = ['nombre', 'imagen_url'];

    /** @return BelongsToMany<Empresa, $this> */
    public function empresas(): BelongsToMany
    {
        return $this->belongsToMany(Empresa::class, 'empresa_familia', 'familia_id', 'empresa_id');
    }

    /** @return HasMany<CicloFormativo, $this> */
    public function ciclos(): HasMany
    {
        return $this->hasMany(CicloFormativo::class, 'familia_id');
    }

    // Familias con lo mínimo para generar un reto: algún ciclo con módulos que tengan
    // RA y criterios de evaluación. Con $ciclosIds (los de un centro), solo entre esos.
    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Familia>  $query
     * @param  \Illuminate\Support\Collection<array-key, mixed>|null  $ciclosIds
     * @return \Illuminate\Database\Eloquent\Builder<Familia>
     */
    public function scopeConDatosParaRetos($query, ?\Illuminate\Support\Collection $ciclosIds = null)
    {
        return $query->whereHas('ciclos', fn ($q) => $q->conDatosParaRetos($ciclosIds));
    }
}
