<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipoReflexion extends Model
{
    protected $table = 'equipo_reflexiones';

    protected $fillable = ['equipo_id', 'tipo', 'autor_nombre', 'respuestas'];

    protected $casts = [
        'respuestas' => 'array',
        // Nombre del alumno (reflexión individual): cifrado igual que equipo_miembros.nombre
        'autor_nombre' => 'encrypted',
    ];

    public function equipo()
    {
        return $this->belongsTo(Equipo::class);
    }
}
