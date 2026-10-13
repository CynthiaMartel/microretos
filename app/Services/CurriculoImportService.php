<?php

namespace App\Services;

use App\Models\CicloFormativo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Importa los módulos, RA y CE de un ciclo desde un CSV (una fila por criterio de evaluación):
 *
 *   modulo_codigo ; modulo_nombre ; curso ; horas ; ra ; ce
 *
 * Las filas con el mismo módulo (por código, o por nombre si no hay código) forman un módulo;
 * dentro de él, las que repiten el texto del RA forman un RA, en el orden del archivo.
 * Solo añade: los módulos que el ciclo ya tiene (mismo código o nombre) se omiten enteros,
 * para no duplicar ni tocar RA/CE que ya puedan usar retos existentes.
 */
class CurriculoImportService
{
    public const COLUMNAS = ['modulo_codigo', 'modulo_nombre', 'curso', 'horas', 'ra', 'ce'];
    private const MAX_FILAS   = 10000;
    private const MAX_ERRORES = 50;

    /**
     * @return array{errores: list<string>, modulos_nuevos: list<array{nombre: string, ras: int, ces: int}>,
     *               modulos_omitidos: list<string>, total_ras: int, total_ces: int, importado: bool}
     */
    public function importar(CicloFormativo $ciclo, UploadedFile $archivo, bool $simular): array
    {
        [$modulos, $errores] = $this->leer($archivo);

        $resultado = ['errores' => $errores, 'modulos_nuevos' => [], 'modulos_omitidos' => [],
                      'total_ras' => 0, 'total_ces' => 0, 'importado' => false];
        if ($errores) {
            return $resultado;
        }

        // Módulos que el ciclo ya tiene: se omiten (solo se añade lo nuevo)
        $existentes = DB::table('modulos')->where('idcicloformativo', $ciclo->id)->get(['codigoBOE', 'nombre']);
        $codigos = $existentes->pluck('codigoBOE')->filter()->map(fn ($c) => trim($c))->all();
        $nombres = $existentes->pluck('nombre')->map(fn ($n) => $this->clave($n))->all();

        $nuevos = [];
        foreach ($modulos as $modulo) {
            $yaExiste = ($modulo['codigo'] !== '' && in_array($modulo['codigo'], $codigos, true))
                || in_array($this->clave($modulo['nombre']), $nombres, true);
            if ($yaExiste) {
                $resultado['modulos_omitidos'][] = $modulo['nombre'];
                continue;
            }
            $nuevos[] = $modulo;
            $ces = array_sum(array_map('count', array_column($modulo['ras'], 'ces')));
            $resultado['modulos_nuevos'][] = ['nombre' => $modulo['nombre'], 'ras' => count($modulo['ras']), 'ces' => $ces];
            $resultado['total_ras'] += count($modulo['ras']);
            $resultado['total_ces'] += $ces;
        }

        if ($simular || !$nuevos) {
            return $resultado;
        }

        DB::transaction(function () use ($ciclo, $nuevos) {
            foreach ($nuevos as $modulo) {
                $moduloId = DB::table('modulos')->insertGetId([
                    'idcicloformativo' => $ciclo->id,
                    'codigoBOE'        => $modulo['codigo'],
                    'nombre'           => $modulo['nombre'],
                    'curso'            => $modulo['curso'],
                    'horastotales'     => $modulo['horas'],
                ]);
                foreach ($modulo['ras'] as $ra) {
                    $raId = DB::table('resultados_aprendizaje')->insertGetId(['idmodulo' => $moduloId, 'ra' => $ra['texto']]);
                    DB::table('criterios_evaluacion')->insert(
                        array_map(fn ($ce) => ['idresultadoaprendizaje' => $raId, 'ce' => $ce], $ra['ces'])
                    );
                }
            }
        });
        $resultado['importado'] = true;

        return $resultado;
    }

    /**
     * Lee y valida el CSV. Admite «;» (Excel en español) o «,» y BOM UTF-8.
     *
     * @return array{0: list<array{codigo: string, nombre: string, curso: int, horas: int, ras: list<array{texto: string, ces: list<string>}>}>, 1: list<string>}
     */
    private function leer(UploadedFile $archivo): array
    {
        $contenido = (string) file_get_contents($archivo->getRealPath());
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido);
        if (!mb_check_encoding($contenido, 'UTF-8')) {
            // Excel guarda «CSV» a secas en Windows-1252: se convierte para no perder tildes
            $contenido = mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252');
        }

        $lineas = preg_split('/\R/', $contenido);
        $cabecera = array_shift($lineas) ?? '';
        $separador = substr_count($cabecera, ';') >= substr_count($cabecera, ',') ? ';' : ',';
        $columnas = array_map(fn ($c) => mb_strtolower(trim($c)), str_getcsv($cabecera, $separador));

        $faltan = array_diff(self::COLUMNAS, $columnas);
        if ($faltan) {
            return [[], ['Faltan columnas en la cabecera: ' . implode(', ', $faltan) . '. Descarga la plantilla para ver el formato.']];
        }
        $pos = array_flip($columnas);

        // El CSV puede tener celdas con saltos de línea entre comillas: se reconstruye por registros
        $registros = [];
        $buffer = '';
        foreach ($lineas as $linea) {
            $buffer = $buffer === '' ? $linea : $buffer . "\n" . $linea;
            if (substr_count($buffer, '"') % 2 === 0) {
                $registros[] = $buffer;
                $buffer = '';
            }
        }

        $modulos = [];
        $errores = [];
        foreach ($registros as $i => $registro) {
            $fila = $i + 2; // nº de fila en Excel (1 = cabecera)
            if (trim($registro, " \t;,") === '') {
                continue;
            }
            if ($i >= self::MAX_FILAS) {
                $errores[] = 'El archivo tiene demasiadas filas (máximo ' . self::MAX_FILAS . ').';
                break;
            }
            $celdas = str_getcsv($registro, $separador);
            $valor = fn (string $col) => $this->limpiar($celdas[$pos[$col]] ?? '');

            $codigo = $valor('modulo_codigo');
            $nombre = $valor('modulo_nombre');
            $curso  = $valor('curso');
            $horas  = $valor('horas');
            $ra     = $valor('ra');
            $ce     = $valor('ce');

            $fallos = [];
            if ($nombre === '' || mb_strlen($nombre) > 255) $fallos[] = 'modulo_nombre obligatorio (máx. 255 caracteres)';
            if (mb_strlen($codigo) > 6)                    $fallos[] = 'modulo_codigo de máx. 6 caracteres';
            if (!in_array($curso, ['1', '2'], true))        $fallos[] = 'curso debe ser 1 o 2';
            if ($horas !== '' && !ctype_digit($horas))      $fallos[] = 'horas debe ser un número entero';
            if ($ra === '' || mb_strlen($ra) > 1000)        $fallos[] = 'ra obligatorio (máx. 1000 caracteres)';
            if ($ce === '' || mb_strlen($ce) > 2000)        $fallos[] = 'ce obligatorio (máx. 2000 caracteres)';
            if ($fallos) {
                $errores[] = "Fila {$fila}: " . implode('; ', $fallos) . '.';
                if (count($errores) >= self::MAX_ERRORES) {
                    $errores[] = 'Hay más errores; corrige estos y vuelve a comprobar.';
                    break;
                }
                continue;
            }

            $claveModulo = $codigo !== '' ? "c:{$codigo}" : 'n:' . $this->clave($nombre);
            $modulos[$claveModulo] ??= ['codigo' => $codigo, 'nombre' => $nombre, 'curso' => (int) $curso, 'horas' => (int) $horas, 'ras' => []];
            $claveRa = $this->clave($ra);
            $modulos[$claveModulo]['ras'][$claveRa] ??= ['texto' => $ra, 'ces' => []];
            if (!in_array($ce, $modulos[$claveModulo]['ras'][$claveRa]['ces'], true)) {
                $modulos[$claveModulo]['ras'][$claveRa]['ces'][] = $ce;
            }
        }

        if (!$errores && !$modulos) {
            $errores[] = 'El archivo no tiene ninguna fila de datos.';
        }

        $modulos = array_values(array_map(fn ($m) => ['ras' => array_values($m['ras'])] + $m, $modulos));

        return [$modulos, $errores];
    }

    // Texto plano: fuera etiquetas HTML y espacios sobrantes (viene copiado del BOE)
    private function limpiar(string $texto): string
    {
        return trim(preg_replace('/[ \t]+/u', ' ', strip_tags($texto)));
    }

    private function clave(string $texto): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($texto)));
    }
}
