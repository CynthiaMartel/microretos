<?php

namespace App\Models;

use App\Support\AliasGenerator;
use Illuminate\Database\Eloquent\Model;

class EquipoMiembro extends Model
{
    protected $table = 'equipo_miembros';

    protected $fillable = ['equipo_id', 'nombre', 'alias', 'rol', 'fortalezas', 'puntos_mejora'];

    protected $casts = [
        'nombre'        => 'encrypted',
        'fortalezas'    => 'array',
        'puntos_mejora' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (EquipoMiembro $miembro) {
            if (empty($miembro->alias) && !empty($miembro->nombre)) {
                $posicion = $miembro->equipo_id
                    ? static::where('equipo_id', $miembro->equipo_id)->count()
                    : 0;
                $miembro->alias = AliasGenerator::generar($miembro->nombre, $posicion);
            }
        });
    }

    public function equipo()
    {
        return $this->belongsTo(Equipo::class);
    }
}
