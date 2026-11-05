<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Vuelve a cifrar con la APP_KEY actual las columnas con cast 'encrypted' que se cifraron
 * con otra clave. Caso de uso: importar en producción un volcado de la BD local (cada
 * entorno tiene su APP_KEY, y sin esto el alumnado de los encuentros, los responsables de
 * tareas, los autores de reflexiones y las firmas quedan ilegibles).
 *
 * La clave anterior se lee de la variable de entorno CLAVE_ANTERIOR (formato del .env,
 * "base64:…"), no de una opción: así no queda en el historial de la shell.
 *   CLAVE_ANTERIOR='base64:…' php artisan datos:recifrar            → dry-run
 *   CLAVE_ANTERIOR='base64:…' php artisan datos:recifrar --commit   → escribe
 *
 * Idempotente: un valor que ya se descifra con la clave actual se deja tal cual, así que
 * relanzarlo tras un corte no estropea nada. Escribe en crudo (query builder) para no
 * pasar por los casts ni tocar updated_at.
 */
class RecifrarDatos extends Command
{
    protected $signature = 'datos:recifrar
                            {--commit : Aplica los cambios. Sin esta opción es un dry-run.}';

    protected $description = 'Vuelve a cifrar con la APP_KEY actual los datos cifrados con otra clave (dry-run por defecto).';

    // tabla => columnas con cast 'encrypted' / 'encrypted:array' (ver los modelos)
    private const COLUMNAS = [
        'encuentros'         => ['alumnados'],                        // Encuentro
        'equipo_tareas'      => ['responsable'],                      // EquipoTarea
        'equipo_reflexiones' => ['autor_nombre'],                     // EquipoReflexion
        'empresa_firmas'     => ['firmante_dni', 'firmante_email'],   // EmpresaFirma
    ];

    public function handle(): int
    {
        $clave = (string) env('CLAVE_ANTERIOR', '');
        if ($clave === '') {
            $this->error("Falta la variable de entorno CLAVE_ANTERIOR (la APP_KEY con la que se cifraron los datos).");
            return self::FAILURE;
        }
        if (str_starts_with($clave, 'base64:')) {
            $clave = base64_decode(substr($clave, 7));
        }
        $anterior = new Encrypter($clave, config('app.cipher'));

        $commit = (bool) $this->option('commit');
        $filas = [];
        $errores = 0;

        foreach (self::COLUMNAS as $tabla => $columnas) {
            $recifrados = $yaActuales = $ilegibles = 0;

            DB::table($tabla)->select(array_merge(['id'], $columnas))->orderBy('id')
                ->chunkById(500, function ($registros) use ($tabla, $columnas, $anterior, $commit, &$recifrados, &$yaActuales, &$ilegibles) {
                    foreach ($registros as $registro) {
                        $cambios = [];
                        foreach ($columnas as $columna) {
                            $valor = $registro->{$columna};
                            if ($valor === null || $valor === '') continue;

                            try {
                                Crypt::decryptString($valor);
                                $yaActuales++;
                                continue;
                            } catch (DecryptException) {
                                // no es de la clave actual: se prueba con la anterior
                            }

                            try {
                                $cambios[$columna] = Crypt::encryptString($anterior->decryptString($valor));
                                $recifrados++;
                            } catch (DecryptException) {
                                $ilegibles++;
                            }
                        }
                        if ($commit && $cambios) {
                            DB::table($tabla)->where('id', $registro->id)->update($cambios);
                        }
                    }
                });

            $errores += $ilegibles;
            $filas[] = [$tabla, implode(', ', $columnas), $recifrados, $yaActuales, $ilegibles];
        }

        $this->table(['Tabla', 'Columnas', $commit ? 'Recifrados' : 'Se recifrarían', 'Ya con la clave actual', 'Ilegibles con ambas'], $filas);

        if ($errores) {
            $this->warn("{$errores} valores no se descifran ni con la clave actual ni con la anterior: se dejan como están.");
        }
        if (!$commit) {
            $this->warn('Dry-run: relanza con --commit para aplicar los cambios.');
        }

        return self::SUCCESS;
    }
}
