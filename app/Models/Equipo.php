<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Models\Encuentro;
use App\Support\CodigoLegible;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Equipo extends Model
{
    protected $fillable = [
        'microproyecto_id', 'encuentro_id', 'nombre', 'numero_equipo', 'token', 'codigo_acceso', 'fase_actual',
        'ia_desbloqueada', 'diagnostico_final', 'diagnostico_generado_en', 'nombres_confirmados',
    ];

    protected $casts = [
        'fase_actual'             => 'integer',
        'numero_equipo'           => 'integer',
        'ia_desbloqueada'         => 'boolean',
        'diagnostico_final'       => 'array',
        'diagnostico_generado_en' => 'datetime',
        'nombres_confirmados'     => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Equipo $equipo) {
            if (empty($equipo->token)) {
                $equipo->token = Str::random(40);
            }
            if (empty($equipo->codigo_acceso)) {
                $equipo->codigo_acceso = static::generarCodigo();
            }
        });
    }

    private static function generarCodigo(): string
    {
        return CodigoLegible::generar(fn($codigo) => static::where('codigo_acceso', $codigo)->exists());
    }

    // ── Relaciones ────────────────────────────────────────────────────────────

    /** @return BelongsTo<Microproyecto, $this> */
    public function microproyecto(): BelongsTo
    {
        return $this->belongsTo(Microproyecto::class);
    }

    /** @return BelongsTo<Encuentro, $this> */
    public function encuentro(): BelongsTo
    {
        return $this->belongsTo(Encuentro::class);
    }

    /** @return HasMany<EquipoMiembro, $this> */
    public function miembros(): HasMany
    {
        return $this->hasMany(EquipoMiembro::class)->orderBy('id');
    }

    /** @return HasMany<EquipoFase, $this> */
    public function fases(): HasMany
    {
        return $this->hasMany(EquipoFase::class)->orderBy('numero_fase');
    }

    /** @return HasMany<EquipoTarea, $this> */
    public function tareas(): HasMany
    {
        return $this->hasMany(EquipoTarea::class)->orderBy('orden')->orderBy('id');
    }

    /** @return HasMany<EquipoReflexion, $this> */
    public function reflexiones(): HasMany
    {
        return $this->hasMany(EquipoReflexion::class)->orderBy('created_at');
    }

    /** @return HasMany<EquipoPrototipo, $this> */
    public function prototipos(): HasMany
    {
        return $this->hasMany(EquipoPrototipo::class)->orderBy('created_at');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function getFase(int $numero): ?EquipoFase
    {
        return $this->fases->firstWhere('numero_fase', $numero);
    }
}
