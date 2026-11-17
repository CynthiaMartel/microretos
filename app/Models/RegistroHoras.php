<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Horas de prácticas de un alumno/a (por alias) en el cronograma de un plan (A3). Las anotan el
 * docente y DuaLab; encuentro, miembro y autor no son fillable: los fija RegistroHorasService.
 */
class RegistroHoras extends Model
{
    protected $table = 'registros_horas';

    protected $fillable = ['fecha', 'horas', 'descripcion'];

    protected $casts = [
        'fecha' => 'date:Y-m-d',
        'horas' => 'decimal:2',
    ];

    /** @return BelongsTo<Encuentro, $this> */
    public function encuentro(): BelongsTo
    {
        return $this->belongsTo(Encuentro::class);
    }

    /** @return BelongsTo<EquipoMiembro, $this> */
    public function miembro(): BelongsTo
    {
        return $this->belongsTo(EquipoMiembro::class, 'equipo_miembro_id');
    }

    /** @return BelongsTo<User, $this> */
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_id');
    }
}
