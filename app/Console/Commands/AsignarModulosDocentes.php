<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ModulosDocenteService;
use Illuminate\Console\Command;

/**
 * Rellena los módulos que imparte cada docente (y admin de centro: también crea proyectos) que
 * aún no tiene ninguno, con los módulos de sus propios proyectos (modulos_seleccionados) que
 * pertenezcan a la oferta de su centro. Quien no tiene proyectos con módulos se salta.
 * Pensado para la demo y para el arranque en producción: después cada docente los ajusta
 * en «Mi usuario» o el admin de su centro en Gestión de usuarios.
 *
 * Dry-run por defecto; --commit guarda. Nunca toca a docentes que ya tengan módulos.
 */
class AsignarModulosDocentes extends Command
{
    protected $signature = 'docentes:asignar-modulos-de-proyectos
                            {--commit : Guarda los módulos. Sin esta opción es un dry-run.}';

    protected $description = 'Asigna a los docentes y admins de centro sin módulos los módulos de sus propios proyectos (oferta de su centro).';

    public function handle(ModulosDocenteService $modulos): int
    {
        $commit = (bool) $this->option('commit');

        $docentes = User::whereIn('role', [User::ROLE_DOCENTE, User::ROLE_ADMIN])->whereNotNull('centro_educativo_id')
            ->whereDoesntHave('modulos')->orderBy('id')->get();

        $filas = [];
        $total = 0;
        foreach ($docentes as $docente) {
            $uso     = $modulos->usoEnProyectos($docente);
            $validos = $uso['modulos'];
            if (!$validos) {
                continue;
            }

            $filas[] = [$docente->id, $docente->name, count($validos), $uso['descartados']];
            $total++;
            if ($commit) {
                $modulos->sincronizar($docente, $validos);
            }
        }

        $this->table(['Usuario', 'Nombre', 'Módulos asignados', 'Fuera de la oferta del centro'], $filas);
        $this->line("Docentes y admins de centro sin módulos: {$docentes->count()} · con módulos deducibles de sus proyectos: {$total}");

        $commit
            ? $this->info('Hecho: módulos guardados.')
            : $this->warn('Dry-run: no se ha escrito nada. Ejecuta con --commit para guardarlos.');

        return self::SUCCESS;
    }
}
