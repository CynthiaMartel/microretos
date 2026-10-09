<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * encuentros.alumnados guarda los nombres del alumnado (datos personales) y estaba en claro,
 * mientras que equipo_miembros.nombre ya va cifrado. Pasa al cast 'encrypted:array' del
 * modelo Encuentro: la columna deja de ser JSON (el valor cifrado no es JSON válido) y se
 * cifran las filas existentes, incluidas las de la papelera (soft delete).
 *
 * mediumText: 200 alumnos en claro rondan los 30 KB y el cifrado los acerca al límite de TEXT.
 * Idempotente: una fila que ya se puede descifrar no se vuelve a cifrar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('encuentros', function (Blueprint $table) {
            $table->mediumText('alumnados')->nullable()->change();
        });

        // '' no se puede descifrar al leerlo con el cast: equivale a "sin alumnado"
        DB::table('encuentros')->where('alumnados', '')->update(['alumnados' => null]);

        DB::table('encuentros')->whereNotNull('alumnados')->orderBy('id')
            ->chunkById(200, function ($filas) {
                foreach ($filas as $fila) {
                    if ($this->estaCifrado($fila->alumnados)) {
                        continue;
                    }
                    DB::table('encuentros')->where('id', $fila->id)
                        ->update(['alumnados' => Crypt::encryptString($fila->alumnados)]);
                }
            });
    }

    public function down(): void
    {
        DB::table('encuentros')->whereNotNull('alumnados')->orderBy('id')
            ->chunkById(200, function ($filas) {
                foreach ($filas as $fila) {
                    if (!$this->estaCifrado($fila->alumnados)) {
                        continue;
                    }
                    DB::table('encuentros')->where('id', $fila->id)
                        ->update(['alumnados' => Crypt::decryptString($fila->alumnados)]);
                }
            });

        Schema::table('encuentros', function (Blueprint $table) {
            $table->json('alumnados')->nullable()->change();
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
