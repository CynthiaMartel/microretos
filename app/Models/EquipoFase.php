<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipoFase extends Model
{
    protected $table = 'equipo_fases';

    protected $fillable = [
        'equipo_id', 'numero_fase', 'datos',
        'completada', 'fecha_completada',
        'validado_docente', 'fecha_validacion_docente',
        'nota_docente', 'observaciones_docente',
    ];

    protected $casts = [
        'datos'                    => 'array',
        'completada'               => 'boolean',
        'validado_docente'         => 'boolean',
        'fecha_completada'         => 'datetime',
        'fecha_validacion_docente' => 'datetime',
        'nota_docente'             => 'decimal:2',
    ];

    /** @return BelongsTo<Equipo, $this> */
    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class);
    }
}
