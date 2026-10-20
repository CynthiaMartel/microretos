<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipoFase extends Model
{
    protected $table = 'equipo_fases';

    protected $fillable = [
        'equipo_id', 'numero_fase', 'datos',
        'completada', 'fecha_completada',
        'validado_docente', 'fecha_validacion_docente',
        'nota_docente', 'observaciones_docente',
    ];

    protected $casts = [
        'datos'                    => 'array',
        'completada'               => 'boolean',
        'validado_docente'         => 'boolean',
        'fecha_completada'         => 'datetime',
        'fecha_validacion_docente' => 'datetime',
        'nota_docente'             => 'decimal:2',
    ];

    /**
     * Datos de la fase sin nombres reales del alumnado: los F0 antiguos guardaban
     * datos.miembros[].nombre en claro. Hoy GuardarFaseEquipoRequest ya no lo deja pasar,
     * pero hasta que `alumnado:anonimizar` limpie los registros antiguos se quita al leer.
     */
    public function datosSinNombres(): ?array
    {
        $datos = $this->datos;
        if (!is_array($datos) || !isset($datos['miembros']) || !is_array($datos['miembros'])) {
            return $datos;
        }

        $datos['miembros'] = array_map(function ($m) {
            if (is_array($m)) {
                unset($m['nombre']);
            }
            return $m;
        }, $datos['miembros']);

        return $datos;
    }

    // Defensa en profundidad: cualquier serialización del modelo sale sin nombres reales.
    public function toArray(): array
    {
        $array = parent::toArray();
        if (array_key_exists('datos', $array)) {
            $array['datos'] = $this->datosSinNombres();
        }

        return $array;
    }

    /** @return BelongsTo<Equipo, $this> */
    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class);
    }
}
