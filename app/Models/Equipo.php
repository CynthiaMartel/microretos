<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Models\Sesion;

class Equipo extends Model
{
    protected $fillable = [
        'microproyecto_id', 'sesion_id', 'nombre', 'token', 'codigo_acceso', 'fase_actual',
    ];

    protected $casts = [
        'fase_actual' => 'integer',
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

    // Genera un código legible tipo "XKM-479" — fácil de proyectar y escribir en móvil
    private static function generarCodigo(): string
    {
        $charset = 'ABCDEFGHJKLMNPQRTUVWXY'; // sin I, O, S, Z (confundibles)
        $intentos = 0;
        do {
            $letras  = $charset[random_int(0, strlen($charset) - 1)]
                     . $charset[random_int(0, strlen($charset) - 1)]
                     . $charset[random_int(0, strlen($charset) - 1)];
            $numeros = str_pad((string) random_int(100, 999), 3, '0', STR_PAD_LEFT);
            $codigo  = $letras . '-' . $numeros;
            if (++$intentos > 200) {
                throw new \RuntimeException('Espacio de códigos agotado.');
            }
        } while (static::where('codigo_acceso', $codigo)->exists());

        return $codigo;
    }

    // ── Relaciones ────────────────────────────────────────────────────────────

    public function microproyecto()
    {
        return $this->belongsTo(Microproyecto::class);
    }

    public function sesion()
    {
        return $this->belongsTo(Sesion::class);
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
