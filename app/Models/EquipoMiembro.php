<?php

namespace App\Models;

use App\Support\AliasGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipoMiembro extends Model
{
    protected $table = 'equipo_miembros';

    // Protección de datos: el alumnado solo existe por su alias ("NombreDePila Animal Color", ver
    // AliasGenerator), único dentro de su encuentro. `nombre` es el nombre real completo de los
    // miembros antiguos: ya no se escribe (fuera de $fillable), nunca se serializa ($hidden)
    // y se vacía con `alumnado:anonimizar` antes de eliminar la columna.
    protected $fillable = ['equipo_id', 'alias', 'rol', 'fortalezas', 'puntos_mejora'];

    protected $hidden = ['nombre'];

    protected $casts = [
        'nombre'        => 'encrypted',
        'fortalezas'    => 'array',
        'puntos_mejora' => 'array',
    ];

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
