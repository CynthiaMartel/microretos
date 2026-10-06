<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
