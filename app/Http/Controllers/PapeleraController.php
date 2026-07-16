<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Empresa;
use App\Models\Microreto;
use App\Models\CicloFormativo;
use App\Models\Familia;
use App\Models\CentroEducativo;
use App\Models\Microproyecto;
use App\Models\Encuentro;

class PapeleraController extends Controller
{
    // Mapa tipo → modelo y etiqueta legible. Claves ('sesiones', etc.) son el
    // contrato con PapeleraBaseDatos.vue — no renombrar sin actualizar el frontend.
    private array $tipos = [
        'empresas'    => Empresa::class,
        'microretos'  => Microreto::class,
        'ciclos'      => CicloFormativo::class,
        'familias'    => Familia::class,
        'centros'     => CentroEducativo::class,
        'proyectos'   => Microproyecto::class,
        'sesiones'    => Encuentro::class,
    ];

    /**
     * GET /papelera
     * Devuelve todos los elementos en papelera agrupados por tipo.
     * Cada item incluye el campo 'tipo' para que el frontend sepa cómo tratarlo.
     */
    public function index()
    {
        $resultado = [];

        foreach ($this->tipos as $tipo => $clase) {
            $items = $clase::onlyTrashed()
                ->orderByDesc('deleted_at')
                ->get()
                ->map(fn ($item) => $this->serializar($item, $tipo));

            $resultado[$tipo] = $items;
        }

        $totalItems = collect($resultado)->flatten(1)->count();

        return response()->json([
            'total'  => $totalItems,
            'items'  => $resultado,
        ]);
    }

    /**
     * PATCH /papelera/{tipo}/{id}/restaurar
     * Restaura un elemento de la papelera (pone deleted_at = null).
     */
    public function restaurar(string $tipo, int $id)
    {
        $clase = $this->resolverClase($tipo);
        if (!$clase) {
            return response()->json(['error' => 'Tipo no válido. Tipos aceptados: ' . implode(', ', array_keys($this->tipos))], 422);
        }

        $item = $clase::onlyTrashed()->find($id);
        if (!$item) {
            return response()->json(['error' => 'Elemento no encontrado en la papelera'], 404);
        }

        $item->restore();

        return response()->json([
            'message' => 'Elemento restaurado correctamente',
            'item'    => $this->serializar($item, $tipo),
        ]);
    }

    /**
     * DELETE /papelera/{tipo}/{id}
     * Elimina un elemento de la papelera de forma permanente (forceDelete).
     */
    public function destruir(string $tipo, int $id)
    {
        $clase = $this->resolverClase($tipo);
        if (!$clase) {
            return response()->json(['error' => 'Tipo no válido. Tipos aceptados: ' . implode(', ', array_keys($this->tipos))], 422);
        }

        $item = $clase::onlyTrashed()->find($id);
        if (!$item) {
            return response()->json(['error' => 'Elemento no encontrado en la papelera'], 404);
        }

        $this->limpiarRelaciones($item, $tipo);
        $item->forceDelete();

        return response()->json(['message' => 'Elemento eliminado permanentemente']);
    }

    /**
     * DELETE /papelera
     * Vacía toda la papelera: elimina permanentemente todos los elementos soft-deleted.
     * Acepta un query param ?tipo=empresas para vaciar solo un tipo.
     */
    public function vaciar(Request $request)
    {
        $tipoFiltro = $request->query('tipo');

        if ($tipoFiltro && !isset($this->tipos[$tipoFiltro])) {
            return response()->json(['error' => 'Tipo no válido. Tipos aceptados: ' . implode(', ', array_keys($this->tipos))], 422);
        }

        $clasesAVaciar = $tipoFiltro
            ? [$tipoFiltro => $this->tipos[$tipoFiltro]]
            : $this->tipos;

        $totalEliminados = 0;

        foreach ($clasesAVaciar as $tipo => $clase) {
            $items = $clase::onlyTrashed()->get();
            foreach ($items as $item) {
                $this->limpiarRelaciones($item, $tipo);
                $item->forceDelete();
                $totalEliminados++;
            }
        }

        return response()->json([
            'message'    => 'Papelera vaciada correctamente',
            'eliminados' => $totalEliminados,
        ]);
    }

    // ─── helpers privados ─────────────────────────────────────────────────────

    private function resolverClase(string $tipo): ?string
    {
        return $this->tipos[$tipo] ?? null;
    }

    /**
     * Serializa un modelo a un array uniforme para la respuesta de papelera.
     * Siempre incluye id, tipo, deleted_at y un campo 'nombre' para el display.
     */
    private function serializar($item, string $tipo): array
    {
        $base = [
            'id'         => $item->id,
            'tipo'       => $tipo,
            'deleted_at' => $item->deleted_at,
            'nombre'     => $this->extraerNombre($item, $tipo),
            'datos'      => $item->toArray(),
        ];

        return $base;
    }

    private function extraerNombre($item, string $tipo): string
    {
        return match ($tipo) {
            'empresas'   => $item->nombre_comercial ?? "Empresa #{$item->id}",
            'microretos' => $item->titulo           ?? "Microreto #{$item->id}",
            'ciclos'     => $item->nombre            ?? "Ciclo #{$item->id}",
            'familias'   => $item->nombre            ?? "Familia #{$item->id}",
            'centros'    => $item->nombre            ?? "Centro #{$item->id}",
            'proyectos'  => $item->titulo            ?? "Proyecto #{$item->id}",
            'sesiones'   => $item->microreto_titulo  ?? "Sesión #{$item->id}",
            default      => "#{$item->id}",
        };
    }

    /**
     * Limpia relaciones huérfanas antes del forceDelete para mantener integridad referencial.
     * Solo se ejecuta en borrado permanente, nunca en soft delete.
     */
    private function limpiarRelaciones($item, string $tipo): void
    {
        match ($tipo) {
            'empresas'  => $this->limpiarEmpresa($item),
            'centros'   => $this->limpiarCentro($item),
            'proyectos' => $this->limpiarProyecto($item),
            // 'sesiones' (Encuentro) no necesita limpieza manual: equipos.encuentro_id
            // ya tiene ON DELETE SET NULL, y microproyectos ya no apunta a encuentros
            // (ver 2026_07_16_000003_drop_sesion_id_from_microproyectos).
            default     => null,
        };
    }

    private function limpiarEmpresa(Empresa $empresa): void
    {
        \Illuminate\Support\Facades\DB::table('empresa_familia')
            ->where('empresa_id', $empresa->id)
            ->delete();

        \Illuminate\Support\Facades\DB::table('microretos')
            ->where('empresa_id', $empresa->id)
            ->update(['empresa_id' => null]);
    }

    private function limpiarCentro(CentroEducativo $centro): void
    {
        Empresa::where('centro_id', $centro->id)->update([
            'centro_id'        => null,
            'centro_educativo' => null,
        ]);

        \Illuminate\Support\Facades\DB::table('centro_ciclo')
            ->where('centro_id', $centro->id)
            ->delete();
    }

    private function limpiarProyecto(Microproyecto $proyecto): void
    {
        // Elimina los recursos asociados al proyecto antes del borrado permanente
        $proyecto->recursos()->forceDelete();
    }

}
