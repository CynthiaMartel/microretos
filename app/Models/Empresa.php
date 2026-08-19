<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Empresa extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'cif',
        'nombre_comercial',
        'razon_social',
        'telefono',
        'email_general',
        'estado_contacto',
        'fecha_cita',
        'persona_contacto',
        'email_contacto',
        'posicion_contacto',
        'sector',
        'actividad',
        'horario_atencion',
        'direccion',
        'numero',
        'otros_direccion',
        'codigo_postal',
        'municipio',
        'provincia',
        'web',
        'proyecto_asociado',
        'centro_educativo',  // legacy — se mantiene hasta completar backfill
        'centro_id',         // FK (nueva)
        'tamano',
        'dia_a_normal',
        'friccion_area',
        'friccion_problema',
        'consecuencias',
        'restricciones',
        'lo_que_no_quieren',
        'es_simulada',
        'expectativas_alumno',
    ];

    protected $casts = [
        'es_simulada' => 'boolean',
    ];

    public function centroEducativo()
    {
        return $this->belongsTo(CentroEducativo::class, 'centro_id');
    }

    public function familias()
    {
        return $this->belongsToMany(Familia::class, 'empresa_familia', 'empresa_id', 'familia_id');
    }

    public function microretos()
    {
        return $this->hasMany(Microreto::class, 'empresa_id');
    }

    // Empresas del centro del usuario: docente y admin ven solo las de su centro
    // (por centro_id normalizado, o por el nombre legacy si el backfill no llegó);
    // superadmin no debería llamar a este scope (ve todas sin filtro).
    public function scopeDelCentroDe($query, \App\Models\User $user)
    {
        if (!$user->centro_educativo_id) {
            return $query->whereRaw('0 = 1');
        }

        $centroNombre = $user->centroEducativo?->nombre;

        return $query->where(function ($q) use ($user, $centroNombre) {
            $q->where('centro_id', $user->centro_educativo_id);
            if ($centroNombre) {
                $q->orWhere('centro_educativo', $centroNombre);
            }
        });
    }

    // Comprueba si esta empresa concreta pertenece al centro del usuario
    // (mismo criterio que scopeDelCentroDe, para checks puntuales por id).
    public function perteneceAlCentroDe(\App\Models\User $user): bool
    {
        if (!$user->centro_educativo_id) {
            return false;
        }

        if ($this->centro_id === $user->centro_educativo_id) {
            return true;
        }

        $centroNombre = $user->centroEducativo?->nombre;
        return $centroNombre !== null && $this->centro_educativo === $centroNombre;
    }
}
