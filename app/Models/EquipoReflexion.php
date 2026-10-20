<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipoReflexion extends Model
{
    protected $table = 'equipo_reflexiones';

    protected $fillable = ['equipo_id', 'tipo', 'autor_nombre', 'respuestas'];

    protected $casts = [
        'respuestas' => 'array',
        // Nombre del alumno (reflexión individual): cifrado igual que equipo_miembros.nombre
        'autor_nombre' => 'encrypted',
    ];

    /** @return BelongsTo<Equipo, $this> */
    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class);
    }
}
