<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Centro educativo captado (entrevista del comercial o formulario web de centros) pendiente
 * de que DuaLab lo valide; al validarlo se da de alta o se enlaza un centro_educativo.
 *   pendiente_validacion → validada | descartada
 */
class CentroCaptacion extends Model
{
    use SoftDeletes;

    protected $table = 'centro_captaciones';

    public const ESTADO_PENDIENTE_VALIDACION = 'pendiente_validacion';
    public const ESTADO_VALIDADA             = 'validada';
    public const ESTADO_DESCARTADA           = 'descartada';

    public const ACCIONES = ['registro', 'info', 'demo']; // mismos botones que el formulario web

    // Datos del formulario. Origen, autor, estado y resultado de la validación no son fillable.
    protected $fillable = [
        'accion', 'centro_nombre', 'direccion', 'provincia', 'municipio', 'familias',
        'contacto_nombre', 'contacto_apellidos', 'contacto_cargo', 'contacto_email',
        'contacto_telefono', 'comentarios',
    ];

    // Datos de una persona: nunca salen en JSON sin pasar por un Resource
    protected $hidden = ['contacto_email', 'contacto_telefono'];

    protected $casts = [
        'familias'          => 'array', // nombres de familia
        'contacto_email'    => 'encrypted',
        'contacto_telefono' => 'encrypted',
        'revisada_at'       => 'datetime',
    ];

    /** @return BelongsTo<CaptacionOrigen, $this> */
    public function origen(): BelongsTo
    {
        return $this->belongsTo(CaptacionOrigen::class, 'origen_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creada_por_user_id');
    }

    /** @return BelongsTo<CentroEducativo, $this> */
    public function centroEducativo(): BelongsTo
    {
        return $this->belongsTo(CentroEducativo::class, 'centro_educativo_id')->withTrashed();
    }

    /** @return HasMany<CentroCaptacionConsentimiento, $this> */
    public function consentimientos(): HasMany
    {
        return $this->hasMany(CentroCaptacionConsentimiento::class, 'centro_captacion_id');
    }
}
