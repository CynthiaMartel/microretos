<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Vía por la que llega una captación (tabla captacion_origenes, ids fijos de la migración).
 */
class CaptacionOrigen extends Model
{
    public const ENTREVISTA = 1; // app comercial (comercial.dualab.es)
    public const FORMULARIO = 2; // formularios del frontoffice (webhook)

    protected $table = 'captacion_origenes';
    public $timestamps = false;
    protected $fillable = [];
}
