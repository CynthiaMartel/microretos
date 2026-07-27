<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipoPrototipo extends Model
{
    protected $fillable = [
        'equipo_id', 'contexto', 'filename', 'url', 'public_id',
        'resource_type', 'mime', 'size', 'label',
    ];

    public function equipo()
    {
        return $this->belongsTo(Equipo::class);
    }
}
