<?php

namespace App\Console\Commands;

use App\Models\Microreto;
use App\Services\MicroretoFichaService;
use Illuminate\Console\Command;

/**
 * Retos anteriores a T3 (sin `diagnostico_empresa`): les guarda una copia del diagnóstico
 * ACTUAL de su empresa, para que a partir de ahora editar la empresa no altere su ficha.
 * Ojo: si la empresa ya se editó después de crear el reto, la copia refleja lo de hoy,
 * no lo de entonces — no hay forma de recuperar el diagnóstico original.
 *
 * Por defecto es un dry-run: no persiste nada hasta pasar --commit.
 */
class CapturarDiagnosticoMicroretos extends Command
{
    protected $signature = 'microretos:capturar-diagnostico
                            {--commit : Guarda los cambios en BD. Sin esta opción es un dry-run.}';

    protected $description = 'Guarda en los retos antiguos una copia del diagnóstico actual de su empresa (ver T3 en TAREAS_PENDIENTES.md).';

    public function handle(): int
    {
        $commit = (bool) $this->option('commit');
        $query  = Microreto::whereNull('diagnostico_empresa')->whereNotNull('empresa_id')->with('empresa');

        $total = (clone $query)->count();
        $this->info(($commit ? '' : '[dry-run] ') . "Retos sin copia del diagnóstico: {$total}");

        $actualizados = 0;
        $sinEmpresa   = 0;
        $query->chunkById(200, function ($retos) use ($commit, &$actualizados, &$sinEmpresa) {
            foreach ($retos as $reto) {
                if (!$reto->empresa) { $sinEmpresa++; continue; } // empresa borrada
                if ($commit) {
                    $reto->forceFill(['diagnostico_empresa' => MicroretoFichaService::copiaDiagnostico($reto->empresa)])->save();
                }
                $actualizados++;
            }
        });

        $this->info(($commit ? 'Actualizados' : 'Se actualizarían') . ": {$actualizados}" . ($sinEmpresa ? " · sin empresa (se omiten): {$sinEmpresa}" : ''));
        if (!$commit) $this->comment('Nada guardado. Repite con --commit para aplicarlo.');

        return self::SUCCESS;
    }
}
