<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Valoración final de la empresa sobre la propuesta trabajada en un encuentro creativo (P3).
 * El token es de acceso público (sin cuenta): no es fillable, caduca y nunca va en la respuesta
 * de la empresa ni en notificaciones; solo lo ve quien puede editar el encuentro.
 */
class EncuentroValoracionEmpresa extends Model
{
    protected $table = 'encuentro_valoraciones_empresa';

    public const DIAS_VALIDEZ_TOKEN = 60;

    protected $fillable = ['valoracion_global', 'respuestas', 'comentarios'];

    protected $hidden = ['token'];

    protected $casts = [
        'token_expira_en'   => 'datetime',
        'enviado_en'        => 'datetime',
        'respondido_en'     => 'datetime',
        'valoracion_global' => 'integer',
        'respuestas'        => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (EncuentroValoracionEmpresa $v) {
            $v->token ??= Str::random(48);
            $v->token_expira_en ??= now()->addDays(self::DIAS_VALIDEZ_TOKEN);
        });
    }

    /** @return BelongsTo<Encuentro, $this> */
    public function encuentro(): BelongsTo
    {
        return $this->belongsTo(Encuentro::class);
    }

    public function caducado(): bool
    {
        return $this->token_expira_en->isPast();
    }

    public function respondida(): bool
    {
        return $this->respondido_en !== null;
    }

    // Nuevo token y nueva caducidad (el enlace anterior deja de funcionar)
    public function renovarToken(): void
    {
        $this->token = Str::random(48);
        $this->token_expira_en = now()->addDays(self::DIAS_VALIDEZ_TOKEN);
    }
}
