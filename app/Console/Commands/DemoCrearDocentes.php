<?php

namespace App\Console\Commands;

use App\Models\Encuentro;
use App\Models\Familia;
use App\Models\Microproyecto;
use App\Models\User;
use App\Services\ModulosDocenteService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Docentes ficticios en el centro DuaLab para enseñar la colaboración entre compañeros: hasta
 * ahora todos los proyectos de demo eran del admin del centro, así que nadie podía invitar a
 * nadie. Por familia de la demo se crean dos docentes y se les pasan proyectos (con sus
 * encuentros) de los dos ciclos con más proyectos:
 *   - ciclo principal: repartido entre los dos (así comparten módulos y pueden invitarse);
 *   - segundo ciclo: del segundo docente.
 * Cada docente recibe como módulos los de sus propios proyectos. El resto de la demo sigue
 * siendo del admin del centro.
 *
 * Dry-run por defecto; --commit crea las cuentas (contraseña aleatoria que se muestra una sola
 * vez) y transfiere los proyectos. Idempotente: no duplica cuentas ni vuelve a repartir.
 */
class DemoCrearDocentes extends Command
{
    protected $signature = 'demo:crear-docentes
                            {--commit : Crea los docentes y les pasa los proyectos. Sin esta opción es un dry-run.}';

    protected $description = 'Crea docentes ficticios en el centro DuaLab y les reparte proyectos de demo para enseñar la colaboración.';

    private const CENTRO_DEMO = 10;
    private const DOMINIO = 'demo.dualab.es';

    // Dos docentes por familia (nombres de adultos ficticios; no es alumnado)
    private const DOCENTES = [
        ['Marta Hernández Perdomo', 'Jonay Rodríguez Afonso'],
        ['Carmen Díaz Suárez', 'Airam González Cabrera'],
        ['Laura Medina Santana', 'Iván Ramos Déniz'],
    ];

    public function handle(ModulosDocenteService $modulos): int
    {
        $commit = (bool) $this->option('commit');

        $admin = User::where('centro_educativo_id', self::CENTRO_DEMO)->where('role', User::ROLE_ADMIN)->orderBy('id')->first();
        if (!$admin) {
            $this->error('El centro DuaLab no tiene admin propietario de la demo.');
            return self::FAILURE;
        }

        // Familias de la demo, de más a menos proyectos
        $familias = Microproyecto::where('es_demo', true)->where('centro_id', self::CENTRO_DEMO)
            ->whereNotNull('familia_id')->get(['id', 'familia_id', 'ciclo_id', 'user_id'])
            ->groupBy('familia_id')->sortByDesc(fn ($g) => $g->count())->take(count(self::DOCENTES));

        $plan = [];
        $i = 0;
        foreach ($familias as $familiaId => $proyectos) {
            $nombres = self::DOCENTES[$i++];
            $ciclos = $proyectos->groupBy('ciclo_id')->sortByDesc(fn ($g) => $g->count())->keys()->take(2)->values();
            $principal = $proyectos->where('ciclo_id', $ciclos[0] ?? null)->where('user_id', $admin->id)->sortBy('id')->values();
            $segundo   = $proyectos->where('ciclo_id', $ciclos[1] ?? null)->where('user_id', $admin->id)->sortBy('id')->values();

            $plan[] = [
                'familia'  => Familia::whereKey($familiaId)->value('nombre'),
                'docentes' => [
                    ['nombre' => $nombres[0], 'proyectos' => $principal->filter(fn ($p, $k) => $k % 2 === 0)->pluck('id')->all()],
                    ['nombre' => $nombres[1], 'proyectos' => $principal->filter(fn ($p, $k) => $k % 2 === 1)->pluck('id')->merge($segundo->pluck('id'))->all()],
                ],
            ];
        }

        $filas = [];
        foreach ($plan as $p) {
            $mods = array_map(fn ($d) => $modulos->idsValidosParaCentro(self::CENTRO_DEMO, $this->modulosDeProyectos($d['proyectos'])), $p['docentes']);
            $comunes = count(array_intersect($mods[0], $mods[1]));
            foreach ($p['docentes'] as $k => $d) {
                $encuentros = Encuentro::whereIn('microproyecto_id', $d['proyectos'])->count();
                $filas[] = [$p['familia'], $d['nombre'], $this->email($d['nombre']), count($d['proyectos']), $encuentros, count($mods[$k]), $comunes];
            }
        }
        $this->table(['Familia', 'Docente', 'Correo', 'Proyectos', 'Encuentros', 'Módulos', 'Comparte con su pareja'], $filas);

        if (!$commit) {
            $this->warn('Dry-run: no se ha escrito nada. Ejecuta con --commit para crear los docentes.');
            return self::SUCCESS;
        }

        $credenciales = [];
        DB::transaction(function () use ($plan, $modulos, &$credenciales) {
            foreach ($plan as $p) {
                foreach ($p['docentes'] as $d) {
                    $email = $this->email($d['nombre']);
                    $docente = User::where('email', $email)->first();
                    if (!$docente) {
                        $password = Str::password(14);
                        $docente = User::forceCreate([
                            'name' => $d['nombre'], 'email' => $email, 'password' => Hash::make($password),
                            'role' => User::ROLE_DOCENTE, 'centro_educativo_id' => self::CENTRO_DEMO,
                            'email_verified_at' => now(),
                        ]);
                        $credenciales[] = [$d['nombre'], $email, $password];
                    }

                    Microproyecto::whereIn('id', $d['proyectos'])->update(['user_id' => $docente->id]);
                    Encuentro::whereIn('microproyecto_id', $d['proyectos'])->update(['user_id' => $docente->id]);

                    $ids = $this->modulosDeProyectos(Microproyecto::where('user_id', $docente->id)->pluck('id')->all());
                    $modulos->sincronizar($docente, $modulos->idsValidosParaCentro(self::CENTRO_DEMO, $ids));
                }
            }
        });

        if ($credenciales) {
            $this->newLine();
            $this->warn('Contraseñas iniciales (solo se muestran ahora; cámbialas desde Gestión de usuarios si hace falta):');
            $this->table(['Docente', 'Correo', 'Contraseña'], $credenciales);
        }
        $this->info('Hecho: docentes creados y proyectos repartidos.');

        return self::SUCCESS;
    }

    /**
     * Ids de módulo de unos proyectos (modulos_seleccionados [{id, nombre}]).
     *
     * @param array<int, int> $proyectos
     * @return array<int, int>
     */
    private function modulosDeProyectos(array $proyectos): array
    {
        return Microproyecto::whereIn('id', $proyectos)->pluck('modulos_seleccionados')->flatten(1)
            ->map(fn ($m) => is_array($m) ? (int) ($m['id'] ?? 0) : 0)
            ->filter(fn (int $id) => $id > 0)->unique()->values()->all();
    }

    private function email(string $nombre): string
    {
        $partes = explode(' ', Str::lower(Str::ascii($nombre)));

        return $partes[0] . '.' . ($partes[1] ?? 'docente') . '@' . self::DOMINIO;
    }
}
