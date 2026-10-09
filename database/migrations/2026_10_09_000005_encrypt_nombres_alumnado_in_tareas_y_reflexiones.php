<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * equipo_tareas.responsable y equipo_reflexiones.autor_nombre guardan el nombre de un
 * alumno (dato personal) en claro, mientras que equipo_miembros.nombre ya va cifrado.
 * Pasan al cast 'encrypted' de sus modelos: la columna crece a TEXT (un nombre de 100
 * caracteres cifrado supera los 255 de VARCHAR) y se cifran las filas existentes.
 * Idempotente: una fila que ya se puede descifrar no se vuelve a cifrar.
 */
return new class extends Migration
{
    private const COLUMNAS = [
        'equipo_tareas'      => 'responsable',
        'equipo_reflexiones' => 'autor_nombre',
    ];

    public function up(): void
    {
        foreach (self::COLUMNAS as $tabla => $columna) {
            Schema::table($tabla, fn (Blueprint $table) => $table->text($columna)->nullable()->change());

            // '' no se puede descifrar al leerlo con el cast: equivale a "sin valor"
            DB::table($tabla)->where($columna, '')->update([$columna => null]);

            $this->recorrer($tabla, $columna, function ($valor) {
                return $this->estaCifrado($valor) ? null : Crypt::encryptString($valor);
            });
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNAS as $tabla => $columna) {
            $this->recorrer($tabla, $columna, function ($valor) {
                return $this->estaCifrado($valor) ? Crypt::decryptString($valor) : null;
            });

            Schema::table($tabla, fn (Blueprint $table) => $table->string($columna)->nullable()->change());
        }
    }

    /** Aplica $transformar a cada valor no nulo; si devuelve null, la fila no se toca. */
    private function recorrer(string $tabla, string $columna, Closure $transformar): void
    {
        DB::table($tabla)->whereNotNull($columna)->orderBy('id')
            ->chunkById(500, function ($filas) use ($tabla, $columna, $transformar) {
                foreach ($filas as $fila) {
                    $nuevo = $transformar($fila->{$columna});
                    if ($nuevo !== null) {
                        DB::table($tabla)->where('id', $fila->id)->update([$columna => $nuevo]);
                    }
                }
            });
    }

    private function estaCifrado(string $valor): bool
    {
        try {
            Crypt::decryptString($valor);
            return true;
        } catch (DecryptException) {
            return false;
        }
    }
};
