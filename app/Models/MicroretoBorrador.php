<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Reto generado por un comercial delante de la empresa, pendiente de que el superadmin lo
 * publique (ver BorradorRetoService). Nunca se mezcla con `microretos`.
 */
class MicroretoBorrador extends Model
{
    protected $table = 'microreto_borradores';

    public const ESTADO_PENDIENTE  = 'pendiente';
    public const ESTADO_PUBLICADO  = 'publicado';
    public const ESTADO_DESCARTADO = 'descartado';

    // Todo lo fija BorradorRetoService desde el servidor; nada llega de un formulario.
    protected $fillable = [];

    protected $casts = [
        'datos'       => 'array',
        'revisado_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (MicroretoBorrador $borrador) {
            if (empty($borrador->uuid)) {
                $borrador->uuid = (string) Str::uuid();
            }
        });
    }

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_user_id');
    }

    /** @return BelongsTo<CicloFormativo, $this> */
    public function cicloFormativo(): BelongsTo
    {
        return $this->belongsTo(CicloFormativo::class, 'ciclo_id');
    }
}
