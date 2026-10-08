<?php

namespace App\Services;

use App\Models\Familia;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Único punto de escritura del pivot empresa_familia (sin modelo Eloquent propio).
 *
 * Una empresa es una sola fila en `empresas` aunque trabaje con varias familias: el pivot
 * guarda una fila por familia, nunca dos veces la misma (índice único empresa_id +
 * familia_id). Cada fila lleva la FK normalizada y el nombre legacy, que aún leen
 * algunos controladores.
 */
class EmpresaFamiliaService
{
    /**
     * Deja la empresa vinculada exactamente a estas familias: añade las que falten y
     * quita las demás (incluidas filas legacy sin familia_id).
     *
     * @param  string[]  $nombres
     */
    public function sincronizarPorNombre(int $empresaId, array $nombres): void
    {
        $familias = $this->familiasPorNombre($nombres);

        DB::table('empresa_familia')
            ->where('empresa_id', $empresaId)
            ->where(fn ($q) => $q->whereNull('familia_id')->orWhereNotIn('familia_id', $familias->keys()))
            ->delete();

        $this->insertarFaltantes($empresaId, $familias);
    }

    /**
     * Añade estas familias sin quitar las que ya tenga la empresa.
     *
     * @param  string[]  $nombres
     */
    public function anadirPorNombre(int $empresaId, array $nombres): void
    {
        $this->insertarFaltantes($empresaId, $this->familiasPorNombre($nombres));
    }

    public function anadir(int $empresaId, Familia $familia): void
    {
        $this->insertarFaltantes($empresaId, collect([$familia->id => $familia->nombre]));
    }

    /** Copia las familias de una empresa a otra (plantillas del catálogo DuaLab y sus copias). */
    public function copiar(int $origenId, int $destinoId): void
    {
        $filas = DB::table('empresa_familia')->where('empresa_id', $origenId)->get(['familia_id', 'familia']);

        // Con FK: sin repetir. Filas legacy sin FK: se copian tal cual (el índice único las admite).
        $this->insertarFaltantes($destinoId, $filas->whereNotNull('familia_id')->pluck('familia', 'familia_id'));

        $legacy = $filas->whereNull('familia_id')->pluck('familia')->filter()->unique()
            ->map(fn (string $nombre) => $this->fila($destinoId, null, $nombre))->values()->all();
        if ($legacy) {
            DB::table('empresa_familia')->insert($legacy);
        }
    }

    /** @return Collection<int, string>  id => nombre (sin familias borradas) */
    private function familiasPorNombre(array $nombres): Collection
    {
        return Familia::whereIn('nombre', array_unique($nombres))->pluck('nombre', 'id');
    }

    /** @param  Collection<int, string>  $familias  id => nombre */
    private function insertarFaltantes(int $empresaId, Collection $familias): void
    {
        if ($familias->isEmpty()) {
            return;
        }

        $yaVinculadas = DB::table('empresa_familia')
            ->where('empresa_id', $empresaId)
            ->whereIn('familia_id', $familias->keys())
            ->pluck('familia_id')
            ->all();

        $filas = $familias->except($yaVinculadas)
            ->map(fn (?string $nombre, int $id) => $this->fila($empresaId, $id, (string) $nombre))
            ->values()->all();

        // insertOrIgnore: dos peticiones simultáneas no fallan contra el índice único
        if ($filas) {
            DB::table('empresa_familia')->insertOrIgnore($filas);
        }
    }

    private function fila(int $empresaId, ?int $familiaId, string $nombre): array
    {
        return [
            'empresa_id' => $empresaId,
            'familia_id' => $familiaId,  // normalizado
            'familia'    => $nombre,     // legacy
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
