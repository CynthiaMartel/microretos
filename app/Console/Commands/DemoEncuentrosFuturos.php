<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\InvocaControladoresReales;
use App\Http\Controllers\EncuentroController;
use App\Http\Requests\StoreEncuentroRequest;
use App\Models\Encuentro;
use App\Models\Microproyecto;
use App\Models\User;
use Carbon\CarbonImmutable;
use App\Console\Commands\Concerns\AlumnadoFicticio;
use Illuminate\Console\Command;

/**
 * Crea unos pocos encuentros FUTUROS (de la semana que viene a mediados de diciembre) para
 * proyectos de demo validados del curso en marcha que aún no tienen encuentro, para que el
 * panel docente muestre «Próximos encuentros» y el calendario tenga actividad por delante.
 *
 * Complementa a demo:reubicar-curso: los encuentros de demo existentes tienen todos fases
 * completadas, así que no se pueden mover al futuro sin incoherencias.
 *
 * Reutiliza el flujo real (StoreEncuentroRequest + EncuentroController::store) en nombre del
 * docente propietario de cada proyecto, con alumnado ficticio — igual que demo:generar-encuentros-
 * equipos, pero SIN avanzar fases, sin IA y sin cerrar el proyecto: el encuentro queda programado.
 *
 * Ámbito: solo proyectos es_demo=1 del centro DuaLab (id=10). Nunca datos de centros reales.
 *
 * Dry-run por defecto: sin --commit solo lista qué encuentros se crearían.
 */
class DemoEncuentrosFuturos extends Command
{
    use AlumnadoFicticio;

    use InvocaControladoresReales;

    protected $signature = 'demo:encuentros-futuros
                            {--total=4 : Cuántos encuentros futuros crear.}
                            {--commit : Crea los encuentros. Sin esta opción es un dry-run.}';

    protected $description = 'Crea encuentros futuros (próximos encuentros) para proyectos de demo validados de este curso.';

    private const CENTRO_DEMO = 10; // DuaLab
    private const ROLES     = ['tiempos', 'documentacion', 'foco']; // el primero de cada equipo es 'portavoz'

    public function handle(): int
    {
        $commit = (bool) $this->option('commit');
        $total  = max(1, (int) $this->option('total'));
        $hoy    = CarbonImmutable::now()->startOfDay();
        $inicioCurso = CarbonImmutable::create($hoy->month >= 9 ? $hoy->year : $hoy->year - 1, 9, 1);

        if (!$commit) {
            $this->warn('Modo DRY-RUN — no se crea nada. Relanza con --commit para crear los encuentros.');
        }

        $proyectos = Microproyecto::where('es_demo', true)->where('centro_id', self::CENTRO_DEMO)
            ->where('estado', 'validado')->where('created_at', '>=', $inicioCurso)
            ->whereDoesntHave('encuentros')
            ->orderBy('id')->take($total)->get();

        if ($proyectos->isEmpty()) {
            $this->warn('No hay proyectos de demo validados de este curso sin encuentro.');
            return self::SUCCESS;
        }

        // Fechas repartidas entre la semana que viene y el 18 de diciembre, en días laborables
        $desde = $hoy->addDays(5);
        $hasta = CarbonImmutable::create($hoy->year, 12, 18);
        $paso  = max(1, intdiv((int) $desde->diffInDays($hasta, true), max(1, $proyectos->count())));

        $creados = 0;
        foreach ($proyectos->values() as $i => $proyecto) {
            $fecha = $desde->addDays($i * $paso);
            if ($fecha->isSaturday()) $fecha = $fecha->addDays(2);
            if ($fecha->isSunday())   $fecha = $fecha->addDay();

            $numEquipos = 2;
            [$letra, $curso] = $this->claseAleatoria($proyecto->curso);
            [$alumnados, $numAlumnos] = $this->alumnadoDeClase($proyecto->centro_id, $proyecto->ciclo_id, $letra, $curso, $fecha->toDateString(), $numEquipos, self::ROLES);
            $this->line("Proyecto #{$proyecto->id} → encuentro el {$fecha->toDateString()} · {$numEquipos} equipos · {$numAlumnos} alumnos ficticios");

            if (!$commit) continue;

            $docente = User::find($proyecto->user_id);
            if (!$docente) {
                $this->error("  ✗ El proyecto #{$proyecto->id} no tiene docente propietario válido; se omite.");
                continue;
            }
            $this->actuarComo($docente);
            $peticion = $this->peticion(StoreEncuentroRequest::class, [
                'microproyecto_id' => $proyecto->id,
                'fecha'            => $fecha->toDateString(),
                'curso'            => "{$curso}º",
                'grupo'            => $letra,
                'ciclo_formativo'  => $proyecto->cicloFormativo()->value('nombre'),
                'num_alumnos'      => $numAlumnos,
                'num_equipos'      => $numEquipos,
                'alumnados'        => $alumnados,
            ], $docente);
            app(EncuentroController::class)->store($peticion);

            $encuentro = Encuentro::where('microproyecto_id', $proyecto->id)->latest('id')->first();
            if ($encuentro) {
                $creados++;
                $this->info("  ✓ Encuentro #{$encuentro->id} creado.");
            } else {
                $this->error("  ✗ No se pudo crear el encuentro del proyecto #{$proyecto->id}.");
            }
        }

        if ($commit) {
            $this->info("Creados {$creados} encuentros futuros. Recuerda: php artisan cache:clear");
        }
        return self::SUCCESS;
    }
}
