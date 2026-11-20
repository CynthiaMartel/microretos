<?php

namespace App\Models;

use App\Support\AliasGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipoMiembro extends Model
{
    protected $table = 'equipo_miembros';

    // Protección de datos: el alumnado solo existe por su alias ("NombreDePila Animal Color", ver
    // AliasGenerator), único dentro de su encuentro. No hay columna con el nombre completo.
    protected $fillable = ['equipo_id', 'alias', 'rol', 'fortalezas', 'puntos_mejora'];

    // destacado_en / destacado_por_id (selección para prácticas, P3b) no son fillable: solo los
    // cambia el superadmin desde DuaLab Central (DestacadoCentralController)
    protected $casts = [
        // Lleva el nombre de pila real: cifrado en BD (no se filtra ni ordena por él en SQL)
        'alias'         => 'encrypted',
        'fortalezas'    => 'array',
        'puntos_mejora' => 'array',
        'destacado_en'  => 'datetime',
    ];

    public function esDestacado(): bool
    {
        return $this->destacado_en !== null;
    }

    /** Horas de prácticas anotadas (A3) */
    public function registrosHoras(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RegistroHoras::class, 'equipo_miembro_id')->orderByDesc('fecha');
    }

    protected static function booted(): void
    {
        static::creating(function (EquipoMiembro $miembro) {
            if (empty($miembro->alias)) {
                // Red de seguridad: los controladores siempre traen alias; si faltara, uno con
                // el nombre de pila por defecto
                $miembro->alias = AliasGenerator::generar(null, static::aliasDelEncuentro($miembro->equipo_id));
            }
        });
    }

    /**
     * Alias ya usados en el encuentro del equipo (en todos sus equipos), para no repetirlos
     * dentro de la misma clase. $excluirId deja fuera al propio miembro al cambiar su alias.
     *
     * @return array<int, string>
     */
    public static function aliasDelEncuentro(?int $equipoId, ?int $excluirId = null): array
    {
        if (!$equipoId) {
            return [];
        }

        $encuentroId = Equipo::whereKey($equipoId)->value('encuentro_id');
        $query = $encuentroId
            ? static::whereHas('equipo', fn ($q) => $q->where('encuentro_id', $encuentroId))
            : static::where('equipo_id', $equipoId);

        return $query->when($excluirId, fn ($q) => $q->whereKeyNot($excluirId))->pluck('alias')->all();
    }

    /** Otro miembro del mismo encuentro ya usa este alias (sin distinguir mayúsculas). */
    public function aliasRepetidoEnEncuentro(string $alias): bool
    {
        $buscado = AliasGenerator::normalizar($alias);

        return collect(static::aliasDelEncuentro($this->equipo_id, $this->id))
            ->contains(fn ($a) => AliasGenerator::normalizar($a) === $buscado);
    }

    /** @return BelongsTo<Equipo, $this> */
    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class);
    }
}
