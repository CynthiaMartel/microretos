<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Empresa captada (entrevista del comercial o formulario web) pendiente de que DuaLab la
 * valide. Ningún centro la ve: al validarla se crea o se elige una fila de `empresas`
 * (empresa_id). Ver CaptacionEmpresaService.
 *   borrador → pendiente_validacion → validada | descartada
 */
class EmpresaCaptacion extends Model
{
    use SoftDeletes;

    protected $table = 'empresa_captaciones';

    public const ESTADO_BORRADOR             = 'borrador';
    public const ESTADO_PENDIENTE_VALIDACION = 'pendiente_validacion';
    public const ESTADO_VALIDADA             = 'validada';
    public const ESTADO_DESCARTADA           = 'descartada';

    // Datos de la empresa y del diagnóstico. Origen, autor, estado, firma y resultado de la
    // validación no son fillable: solo los fija CaptacionEmpresaService.
    protected $fillable = [
        'nombre_comercial', 'razon_social', 'cif', 'sector', 'tamano', 'nivel_digitalizacion',
        'web', 'actividad', 'persona_contacto', 'telefono', 'email_general', 'direccion',
        'municipio', 'provincia', 'codigo_postal', 'familias',
        'dia_a_normal', 'friccion_area', 'friccion_problema', 'consecuencias',
        'restricciones', 'lo_que_no_quieren', 'expectativas_alumno', 'notas',
    ];

    // Solo su huella; aun así nunca sale en JSON
    protected $hidden = ['formulario_token_hash'];

    protected $casts = [
        'familias'    => 'array', // [{ nombre, interes }]
        'firmada_at'  => 'datetime',
        'revisada_at' => 'datetime',
        'formulario_expira_at'     => 'datetime',
        'formulario_enviado_at'    => 'datetime',
        'formulario_respondido_at' => 'datetime',
        'respuestas_ia'            => 'array', // campos del diagnóstico (camelCase) que partieron de una sugerencia de la IA
    ];

    public function esBorrador(): bool
    {
        return $this->estado === self::ESTADO_BORRADOR;
    }

    /** El enlace del formulario público se puede usar: sin firmar, sin caducar y sin enviar aún */
    public function formularioAbierto(): bool
    {
        return $this->esBorrador()
            && $this->formulario_token_hash !== null
            && $this->formulario_expira_at?->isFuture()
            && $this->formulario_respondido_at === null;
    }

    /** @return list<string> */
    public function nombresFamilias(): array
    {
        return array_values(array_filter(array_map(fn ($f) => $f['nombre'] ?? null, $this->familias ?? [])));
    }

    /** @return BelongsTo<CaptacionOrigen, $this> */
    public function origen(): BelongsTo
    {
        return $this->belongsTo(CaptacionOrigen::class, 'origen_id');
    }

    /** @return BelongsTo<User, $this> Comercial que la captó (null si llegó por la web) */
    public function creadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creada_por_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function revisadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisada_por_user_id');
    }

    /** @return BelongsTo<Empresa, $this> Empresa de DuaLab en la que se convirtió al validarla */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id')->withTrashed();
    }

    /** @return HasMany<EmpresaFirma, $this> */
    public function firmas(): HasMany
    {
        return $this->hasMany(EmpresaFirma::class, 'empresa_captacion_id');
    }

    /** @return HasMany<MicroretoBorrador, $this> */
    public function borradores(): HasMany
    {
        return $this->hasMany(MicroretoBorrador::class, 'empresa_captacion_id');
    }
}
