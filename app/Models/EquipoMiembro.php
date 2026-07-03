<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipoMiembro extends Model
{
    protected $table = 'equipo_miembros';

    protected $fillable = ['equipo_id', 'nombre', 'rol'];

    public function equipo()
    {
        return $this->belongsTo(Equipo::class);
    }
}
