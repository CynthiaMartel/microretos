<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Lo que aceptó un centro captado (protección de datos, cesiones), con la versión y la huella
 * del texto mostrado. Solo se inserta.
 */
class CentroCaptacionConsentimiento extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [];

    protected $casts = ['aceptado' => 'boolean'];
}
