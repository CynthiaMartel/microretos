<?php

namespace App\Console\Commands;

use App\Models\Microproyecto;
use App\Models\User;
use App\Services\ModulosDocenteService;
use Illuminate\Console\Command;

/**
 * Rellena los módulos que imparte cada docente que aún no tiene ninguno, con los módulos de
 * sus propios proyectos (modulos_seleccionados) que pertenezcan a la oferta de su centro.
 * Pensado para la demo y para el arranque en producción: después cada docente los ajusta
 * en «Mi usuario» o el admin de su centro en Gestión de usuarios.
 *
 * Dry-run por defecto; --commit guarda. Nunca toca a docentes que ya tengan módulos.
 */
class AsignarModulosDocentes extends Command
{
    protected $signature = 'docentes:asignar-modulos-de-proyectos
                            {--commit : Guarda los módulos. Sin esta opción es un dry-run.}';

    protected $description = 'Asigna a los docentes sin módulos los módulos de sus propios proyectos (oferta de su centro).';

    public function handle(ModulosDocenteService $modulos): int
    {
        $commit = (bool) $this->option('commit');

        $docentes = User::where('role', User::ROLE_DOCENTE)->whereNotNull('centro_educativo_id')
            ->whereDoesntHave('modulos')->orderBy('id')->get();

        $filas = [];
        $total = 0;
        foreach ($docentes as $docente) {
            $ids = Microproyecto::where('user_id', $docente->id)->pluck('modulos_seleccionados')
                ->flatten(1)
                ->map(fn ($m) => is_array($m) ? (int) ($m['id'] ?? 0) : 0)
                ->filter(fn (int $id) => $id > 0)->unique()->values()->all();

            $validos = $modulos->idsValidosParaCentro($docente->centro_educativo_id, $ids);
            if (!$validos) {
                continue;
            }

            $filas[] = [$docente->id, $docente->name, count($validos), count($ids) - count($validos)];
            $total++;
            if ($commit) {
                $modulos->sincronizar($docente, $validos);
            }
        }

        $this->table(['Docente', 'Nombre', 'Módulos asignados', 'Fuera de la oferta del centro'], $filas);
        $this->line("Docentes sin módulos: {$docentes->count()} · con módulos deducibles de sus proyectos: {$total}");

        $commit
            ? $this->info('Hecho: módulos guardados.')
            : $this->warn('Dry-run: no se ha escrito nada. Ejecuta con --commit para guardarlos.');

        return self::SUCCESS;
    }
}
