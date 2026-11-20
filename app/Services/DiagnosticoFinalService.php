<?php

namespace App\Services;

use App\Models\Equipo;
use App\Support\AliasGenerator;

/**
 * Arma el contexto textual del diagnóstico final IA de un equipo (ver
 * EquipoGestionController::diagnosticoFinal()) a partir de todo su workspace:
 * encuentro, proyecto/reto, RA-CE, contenido de las 5 fases, evaluación
 * curricular del docente y reflexiones. Aislado del controller para poder
 * testear el armado del contexto sin pasar por HTTP.
 */
class DiagnosticoFinalService
{
    // Mismas 5 fases y etiquetas que fasesProyecto.js (frontend) — duplicado deliberado,
    // el backend no depende de assets del SPA.
    private const FASE_LABELS = [
        0 => 'Inicio del equipo',
        1 => 'Análisis del reto',
        2 => 'Diseño de solución y desarrollo',
        3 => 'Entrega de la solución',
        4 => 'Presentación',
    ];

    /**
     * Requiere que $equipo tenga cargadas las relaciones encuentro, microproyecto.microreto,
     * miembros, fases y reflexiones (ver diagnosticoFinal()).
     */
    public static function contexto(Equipo $equipo): string
    {
        $encuentro = $equipo->encuentro;
        $proyecto  = $equipo->microproyecto;

        $ctx = "=== ENCUENTRO ===\n";
        if ($encuentro?->ciclo_formativo)  $ctx .= "Ciclo formativo: {$encuentro->ciclo_formativo}\n";
        if ($encuentro?->curso)            $ctx .= "Curso: {$encuentro->curso}\n";
        if ($encuentro?->grupo)            $ctx .= "Grupo/clase: {$encuentro->grupo}\n";
        if ($encuentro?->centro_educativo) $ctx .= "Centro educativo: {$encuentro->centro_educativo}\n";

        $ctx .= "\n=== EQUIPO ===\n";
        $ctx .= "Nombre del equipo: {$equipo->nombre}\n";
        // Protección de datos: los alias llevan el nombre de pila; al final seudonimizar() los
        // cambia por «Integrante N» y a la IA no le llega ninguno
        $miembros = $equipo->miembros->map(fn($m) => $m->alias . ($m->rol ? " ({$m->rol})" : ''))->implode(', ');
        $ctx .= 'Participantes: ' . ($miembros ?: 'sin registrar') . "\n";

        if ($proyecto) {
            $ctx .= "\n=== PROYECTO / RETO ===\n";
            if ($proyecto->titulo) $ctx .= "Título: {$proyecto->titulo}\n";
            if (!empty($proyecto->modulos_seleccionados)) {
                $nombresModulos = collect($proyecto->modulos_seleccionados)
                    ->map(fn($m) => is_array($m) ? ($m['nombre'] ?? null) : $m)
                    ->filter()
                    ->implode(', ');
                if ($nombresModulos) $ctx .= "Módulos trabajados: {$nombresModulos}\n";
            }
            if (!empty($proyecto->resumen)) {
                $ctx .= 'Resumen del proyecto: ' . self::aplanarValor($proyecto->resumen) . "\n";
            }
            if (!empty($proyecto->objetivos)) {
                $ctx .= 'Objetivos de aprendizaje: ' . self::aplanarValor($proyecto->objetivos) . "\n";
            }

            $mr = $proyecto->microreto;
            if ($mr) {
                if ($mr->quien_es)      $ctx .= "Quién es la empresa: {$mr->quien_es}\n";
                if ($mr->dia_a_dia)     $ctx .= "Su día a día: {$mr->dia_a_dia}\n";
                if ($mr->pregunta_reto) $ctx .= "Pregunta del reto: {$mr->pregunta_reto}\n";
                if (!empty($mr->que_necesitan)) $ctx .= 'Qué necesitan: ' . implode('; ', $mr->que_necesitan) . "\n";
                if (!empty($mr->dificultades))  $ctx .= 'Dificultades: ' . implode('; ', $mr->dificultades) . "\n";
                if (!empty($mr->limitaciones))  $ctx .= 'Limitaciones: ' . implode('; ', $mr->limitaciones) . "\n";
            }

            $evaluacion = $proyecto->evaluacion_oficial ?: [];
            if (!empty($evaluacion)) {
                $ctx .= "\nRA/CE asociados:\n";
                foreach ($evaluacion as $item) {
                    $modulo = $item['modulo'] ?? 'Módulo sin especificar';
                    $ra     = $item['ra'] ?? '';
                    $ces    = implode(' | ', $item['ce'] ?? []);
                    $ctx .= "- [{$modulo}] RA: {$ra}" . ($ces ? " — CE: {$ces}" : '') . "\n";
                }
            } elseif (!empty($proyecto->ra_ce)) {
                $ctx .= "\nRA/CE trabajados (texto libre):\n{$proyecto->ra_ce}\n";
            }
        }

        $ctx .= "\n=== TRABAJO DEL EQUIPO POR FASES ===\n";
        foreach (range(0, 4) as $n) {
            $fase = $equipo->fases->firstWhere('numero_fase', $n);
            $ctx .= "\nFase {$n} (" . self::FASE_LABELS[$n] . '): ';
            if (!$fase || !$fase->completada) {
                $ctx .= "no completada\n";
                continue;
            }
            $ctx .= "completada\n";
            if (!empty($fase->datos)) {
                $ctx .= self::resumenFase($fase->datos);
            }
            if ($n !== 4 && $fase->nota_docente !== null) {
                $ctx .= "Nota docente en esta fase: {$fase->nota_docente}/10\n";
            }
            if ($fase->observaciones_docente) {
                $ctx .= "Observaciones docente: {$fase->observaciones_docente}\n";
            }
        }

        $fase4       = $equipo->fases->firstWhere('numero_fase', 4);
        $evalDocente = $fase4?->datos['evaluacion_docente'] ?? null;
        if ($evalDocente) {
            $ctx .= "\n=== EVALUACIÓN CURRICULAR DEL DOCENTE (RA/CE) ===\n";
            foreach ($evalDocente['ras'] ?? [] as $r) {
                $ctx .= "- {$r['ra']}: {$r['nivel']}" . (!empty($r['observaciones']) ? " ({$r['observaciones']})" : '') . "\n";
            }
            if (isset($evalDocente['nota_opcional']) && $evalDocente['nota_opcional'] !== null) {
                $ctx .= "Nota opcional asignada por el docente al cierre del proyecto: {$evalDocente['nota_opcional']}/10\n";
            }
        }

        if ($equipo->reflexiones->isNotEmpty()) {
            $ctx .= "\n=== REFLEXIONES DEL EQUIPO ===\n";
            foreach ($equipo->reflexiones as $r) {
                foreach ($r->respuestas ?? [] as $resp) {
                    if (!empty($resp['respuesta'])) {
                        $ctx .= "- ({$r->tipo}) {$resp['pregunta']}: {$resp['respuesta']}\n";
                    }
                }
            }
        }

        return self::seudonimizar($ctx, $equipo);
    }

    /**
     * Alias de cada miembro → «Integrante N» (N por orden de alta en el equipo).
     *
     * @return array<string, string>
     */
    public static function seudonimos(Equipo $equipo): array
    {
        $mapa = [];
        foreach ($equipo->miembros->values() as $i => $m) {
            if (filled($m->alias)) {
                $mapa[(string) $m->alias] = 'Integrante ' . ($i + 1);
            }
        }

        return $mapa;
    }

    /**
     * Quita del contexto los alias del alumnado antes de enviarlo a OpenAI: el alias completo
     * y, en los textos libres, el nombre de pila suelto («Daniel hizo…»). Si dos miembros
     * comparten nombre de pila, el nombre suelto pasa a «un integrante». Se saltan los
     * nombres de menos de 3 letras y el de relleno («Alumno»), que se confundirían con texto.
     */
    private static function seudonimizar(string $ctx, Equipo $equipo): string
    {
        $mapa = self::seudonimos($equipo);
        $ctx  = strtr($ctx, $mapa); // strtr prueba antes los alias más largos

        $porNombre = [];
        foreach ($mapa as $alias => $etiqueta) {
            $nombre = AliasGenerator::nombreDeAlias($alias);
            if ($nombre !== null && mb_strlen($nombre) >= 3 && $nombre !== AliasGenerator::NOMBRE_POR_DEFECTO) {
                $porNombre[$nombre][] = $etiqueta;
            }
        }
        foreach ($porNombre as $nombre => $etiquetas) {
            $sustituto = count($etiquetas) === 1 ? $etiquetas[0] : 'un integrante';
            $ctx = (string) preg_replace('/(?<!\p{L})' . preg_quote($nombre, '/') . '(?!\p{L})/u', $sustituto, $ctx);
        }

        return $ctx;
    }

    /**
     * Devuelve los alias a la respuesta de la IA: cada «Integrante N» (o «integrante N»)
     * vuelve a ser el alias de ese miembro, en todos los textos del diagnóstico.
     *
     * @param  array<array-key, mixed>  $diagnostico
     * @return array<array-key, mixed>
     */
    public static function restaurarAlias(array $diagnostico, Equipo $equipo): array
    {
        $porNumero = [];
        foreach (self::seudonimos($equipo) as $alias => $etiqueta) {
            $porNumero[(int) substr($etiqueta, strlen('Integrante '))] = $alias;
        }

        // «el/al/del integrante 2» → «Daniel…/a Daniel…/de Daniel…»: con nombre no va artículo
        $sinArticulo = ['el' => '', 'al' => 'a ', 'del' => 'de '];

        array_walk_recursive($diagnostico, function (&$valor) use ($porNumero, $sinArticulo) {
            if (is_string($valor)) {
                $valor = (string) preg_replace_callback('/(?<!\p{L})(?:(el|al|del) )?integrante (\d+)(?!\d)/iu',
                    function ($m) use ($porNumero, $sinArticulo) {
                        $alias = $porNumero[(int) $m[2]] ?? null;
                        if ($alias === null) return $m[0];
                        $articulo = mb_strtolower($m[1]);
                        $prefijo  = $sinArticulo[$articulo] ?? '';
                        // Respeta la mayúscula de inicio de frase («Del integrante…» → «De …»)
                        return ($m[1] !== '' && $m[1] !== $articulo ? ucfirst($prefijo) : $prefijo) . $alias;
                    }, $valor);
            }
        });

        return $diagnostico;
    }

    // Aplana el contenido libre de una fase (equipo_fases.datos) a texto legible para el
    // prompt. Las formas conocidas (síntesis pregunta/respuesta, miembros, listas simples)
    // se resuelven a frases; cualquier otra estructura cae al fallback recursivo genérico.
    private static function resumenFase(array $datos): string
    {
        $texto = '';
        foreach ($datos as $clave => $valor) {
            if ($clave === 'evaluacion_docente') continue; // se muestra aparte
            $contenido = self::aplanarValor($valor);
            if ($contenido !== '') {
                $etiqueta = str_replace('_', ' ', $clave);
                $texto .= "- {$etiqueta}: {$contenido}\n";
            }
        }
        return $texto;
    }

    private static function aplanarValor($valor): string
    {
        if ($valor === null || $valor === '') return '';
        if (is_bool($valor)) return $valor ? 'Sí' : 'No';
        if (is_string($valor) || is_numeric($valor)) return (string) $valor;

        if (is_array($valor)) {
            if (array_is_list($valor)) {
                if ($valor && is_array($valor[0]) && isset($valor[0]['pregunta'])) {
                    $items = array_map(fn($item) => "{$item['pregunta']} → " . ($item['respuesta'] ?? 'sin responder'), $valor);
                    return implode('; ', $items);
                }
                // Miembros de F0: se identifican por alias (sin nombre real)
                if ($valor && is_array($valor[0]) && isset($valor[0]['alias'])) {
                    $items = array_map(fn($item) => ($item['alias'] ?? '') . (!empty($item['rol']) ? " ({$item['rol']})" : ''), $valor);
                    return implode('; ', $items);
                }
                if ($valor && is_array($valor[0]) && isset($valor[0]['nombre'])) {
                    $items = array_map(fn($item) => $item['nombre'] . (!empty($item['rol']) ? " ({$item['rol']})" : ''), $valor);
                    return implode('; ', $items);
                }
                return implode('; ', array_map(fn($v) => is_scalar($v) ? (string) $v : self::aplanarValor($v), $valor));
            }

            $items = [];
            foreach ($valor as $k => $v) {
                $t = self::aplanarValor($v);
                if ($t !== '') $items[] = str_replace('_', ' ', $k) . ": {$t}";
            }
            return implode('; ', $items);
        }

        return '';
    }
}
