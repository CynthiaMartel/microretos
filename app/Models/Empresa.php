<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'es_catalogo' => 'boolean', // catálogo DuaLab (T2): no fillable, solo lo fija el backend
    ];

    /** @return BelongsTo<CentroEducativo, $this> */
    public function centroEducativo(): BelongsTo
    {
        return $this->belongsTo(CentroEducativo::class, 'centro_id');
    }

    /** @return BelongsToMany<Familia, $this> */
    public function familias(): BelongsToMany
    {
        return $this->belongsToMany(Familia::class, 'empresa_familia', 'empresa_id', 'familia_id');
    }

    /** @return HasMany<Microreto, $this> */
    public function microretos(): HasMany
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

    /** @return BelongsTo<Empresa, $this> Plantilla del catálogo de la que es copia (T2) */
    public function copiadaDe(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'copiada_de_id');
    }

    // Nombre no repetido entre las empresas del centro: añade " (2)", " (3)"...
    public static function nombreLibreEnCentro(?int $centroId, string $nombre): string
    {
        $base = mb_substr($nombre, 0, 240);
        $candidato = $base;
        for ($n = 2; static::where('centro_id', $centroId)->where('nombre_comercial', $candidato)->exists(); $n++) {
            $candidato = "{$base} ({$n})";
        }
        return $candidato;
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
