<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocenteTarea extends Model
{
    protected $table = 'docente_tareas';

    // user_id fuera de $fillable: lo asigna el controller con el usuario autenticado
    protected $fillable = ['texto', 'hecha'];

    protected $casts = [
        'hecha' => 'boolean',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
