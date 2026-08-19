<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Models\Encuentro;
use App\Support\CodigoLegible;

class Equipo extends Model
{
    protected $fillable = [
        'microproyecto_id', 'encuentro_id', 'nombre', 'numero_equipo', 'token', 'codigo_acceso', 'fase_actual',
        'ia_desbloqueada', 'diagnostico_final', 'diagnostico_generado_en',
    ];

    protected $casts = [
        'fase_actual'             => 'integer',
        'numero_equipo'           => 'integer',
        'ia_desbloqueada'         => 'boolean',
        'diagnostico_final'       => 'array',
        'diagnostico_generado_en' => 'datetime',
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

    public function microproyecto()
    {
        return $this->belongsTo(Microproyecto::class);
    }

    public function encuentro()
    {
        return $this->belongsTo(Encuentro::class);
    }

    public function miembros()
    {
        return $this->hasMany(EquipoMiembro::class)->orderBy('id');
    }

    public function fases()
    {
        return $this->hasMany(EquipoFase::class)->orderBy('numero_fase');
    }

    public function tareas()
    {
        return $this->hasMany(EquipoTarea::class)->orderBy('orden')->orderBy('id');
    }

    public function reflexiones()
    {
        return $this->hasMany(EquipoReflexion::class)->orderBy('created_at');
    }

    public function prototipos()
    {
        return $this->hasMany(EquipoPrototipo::class)->orderBy('created_at');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function getFase(int $numero): ?EquipoFase
    {
        return $this->fases->firstWhere('numero_fase', $numero);
    }
}
