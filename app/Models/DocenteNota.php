<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocenteNota extends Model
{
    protected $table = 'docente_notas';

    // user_id fuera de $fillable: lo asigna el controller con el usuario autenticado
    protected $fillable = ['texto', 'fecha'];

    protected $casts = [
        'fecha' => 'date:Y-m-d',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
