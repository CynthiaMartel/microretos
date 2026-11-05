<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un texto legal aceptado (o rechazado, en las cesiones) dentro de una firma. Solo se inserta. */
class EmpresaConsentimiento extends Model
{
    protected $fillable = [];

    protected $casts = [
        'aceptado' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \LogicException('Un consentimiento firmado no se puede modificar.');
        });
    }

    /** @return BelongsTo<EmpresaFirma, $this> */
    public function firma(): BelongsTo
    {
        return $this->belongsTo(EmpresaFirma::class, 'empresa_firma_id');
    }
}
