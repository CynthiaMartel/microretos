<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Solicitud de retos de un docente: DuaLab le envía un enlace, el docente cuenta qué quiere
 * trabajar y DuaLab genera los retos con esas respuestas. Ver SolicitudRetoService.
 *   pendiente → respondida | descartada
 * Los retos (microretos.solicitud_reto_id), el aviso (avisado_at) y el encuentro
 * (encuentros.solicitud_reto_id) se deducen de sus relaciones, no del estado.
 * Borrado seguro: va a la papelera (SoftDeletes); mientras está allí su enlace no vale y sus
 * retos siguen en la recámara.
 */
class SolicitudReto extends Model
{
    use SoftDeletes;

    protected $table = 'solicitudes_reto';

    public const ESTADO_PENDIENTE  = 'pendiente';
    public const ESTADO_RESPONDIDA = 'respondida';
    public const ESTADO_DESCARTADA = 'descartada';

    // Lo que escribe el docente o DuaLab al crearla. Token, estado, centro y empresa asignados,
    // aviso y autor no son fillable: solo los fija SolicitudRetoService.
    protected $fillable = [
        'nombre_docente', 'email_docente', 'centro_nombre',
        'respuestas', 'sesion_fecha', 'sesion_hora_inicio', 'sesion_hora_fin', 'notas',
    ];

    // Solo su huella; aun así nunca sale en JSON
    protected $hidden = ['token_hash'];

    protected $casts = [
        'respuestas'     => 'array',
        'retos_avisados' => 'array', // uuid de los retos enviados al docente
        'empresas_peticiones' => 'array', // { índice de la petición: empresa_id } que elige DuaLab
        'sesion_fecha'   => 'date:Y-m-d',
        'expira_at'      => 'datetime',
        'enviado_at'     => 'datetime',
        'respondido_at'  => 'datetime',
        'avisado_at'     => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (SolicitudReto $solicitud) {
            $solicitud->uuid ??= (string) Str::uuid();
        });
    }

    /**
     * Referencia que va en el asunto de los correos al docente («Ref. SR-12»). Las respuestas la
     * conservan («Re: …»), y así Central las separa del resto del buzón de info@ y las asocia.
     */
    public const PREFIJO_REFERENCIA = 'Ref. SR-';

    public function referencia(): string
    {
        return self::PREFIJO_REFERENCIA . $this->id;
    }

    /** Id de la solicitud a partir del asunto de un correo, o null si no lleva referencia */
    public static function idDeAsunto(?string $asunto): ?int
    {
        return $asunto && preg_match('/Ref\. SR-(\d+)\b/', $asunto, $m) ? (int) $m[1] : null;
    }

    public function enlaceVigente(): bool
    {
        return $this->estado !== self::ESTADO_DESCARTADA
            && $this->token_hash !== null
            && (bool) $this->expira_at?->isFuture();
    }

    /** El docente aún puede rellenar el formulario (enlace vigente y sin enviar) */
    public function formularioAbierto(): bool
    {
        return $this->enlaceVigente() && $this->estado === self::ESTADO_PENDIENTE;
    }

    /** @return HasMany<Microreto, $this> */
    public function microretos(): HasMany
    {
        return $this->hasMany(Microreto::class);
    }

    /**
     * Al borrarla del todo (Papelera o purga automática): sus retos de la recámara, también los que ya
     * estén en la papelera, se borran con ella. Sin solicitud nadie los vería ni los podría publicar.
     * Los publicados en un centro se quedan (FK nullOnDelete).
     */
    public function borrarRetosRecamara(): int
    {
        $retos = $this->microretos()->withTrashed()->where('en_recamara', true)->get();
        $retos->each->forceDelete();

        return $retos->count();
    }

    /** @return HasMany<Encuentro, $this> */
    public function encuentros(): HasMany
    {
        return $this->hasMany(Encuentro::class);
    }

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /** @return BelongsTo<CentroEducativo, $this> */
    public function centro(): BelongsTo
    {
        return $this->belongsTo(CentroEducativo::class, 'centro_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creada_por_user_id');
    }
}
