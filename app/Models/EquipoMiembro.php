<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipoMiembro extends Model
{
    protected $table = 'equipo_miembros';

    protected $fillable = ['equipo_id', 'nombre', 'rol', 'fortalezas', 'puntos_mejora'];

    protected $casts = [
        'fortalezas'    => 'array',
        'puntos_mejora' => 'array',
    ];

    public function equipo()
    {
        return $this->belongsTo(Equipo::class);
    }
}
