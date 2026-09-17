<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\InvocaControladoresReales;
use App\Http\Controllers\MicroretoIAController;
use App\Http\Requests\GenerarEmpresaFicticiaRequest;
use App\Http\Requests\SimularInfoEmpresaRequest;
use App\Models\CentroEducativo;
use App\Models\Empresa;
use App\Models\Familia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Genera empresas ficticias de demo reutilizando MicroretoIAController::generarEmpresaFicticia()
 * (datos de la empresa) y ::simularInfoEmpresa() (diagnóstico: día a día, fricciones,
 * restricciones, expectativas) — los mismos prompts que usa el Generador de Retos real.
 *
 * Todas las empresas se asocian al centro DuaLab (id=10) — es el único centro al que se
 * asocian empresas ficticias; NUNCA a un centro educativo real como IES Ana Luisa de
 * Benítez (id=1), cuyos datos son de producción y no deben mezclarse con datos de demo.
 *
 * Reanudable: por defecto completa hasta --total empresas simuladas EN TOTAL (no por
 * ejecución) — si ya existen N, solo genera las que faltan. --limit acota cuántas se
 * generan en ESTA ejecución concreta (por si se quiere ir poco a poco).
 *
 * Dry-run por defecto: sin --commit no se llama a la IA ni se escribe nada en BD, solo
 * se imprime el plan (familia, tamaño objetivo) de cada empresa pendiente.
 */
class DemoGenerarEmpresas extends Command
{
    use InvocaControladoresReales;

    protected $signature = 'demo:generar-empresas
                            {--total=10 : Nº total de empresas simuladas que debe haber al terminar (acumulado, no por ejecución).}
                            {--limit=0 : Tope de empresas a generar en ESTA ejecución (0 = hasta completar --total).}
                            {--commit : Llama a la IA real y persiste en BD. Sin esta opción es un dry-run sin coste (ni IA ni escritura).}';

    protected $description = 'Genera empresas ficticias de demo (con diagnóstico) repartidas entre las 3 familias objetivo, todas asociadas al centro DuaLab.';

    // Único centro al que se asocian las empresas ficticias — ver cabecera de la clase.
    private const CENTRO_ID = 10;

    // Familias objetivo, en el orden de reparto round-robin. Se referencian siempre por
    // esta constante (nunca como literal suelto): el id de familia "Informática y
    // Comunicaciones" (1) coincide numéricamente con el id del centro IES (1) pero son
    // tablas distintas — confundirlos sería el típico bug silencioso.
    private const FAMILIAS_OBJETIVO = [3, 5, 1]; // Administración y Gestión, Comercio y Marketing, Informática y Comunicaciones

    private const TAMANOS_PEQUENOS = ['Micropyme (1-10)', 'Pequeña (10-50)'];

    // De cada 10 empresas, estas posiciones (índice global % 10) se dejan al criterio
    // libre de la IA; el resto (7 de 10) se fuerza a Micropyme/Pequeña — así se cumple
    // "la mayoría deben ser Micropyme o Pequeña" sin perder toda variedad de tamaños.
    // generarEmpresaFicticia() no acepta un hint de tamaño en el prompt (no se toca el
    // controller), así que el forzado se hace aquí, después de recibir la respuesta.
    private const POSICIONES_TAMANO_LIBRE = [2, 5, 9];

    public function handle(): int
    {
        $commit = (bool) $this->option('commit');
        $total  = max(0, (int) $this->option('total'));
        $limite = (int) $this->option('limit');

        if (!$commit) {
            $this->warn('Modo DRY-RUN — no se llama a la IA ni se escribe nada. Relanza con --commit para generar de verdad.');
        }

        $centro = CentroEducativo::find(self::CENTRO_ID);
        if (!$centro) {
            $this->error('No existe el centro educativo id=' . self::CENTRO_ID . ' (DuaLab). Abortando.');
            return self::FAILURE;
        }

        $familias = Familia::whereIn('id', self::FAMILIAS_OBJETIVO)->get()->keyBy('id');
        foreach (self::FAMILIAS_OBJETIVO as $familiaId) {
            if (!$familias->has($familiaId)) {
                $this->error("No existe la familia id={$familiaId}. Revisa FAMILIAS_OBJETIVO. Abortando.");
                return self::FAILURE;
            }
        }

        // Scoped a centro_id=10: 'es_simulada' es un flag genérico ya usado por otras
        // empresas de prueba ajenas a este comando (creadas manualmente, de otros centros)
        // — contar solo por el flag sobrecontaría el objetivo y command5 podría llegar a
        // borrar datos que no son suyos (ver DemoBorrarFicticios).
        $yaExistentes = Empresa::where('es_simulada', true)->where('centro_id', self::CENTRO_ID)->count();
        $pendientes   = max(0, $total - $yaExistentes);
        $aGenerar     = $limite > 0 ? min($limite, $pendientes) : $pendientes;

        if ($aGenerar === 0) {
            $this->info("Ya hay {$yaExistentes} empresas simuladas de las {$total} objetivo. Nada que hacer (sube --total si quieres más).");
            return self::SUCCESS;
        }

        $this->info("Empresas simuladas existentes: {$yaExistentes}. Objetivo total: {$total}. Generando {$aGenerar} en esta ejecución.");
        $this->newLine();

        $creadas = 0;
        for ($i = 0; $i < $aGenerar; $i++) {
            $indiceGlobal = $yaExistentes + $i;
            $familiaId    = self::FAMILIAS_OBJETIVO[$indiceGlobal % count(self::FAMILIAS_OBJETIVO)];
            $familia      = $familias[$familiaId];
            $forzarTamanoPequeno = !in_array($indiceGlobal % 10, self::POSICIONES_TAMANO_LIBRE, true);

            $this->line(sprintf(
                '[%d] Empresa a generar → familia: %s | centro: %s (id %d) | tamaño: %s',
                $indiceGlobal,
                $familia->nombre,
                $centro->nombre,
                self::CENTRO_ID,
                $forzarTamanoPequeno ? 'forzado a Micropyme/Pequeña' : 'libre criterio de la IA'
            ));

            if (!$commit) {
                continue;
            }

            try {
                $reqEmpresa = $this->peticion(GenerarEmpresaFicticiaRequest::class, [
                    'centroId'  => self::CENTRO_ID,
                    'familiaId' => $familiaId,
                ]);
                $respuestaEmpresa = app(MicroretoIAController::class)->generarEmpresaFicticia($reqEmpresa);
                $datosEmpresa     = json_decode($respuestaEmpresa->getContent(), true);

                if (!is_array($datosEmpresa) || empty($datosEmpresa['nombre_comercial'])) {
                    $this->error('  ✗ La IA no devolvió una empresa válida: ' . json_encode($datosEmpresa));
                    continue;
                }

                if ($forzarTamanoPequeno && !in_array($datosEmpresa['tamano'] ?? null, self::TAMANOS_PEQUENOS, true)) {
                    $datosEmpresa['tamano'] = self::TAMANOS_PEQUENOS[$indiceGlobal % 2];
                }

                $reqDiagnostico = $this->peticion(SimularInfoEmpresaRequest::class, [
                    'empresaNombre'    => $datosEmpresa['nombre_comercial'],
                    'empresaSector'    => $datosEmpresa['sector'] ?? $familia->nombre,
                    'empresaTamano'    => $datosEmpresa['tamano'] ?? null,
                    'empresaUbicacion' => $datosEmpresa['municipio'] ?? null,
                ]);
                $respuestaDiag = app(MicroretoIAController::class)->simularInfoEmpresa($reqDiagnostico);
                $diagnostico   = json_decode($respuestaDiag->getContent(), true);

                if (!is_array($diagnostico) || !array_key_exists('diaANormal', $diagnostico)) {
                    $this->error("  ✗ La IA no devolvió diagnóstico válido para {$datosEmpresa['nombre_comercial']}: " . json_encode($diagnostico));
                    continue;
                }

                // Restricciones/consecuencias llegan como array (checklist) + campo "otra" libre;
                // empresas.restricciones/consecuencias son texto plano — mismo criterio de
                // aplanado que DatosFPController::guardarEmpresa (implode con coma).
                $restricciones = collect($diagnostico['restricciones'] ?? [])->filter()->values();
                if (!empty($diagnostico['otraLimitacion'])) {
                    $restricciones->push($diagnostico['otraLimitacion']);
                }
                $consecuencias = collect($diagnostico['consecuencias'] ?? [])->filter()->values();
                if (!empty($diagnostico['otraConsecuencia'])) {
                    $consecuencias->push($diagnostico['otraConsecuencia']);
                }

                $empresa = Empresa::create([
                    'nombre_comercial'    => $datosEmpresa['nombre_comercial'],
                    'razon_social'        => $datosEmpresa['razon_social'] ?? null,
                    'cif'                 => $datosEmpresa['cif'] ?? null,
                    'centro_educativo'    => $centro->nombre, // legacy, mismo criterio que guardarEmpresa
                    'centro_id'           => self::CENTRO_ID,
                    'sector'              => $datosEmpresa['sector'] ?? null,
                    'tamano'              => $datosEmpresa['tamano'] ?? null,
                    'web'                 => $datosEmpresa['web'] ?? null,
                    'actividad'           => $datosEmpresa['actividad'] ?? null,
                    'persona_contacto'    => $datosEmpresa['persona_contacto'] ?? null,
                    'telefono'            => $datosEmpresa['telefono'] ?? null,
                    'email_general'       => $datosEmpresa['email_general'] ?? null,
                    'direccion'           => $datosEmpresa['direccion'] ?? null,
                    'municipio'           => $datosEmpresa['municipio'] ?? null,
                    'provincia'           => $datosEmpresa['provincia'] ?? null,
                    'codigo_postal'       => $datosEmpresa['codigo_postal'] ?? null,
                    'dia_a_normal'        => $diagnostico['diaANormal'] ?? null,
                    'friccion_area'       => $diagnostico['friccionArea'] ?? null,
                    'friccion_problema'   => $diagnostico['friccionProblema'] ?? null,
                    'restricciones'       => $restricciones->implode(', '),
                    'lo_que_no_quieren'   => $diagnostico['loQueNoQuieren'] ?? null,
                    'consecuencias'       => $consecuencias->implode(', '),
                    'expectativas_alumno' => $diagnostico['expectativasAlumno'] ?? null,
                    // Empresa ficticia "activa": es la que va a tener retos/proyectos generados
                    // encima, tiene más sentido narrativo que "pendiente de llamar".
                    'estado_contacto'     => 'En colaboración activa',
                    'es_simulada'         => true,
                ]);

                // Mismo patrón exacto que DatosFPController::guardarEmpresa: familia_id normalizada
                // + campo legacy 'familia' (texto) en la tabla pivote.
                DB::table('empresa_familia')->insert([
                    'empresa_id' => $empresa->id,
                    'familia'    => $familia->nombre,
                    'familia_id' => $familia->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $creadas++;
                $this->info("  ✓ Empresa #{$empresa->id} creada: {$empresa->nombre_comercial} ({$empresa->tamano})");
            } catch (\Illuminate\Validation\ValidationException $e) {
                $this->error('  ✗ Validación falló: ' . json_encode($e->errors()));
            } catch (\Throwable $e) {
                $this->error("  ✗ Error generando empresa #{$indiceGlobal}: " . $e->getMessage());
            }
        }

        $this->newLine();
        if ($commit) {
            $this->info("Empresas creadas en esta ejecución: {$creadas}/{$aGenerar}.");
        } else {
            $this->comment('Dry-run: ninguna llamada a IA, ninguna escritura. Relanza con --commit para generar de verdad.');
        }

        return self::SUCCESS;
    }
}
