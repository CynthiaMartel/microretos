<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipoTarea extends Model
{
    protected $table = 'equipo_tareas';

    protected $fillable = ['equipo_id', 'descripcion', 'responsable', 'estado', 'orden'];

    protected $casts = [
        'orden' => 'integer',
    ];

    public function equipo()
    {
        return $this->belongsTo(Equipo::class);
    }
}
