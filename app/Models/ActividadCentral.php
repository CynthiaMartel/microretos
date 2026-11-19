<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Una línea del historial de DuaLab Central (ActividadCentralService::registrar). Solo se añaden: nunca se editan. */
class ActividadCentral extends Model
{
    protected $table = 'actividad_central';

    public const UPDATED_AT = null;

    // Todo lo pone ActividadCentralService, nunca la petición
    protected $fillable = [];

    protected $casts = [
        'critica'    => 'boolean',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
