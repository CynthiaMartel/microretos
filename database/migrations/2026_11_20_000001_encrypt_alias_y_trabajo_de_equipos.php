<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El alias del alumnado ("NombreDePila Animal Color") lleva el nombre de pila real desde el
 * 20-10-2026, pero se guardaba en claro (el cifrado lo tenía la columna nombre, ya eliminada).
 * Se cifra con el cast del modelo junto con sus copias:
 *   - equipo_miembros.alias              → 'encrypted'
 *   - equipo_fases.datos (F0 lista los alias; el resto es el trabajo del grupo) → 'encrypted:array'
 *   - equipos.diagnostico_final (la IA nombra a los integrantes por su alias)   → 'encrypted:array'
 *
 * Las columnas JSON pasan a texto (el valor cifrado no es JSON válido). Idempotente: una fila
 * que ya se puede descifrar no se vuelve a cifrar.
 */
return new class extends Migration
{
    /** @var array<int, array{0: string, 1: string}> tabla, columna */
    private array $columnas = [
        ['equipo_miembros', 'alias'],
        ['equipo_fases', 'datos'],
        ['equipos', 'diagnostico_final'],
    ];

    public function up(): void
    {
        Schema::table('equipo_miembros', function (Blueprint $table) {
            $table->text('alias')->nullable()->change();
        });
        Schema::table('equipo_fases', function (Blueprint $table) {
            $table->longText('datos')->nullable()->change();
        });
        Schema::table('equipos', function (Blueprint $table) {
            $table->longText('diagnostico_final')->nullable()->change();
        });

        foreach ($this->columnas as [$tabla, $columna]) {
            // '' no se puede descifrar al leerlo con el cast: equivale a vacío
            DB::table($tabla)->where($columna, '')->update([$columna => null]);
            $this->recorrer($tabla, $columna, fn ($v) => $this->estaCifrado($v) ? null : Crypt::encryptString($v));
        }
    }

    public function down(): void
    {
        foreach ($this->columnas as [$tabla, $columna]) {
            $this->recorrer($tabla, $columna, fn ($v) => $this->estaCifrado($v) ? Crypt::decryptString($v) : null);
        }

        Schema::table('equipo_miembros', function (Blueprint $table) {
            $table->string('alias')->nullable()->change();
        });
        Schema::table('equipo_fases', function (Blueprint $table) {
            $table->json('datos')->nullable()->change();
        });
        Schema::table('equipos', function (Blueprint $table) {
            $table->json('diagnostico_final')->nullable()->change();
        });
    }

    /** Aplica $nuevo a cada valor no nulo; si devuelve null, la fila no se toca */
    private function recorrer(string $tabla, string $columna, callable $nuevo): void
    {
        DB::table($tabla)->whereNotNull($columna)->select('id', $columna)->orderBy('id')
            ->chunkById(200, function ($filas) use ($tabla, $columna, $nuevo) {
                foreach ($filas as $fila) {
                    $valor = $nuevo($fila->{$columna});
                    if ($valor !== null) {
                        DB::table($tabla)->where('id', $fila->id)->update([$columna => $valor]);
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
