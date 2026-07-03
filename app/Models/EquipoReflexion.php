<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipoReflexion extends Model
{
    protected $table = 'equipo_reflexiones';

    protected $fillable = ['equipo_id', 'tipo', 'autor_nombre', 'respuestas'];

    protected $casts = [
        'respuestas' => 'array',
    ];

    public function equipo()
    {
        return $this->belongsTo(Equipo::class);
    }
}
