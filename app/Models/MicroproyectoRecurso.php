<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MicroproyectoRecurso extends Model
{
    protected $table = 'microproyecto_recursos';

    protected $fillable = [
        'microproyecto_id', 'tipo', 'label', 'filename',
        'url', 'public_id', 'resource_type', 'mime', 'size',
    ];

    /** @return BelongsTo<Microproyecto, $this> */
    public function microproyecto(): BelongsTo
    {
        return $this->belongsTo(Microproyecto::class);
    }
}
