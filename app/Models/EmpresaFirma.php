<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Firma del empresario entrevistado (evidencia de los consentimientos). Solo se inserta:
 * cualquier intento de modificarla después lanza una excepción.
 */
class EmpresaFirma extends Model
{
    // Todo lo fija CaptacionEmpresaService; nada llega directo de un formulario.
    protected $fillable = [];

    // DNI, email, firma y huella del navegador nunca salen en JSON
    protected $hidden = ['firmante_dni', 'firmante_email', 'firma_path', 'ip', 'user_agent'];

    protected $casts = [
        'firmante_dni'   => 'encrypted',
        'firmante_email' => 'encrypted',
        'snapshot'       => 'array',
        'firmado_at'     => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (EmpresaFirma $firma) {
            if (empty($firma->uuid)) {
                $firma->uuid = (string) Str::uuid();
            }
        });

        static::updating(function () {
            throw new \LogicException('Una firma no se puede modificar.');
        });
    }

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function comercial(): BelongsTo
    {
        return $this->belongsTo(User::class, 'comercial_user_id');
    }

    /** @return HasMany<EmpresaConsentimiento, $this> */
    public function consentimientos(): HasMany
    {
        return $this->hasMany(EmpresaConsentimiento::class, 'empresa_firma_id');
    }
}
