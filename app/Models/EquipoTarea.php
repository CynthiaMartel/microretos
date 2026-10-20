<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipoTarea extends Model
{
    protected $table = 'equipo_tareas';

    protected $fillable = ['equipo_id', 'descripcion', 'tipo', 'obligatoria', 'responsable', 'estado', 'orden'];

    protected $casts = [
        'orden'       => 'integer',
        'obligatoria' => 'boolean',
        // Nombre del alumno responsable: cifrado igual que equipo_miembros.nombre
        'responsable' => 'encrypted',
    ];

    /** @return BelongsTo<Equipo, $this> */
    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class);
    }
}
