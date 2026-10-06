<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Crea plantillas del catálogo DuaLab (T2) a partir de las empresas ficticias de un centro
 * (por defecto, el centro DuaLab). Las originales NO se mueven: siguen en su centro con sus
 * retos (los retos heredan el centro de su empresa; moverlas los dejaría sin centro). Cada
 * original queda enlazada como «copia» de su plantilla (copiada_de_id), así que «Usar en mi
 * centro» desde ese centro la reutiliza en vez de duplicarla, y relanzar el comando no repite.
 *
 * Solo se copian ficticias con diagnóstico completo (P1, P2, P2b y P5) que no sean ya copia
 * de otra plantilla. Sin IA: usa los datos que ya tienen.
 *
 * Por defecto es un dry-run: no persiste nada hasta pasar --commit.
 */
class CopiarEmpresasAlCatalogo extends Command
{
    protected $signature = 'empresas:copiar-al-catalogo
                            {--centro=10 : Id del centro cuyas empresas ficticias se copian al catálogo (10 = DuaLab).}
                            {--commit : Guarda los cambios en BD. Sin esta opción es un dry-run.}';

    protected $description = 'Crea plantillas del catálogo DuaLab a partir de las empresas ficticias de un centro, sin moverlas (sus retos no cambian).';

    public function handle(): int
    {
        $commit   = (bool) $this->option('commit');
        $centroId = (int) $this->option('centro');

        $candidatas = Empresa::where('centro_id', $centroId)
            ->where('es_simulada', true)
            ->where('es_catalogo', false)
            ->whereNull('copiada_de_id')
            ->with('familias:id,nombre')
            ->orderBy('id')
            ->get();

        $completas = $candidatas->filter(fn (Empresa $e) => filled($e->dia_a_normal) && filled($e->friccion_area)
            && filled($e->friccion_problema) && filled($e->expectativas_alumno));
        $omitidas = $candidatas->count() - $completas->count();

        $this->info(($commit ? '' : '[dry-run] ') . "Centro {$centroId}: {$completas->count()} ficticias se copiarán al catálogo"
            . ($omitidas ? " · {$omitidas} sin diagnóstico completo (se omiten)" : ''));

        $creadas = 0;
        foreach ($completas as $original) {
            $familias = $original->familias->pluck('nombre')->implode(', ') ?: 'sin familia';
            if (!$commit) {
                $this->line("  · #{$original->id} {$original->nombre_comercial} ({$familias})");
                continue;
            }

            $plantilla = DB::transaction(function () use ($original) {
                // Copia de todos los datos (ficha + diagnóstico), sin centro ni estado de contacto.
                $plantilla = $original->replicate(['es_catalogo', 'copiada_de_id', 'estado_contacto', 'fecha_cita', 'centro_id', 'centro_educativo']);
                $plantilla->forceFill([
                    'nombre_comercial' => Empresa::nombreLibreEnCentro(null, (string) $original->nombre_comercial),
                    'centro_id'        => null,
                    'centro_educativo' => null,
                    'es_simulada'      => true,
                    'es_catalogo'      => true,
                ])->save();

                $filas = DB::table('empresa_familia')->where('empresa_id', $original->id)->get(['familia', 'familia_id']);
                DB::table('empresa_familia')->insert($filas->map(fn ($f) => [
                    'empresa_id' => $plantilla->id,
                    'familia'    => $f->familia,
                    'familia_id' => $f->familia_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all());

                // La original pasa a contar como la copia de su centro (ver cabecera).
                $original->forceFill(['copiada_de_id' => $plantilla->id])->save();
                return $plantilla;
            });

            $creadas++;
            $this->line("  ✓ #{$original->id} {$original->nombre_comercial} → plantilla #{$plantilla->id} ({$familias})");
        }

        $this->info(($commit ? 'Plantillas creadas' : 'Se crearían') . ': ' . ($commit ? $creadas : $completas->count()));
        if (!$commit) $this->comment('Nada guardado. Repite con --commit para aplicarlo.');

        return self::SUCCESS;
    }
}
