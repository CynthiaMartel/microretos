<?php

namespace App\Services;

use App\Models\CentroEducativo;
use App\Models\Empresa;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo de empresas ficticias de DuaLab (T2). Las plantillas no tienen centro y son de
 * solo lectura; un centro las usa a través de su propia copia (ficticia, editable). Los
 * retos se generan siempre con la copia, porque heredan el centro de su empresa.
 */
class EmpresaCatalogoService
{
    /**
     * Copia de la plantilla para el centro. Si el centro ya tenía una, se reutiliza en vez
     * de crear otra (devuelve [empresa, true si es nueva]).
     *
     * @return array{0: Empresa, 1: bool}
     */
    public function usarEnCentro(Empresa $plantilla, CentroEducativo $centro): array
    {
        $existente = Empresa::where('copiada_de_id', $plantilla->id)->where('centro_id', $centro->id)->first();
        if ($existente) {
            return [$existente->load('familias:id,nombre'), false];
        }

        $copia = DB::transaction(function () use ($plantilla, $centro) {
            // replicate() copia todos los atributos salvo id/timestamps; se rehacen los de centro.
            $copia = $plantilla->replicate(['es_catalogo', 'copiada_de_id', 'estado_contacto', 'fecha_cita']);
            $copia->forceFill([
                'nombre_comercial' => Empresa::nombreLibreEnCentro($centro->id, (string) $plantilla->nombre_comercial),
                'centro_id'        => $centro->id,
                'centro_educativo' => $centro->nombre, // legacy
                'es_simulada'      => true,
                'es_catalogo'      => false,
                'copiada_de_id'    => $plantilla->id,
            ])->save();

            // Mismas familias que la plantilla (FK + string legacy del pivot).
            $filas = DB::table('empresa_familia')->where('empresa_id', $plantilla->id)->get(['familia', 'familia_id']);
            DB::table('empresa_familia')->insert($filas->map(fn ($f) => [
                'empresa_id' => $copia->id,
                'familia'    => $f->familia,
                'familia_id' => $f->familia_id,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all());

            return $copia;
        });

        return [$copia->load('familias:id,nombre'), true];
    }
}
