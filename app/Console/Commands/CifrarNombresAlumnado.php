<?php

namespace App\Console\Commands;

use App\Models\EquipoMiembro;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;

/**
 * Cifra en BD el nombre real de equipo_miembros que aún esté en texto plano.
 * Dry-run por defecto — ninguna mutación de datos existentes sin confirmación explícita.
 *
 * IMPORTANTE: este comando debe ejecutarse con --commit ANTES de añadir el cast
 * `encrypted` a EquipoMiembro::$casts — si el cast ya estuviera activo, Eloquent
 * intentaría descifrar el texto plano al leerlo y fallaría.
 */
class CifrarNombresAlumnado extends Command
{
    protected $signature = 'alumnado:cifrar-nombres
                            {--commit : Guarda los nombres cifrados en BD. Sin esta opción es un dry-run.}';

    protected $description = 'Cifra en BD el nombre real de los miembros de equipo que aún esté en texto plano.';

    public function handle(): int
    {
        $commit     = $this->option('commit');
        $cifrados   = 0;
        $yaCifrados = 0;

        foreach (EquipoMiembro::all() as $miembro) {
            $valor = $miembro->getRawOriginal('nombre');

            if ($this->pareceCifrado($valor)) {
                $yaCifrados++;
                continue;
            }

            $this->line("Miembro {$miembro->id}: cifrando \"{$valor}\"");
            if ($commit) {
                $miembro->newQuery()->whereKey($miembro->id)->update([
                    'nombre' => Crypt::encryptString($valor),
                ]);
            }
            $cifrados++;
        }

        if (!$commit) {
            $this->warn("Dry-run: {$cifrados} nombres se cifrarían ({$yaCifrados} ya lo estaban). Ejecuta con --commit para guardarlo.");
        } else {
            $this->info("Hecho: {$cifrados} nombres cifrados ({$yaCifrados} ya lo estaban).");
        }

        return self::SUCCESS;
    }

    // El payload de Crypt::encryptString es JSON (iv/value/mac) codificado en base64.
    private function pareceCifrado(string $valor): bool
    {
        $decoded = base64_decode($valor, true);
        if ($decoded === false) {
            return false;
        }
        $json = json_decode($decoded, true);
        return is_array($json) && isset($json['iv'], $json['value'], $json['mac']);
    }
}
