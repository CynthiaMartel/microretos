<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\InvocaControladoresReales;
use App\Http\Controllers\EncuentroController;
use App\Http\Controllers\EquipoGestionController;
use App\Http\Controllers\EquipoPublicoController;
use App\Http\Requests\EvaluarEquipoRequest;
use App\Http\Requests\GuardarFaseEquipoRequest;
use App\Http\Requests\StoreEncuentroRequest;
use App\Http\Requests\StoreEquipoReflexionRequest;
use App\Models\Empresa;
use App\Models\Encuentro;
use App\Models\Equipo;
use App\Models\EquipoFase;
use App\Models\EquipoReflexion;
use App\Models\EquipoTarea;
use App\Models\Microproyecto;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Toma un porcentaje de los microproyectos simulados ya 'validado' (generados por
 * demo:generar-proyectos, sin encuentro todavía) y para cada uno: crea un Encuentro +
 * equipo(s) de alumnado ficticio (reutilizando EncuentroController::store()/crearCodigo()),
 * hace avanzar cada equipo por sus 5 fases hasta el diagnóstico final (reutilizando
 * EquipoPublicoController/EquipoGestionController/DiagnosticoFinalService, la MISMA lógica
 * que usa el workspace real del alumnado), y cierra el proyecto en estado 'completado'.
 *
 * El contenido de la mayoría de fases (equipo_fases.datos) NO se genera con IA — en la app
 * real lo escribe el alumnado a mano, no hay ningún endpoint de IA para ello; aquí se usa
 * texto de relleno realista construido a partir de los campos reales del reto. Dos partes SÍ
 * usan IA real generada por este comando (no reutilizan ningún endpoint de producción,
 * porque no existe uno — en la app real lo escribe el alumnado): la síntesis de F1 (5
 * preguntas guía, ver generarSintesisReto()) y la reflexión final de F4, grupal + una por
 * cada miembro (ver generarReflexionFinal()). El resto de pasos con IA SÍ reutilizan
 * endpoints reales de producción: verificarCodigoIa/sugerirHallazgo (no usados aquí, no
 * aportan al cierre del proyecto) y, sobre todo, el diagnóstico final del equipo.
 *
 * No se reproduce la validación docente de cada fase individual (validarFase/rechazarFase)
 * — no es necesaria para llegar a 'completado' (basta con que el equipo complete sus 5
 * fases) y añadiría 5 llamadas más por equipo sin aportar al objetivo del comando.
 * Tampoco se suben prototipos reales (requeriría credenciales de Cloudinary y archivos).
 *
 * Dry-run por defecto: sin --commit no se llama a la IA ni se escribe nada.
 */
class DemoGenerarEncuentrosEquipos extends Command
{
    use InvocaControladoresReales;

    protected $signature = 'demo:generar-encuentros-equipos
                            {--porcentaje=40 : % de los proyectos simulados validados (sin encuentro) a llevar a completado.}
                            {--en-curso=0 : Nº adicional de proyectos a los que crear encuentro pero dejar SIN completar (fecha del curso actual: sep/oct/dic 2026), simulando trabajo en progreso.}
                            {--limit=0 : Tope de proyectos a procesar en esta ejecución (0 = sin tope adicional al del porcentaje).}
                            {--corregir-sintesis : Regenera con IA la síntesis F1 de equipos YA EXISTENTES (para corregir contenido antiguo tipo "dato de demo"), sin tocar nada más. Ignora --porcentaje.}
                            {--corregir-reflexiones : Regenera con IA la reflexión grupal Y añade las individuales (una por miembro) que faltaban en equipos YA EXISTENTES. Ignora --porcentaje.}
                            {--commit : Llama a la IA real y persiste en BD. Sin esta opción es un dry-run sin coste.}';

    protected $description = 'Crea encuentros + equipos para un % de los proyectos simulados validados y los lleva a completado (diagnóstico final incluido).';

    private const USUARIO_ID = 48; // DuaLab — propietario del encuentro y quien evalúa/valida
    private const CENTRO_ID  = 10;

    private const ROLES_ROTACION = ['tiempos', 'documentacion', 'foco']; // el primer miembro de cada equipo siempre es 'portavoz'

    // Sin ninguna mención a que el dato es de demo — se ve en el workspace público del
    // alumnado (EquipoWorkspace.vue), sin login.
    private const OBSERVACIONES_DOCENTE = [
        'Equipo participativo, entrega completa y dentro de plazo.',
        'Buen trabajo en equipo, interesante el enfoque dado al currículo del reto.',
        'Entrega ordenada y bien argumentada, con buena comprensión del problema planteado.',
        'Equipo autónomo, ha sabido priorizar bien las tareas de cada fase.',
    ];

    private const NOMBRES = [
        'María', 'Lucía', 'Sofía', 'Martina', 'Paula', 'Daniela', 'Alba', 'Noa', 'Carla', 'Julia',
        'Valeria', 'Marta', 'Laura', 'Sara', 'Elena', 'Carmen', 'Nerea', 'Claudia', 'Irene', 'Andrea',
        'Alejandro', 'Daniel', 'Pablo', 'Hugo', 'Mateo', 'Martín', 'Lucas', 'Adrián', 'David', 'Javier',
        'Diego', 'Iker', 'Marcos', 'Álvaro', 'Rubén', 'Sergio', 'Jorge', 'Óscar', 'Raúl', 'Nicolás',
    ];

    private const APELLIDOS = [
        'García', 'Rodríguez', 'Martínez', 'López', 'Sánchez', 'Pérez', 'González', 'Fernández',
        'Gómez', 'Díaz', 'Ruiz', 'Hernández', 'Jiménez', 'Moreno', 'Muñoz', 'Álvarez', 'Romero',
        'Alonso', 'Gutiérrez', 'Navarro', 'Torres', 'Domínguez', 'Vázquez', 'Ramos', 'Gil', 'Serrano',
    ];

    private const LETRAS_GRUPO = ['A', 'B', 'C', 'D'];

    private const MAX_INTENTOS_SINTESIS = 3;
    private const ESPERA_SINTESIS       = 15;

    // Mismas preguntas exactas que EquipoWorkspace.vue (PREGUNTAS_GRUPAL/PREGUNTAS_INDIVIDUAL)
    // — si cambian ahí, hay que replicarlo aquí también.
    private const PREGUNTAS_GRUPAL = [
        '¿Qué ha funcionado bien como equipo?',
        '¿Qué mejoraríais en vuestra forma de trabajar?',
        '¿Qué aplicaríais en el próximo proyecto?',
    ];

    private const PREGUNTAS_INDIVIDUAL = [
        '¿Qué has aprendido que no sabías antes?',
        '¿Cuál fue la parte más difícil del proyecto?',
        '¿Qué habilidad has practicado más durante el proyecto?',
        '¿Qué cambiarías de tu propio desempeño si repitieras el proyecto?',
        '¿Dónde crees que podrías aplicar lo aprendido en el futuro?',
    ];

    public function handle(): int
    {
        $commit     = (bool) $this->option('commit');
        $porcentaje = max(0, min(100, (int) $this->option('porcentaje')));
        $limite     = (int) $this->option('limit');

        if (!$commit) {
            $this->warn('Modo DRY-RUN — no se llama a la IA ni se escribe nada. Relanza con --commit para generar de verdad.');
        }

        $usuario = User::find(self::USUARIO_ID);
        if (!$usuario) {
            $this->error('No existe el usuario id=' . self::USUARIO_ID . ' (DuaLab). Abortando.');
            return self::FAILURE;
        }
        $this->actuarComo($usuario);

        if ($this->option('corregir-sintesis')) {
            return $this->corregirSintesisExistente($commit, $limite);
        }

        if ($this->option('corregir-reflexiones')) {
            return $this->corregirReflexionesExistentes($commit, $limite);
        }

        $elegibles = Microproyecto::where('estado', 'validado')
            ->where('es_demo', true)
            ->where('centro_id', self::CENTRO_ID) // redundante con es_demo, pero explícito: nunca tocar otro centro
            ->whereDoesntHave('encuentros')
            ->orderBy('id')
            ->get();

        if ($elegibles->isEmpty()) {
            $this->info("No hay proyectos simulados en estado 'validado' sin encuentro todavía. Ejecuta antes demo:generar-proyectos.");
            return self::SUCCESS;
        }

        $aProcesar = $porcentaje > 0 ? (int) ceil($elegibles->count() * $porcentaje / 100) : 0;
        if ($limite > 0) {
            $aProcesar = min($aProcesar, $limite);
        }
        $seleccionados = $elegibles->take($aProcesar);

        $this->info("Proyectos validados sin encuentro: {$elegibles->count()}. Procesando {$aProcesar} ({$porcentaje}%).");
        $this->newLine();

        $completados = 0;

        foreach ($seleccionados as $proyecto) {
            $microreto = $proyecto->microreto;
            $numEquipos = random_int(1, 2);
            [$alumnados, $totalAlumnos] = $this->generarAlumnado($numEquipos);

            $this->line("Proyecto #{$proyecto->id} \"{$proyecto->titulo}\" → {$numEquipos} equipo(s), {$totalAlumnos} alumnos ficticios");

            if (!$commit) {
                continue;
            }

            try {
                $fecha = $this->fechaCursoAnterior();

                $reqEncuentro = $this->peticion(StoreEncuentroRequest::class, [
                    'microproyecto_id' => $proyecto->id,
                    'fecha'            => $fecha,
                    'curso'            => (string) ($proyecto->curso ?: '2'),
                    'grupo'            => $this->generarGrupo($proyecto->curso),
                    'num_alumnos'      => $totalAlumnos,
                    'num_equipos'      => $numEquipos,
                    'alumnados'        => $alumnados,
                ], $usuario);
                app(EncuentroController::class)->store($reqEncuentro);

                $encuentro = Encuentro::where('microproyecto_id', $proyecto->id)->latest('id')->first();
                if (!$encuentro) {
                    $this->error("  ✗ No se pudo crear el encuentro del proyecto #{$proyecto->id}.");
                    continue;
                }

                $reqCodigo = $this->peticion(Request::class, [], $usuario);
                app(EncuentroController::class)->crearCodigo($reqCodigo, $encuentro->id);

                $equipos = Equipo::where('encuentro_id', $encuentro->id)->get();
                if ($equipos->isEmpty()) {
                    $this->error("  ✗ No se crearon equipos para el encuentro #{$encuentro->id}.");
                    continue;
                }

                foreach ($equipos as $equipo) {
                    $this->completarWorkflowEquipo($equipo, $proyecto, $microreto, $usuario);
                    $this->line("  ✓ Equipo #{$equipo->id} ({$equipo->nombre}) completado, diagnóstico final generado.");
                }

                // Ningún endpoint hace esta transición todavía (ver cabecera de la clase) —
                // es el cierre manual que haría el docente en la UI tras revisar el diagnóstico.
                $proyecto->update(['estado' => 'completado']);

                $completados++;
                $this->info("  ✓ Proyecto #{$proyecto->id} → estado final: completado");
            } catch (\Illuminate\Validation\ValidationException $e) {
                $this->error("  ✗ Validación falló para el proyecto #{$proyecto->id}: " . json_encode($e->errors()));
            } catch (\Throwable $e) {
                $this->error("  ✗ Error procesando el proyecto #{$proyecto->id}: " . $e->getMessage());
            }
        }

        $enCurso = max(0, (int) $this->option('en-curso'));
        $dejadosEnCurso = 0;
        if ($enCurso > 0) {
            $restantes = $elegibles->whereNotIn('id', $seleccionados->pluck('id'))->values()->take($enCurso);

            $this->newLine();
            $this->info("Proyectos a dejar EN CURSO (sin completar), fecha curso actual: {$restantes->count()}.");
            $this->newLine();

            foreach ($restantes as $proyecto) {
                $microreto = $proyecto->microreto;
                $numEquipos = random_int(1, 2);
                [$alumnados, $totalAlumnos] = $this->generarAlumnado($numEquipos);

                $this->line("Proyecto #{$proyecto->id} \"{$proyecto->titulo}\" → {$numEquipos} equipo(s) EN CURSO, {$totalAlumnos} alumnos ficticios");

                if (!$commit) {
                    continue;
                }

                try {
                    $reqEncuentro = $this->peticion(StoreEncuentroRequest::class, [
                        'microproyecto_id' => $proyecto->id,
                        'fecha'            => $this->fechaEnCurso(),
                        'curso'            => (string) ($proyecto->curso ?: '2'),
                        'grupo'            => $this->generarGrupo($proyecto->curso),
                        'num_alumnos'      => $totalAlumnos,
                        'num_equipos'      => $numEquipos,
                        'alumnados'        => $alumnados,
                    ], $usuario);
                    app(EncuentroController::class)->store($reqEncuentro);

                    $encuentro = Encuentro::where('microproyecto_id', $proyecto->id)->latest('id')->first();
                    if (!$encuentro) {
                        $this->error("  ✗ No se pudo crear el encuentro del proyecto #{$proyecto->id}.");
                        continue;
                    }

                    $reqCodigo = $this->peticion(Request::class, [], $usuario);
                    app(EncuentroController::class)->crearCodigo($reqCodigo, $encuentro->id);

                    $equipos = Equipo::where('encuentro_id', $encuentro->id)->get();
                    if ($equipos->isEmpty()) {
                        $this->error("  ✗ No se crearon equipos para el encuentro #{$encuentro->id}.");
                        continue;
                    }

                    foreach ($equipos as $equipo) {
                        $this->avanzarWorkflowParcial($equipo, $proyecto, $microreto);
                        $this->line("  ✓ Equipo #{$equipo->id} ({$equipo->nombre}) en curso (fase {$equipo->fresh()->fase_actual}, sin completar).");
                    }

                    // A propósito: el proyecto se queda en su estado actual ('validado'), nunca
                    // se marca 'completado' — es lo que pide este modo.
                    $dejadosEnCurso++;
                } catch (\Illuminate\Validation\ValidationException $e) {
                    $this->error("  ✗ Validación falló para el proyecto #{$proyecto->id}: " . json_encode($e->errors()));
                } catch (\Throwable $e) {
                    $this->error("  ✗ Error procesando el proyecto #{$proyecto->id}: " . $e->getMessage());
                }
            }
        }

        $this->newLine();
        if ($commit) {
            $this->info("Proyectos llevados a completado en esta ejecución: {$completados}/{$aProcesar}.");
            if ($enCurso > 0) {
                $this->info("Proyectos dejados en curso (sin completar) en esta ejecución: {$dejadosEnCurso}.");
            }
        } else {
            $this->comment('Dry-run: ninguna llamada a IA, ninguna escritura. Relanza con --commit para generar de verdad.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: array<int, array{nombre:string,equipo_num:int,rol:string}>, 1: int}
     */
    private function generarAlumnado(int $numEquipos): array
    {
        $alumnados = [];
        $total     = 0;
        $usados    = []; // evita repetir el mismo nombre completo dos veces en el mismo encuentro

        for ($eq = 1; $eq <= $numEquipos; $eq++) {
            $n = random_int(3, 5);
            for ($m = 1; $m <= $n; $m++) {
                $rol = $m === 1 ? 'portavoz' : self::ROLES_ROTACION[($m - 2) % count(self::ROLES_ROTACION)];
                $alumnados[] = [
                    'nombre'     => $this->nombreAleatorio($usados),
                    'equipo_num' => $eq,
                    'rol'        => $rol,
                ];
                $total++;
            }
        }

        return [$alumnados, $total];
    }

    /**
     * Nombre y apellido inventados (nunca "Alumno/a N.M") — es lo que ve el docente en
     * `EquipoMiembro.nombre` (columna `encrypted`) y de lo que deriva el alias público
     * ("Nombre Animal", ver AliasGenerator) que ve el propio alumnado en su workspace.
     */
    private function nombreAleatorio(array &$usados): string
    {
        for ($intento = 0; $intento < 10; $intento++) {
            $nombre = self::NOMBRES[array_rand(self::NOMBRES)] . ' ' . self::APELLIDOS[array_rand(self::APELLIDOS)];
            if (!in_array($nombre, $usados, true)) {
                $usados[] = $nombre;
                return $nombre;
            }
        }
        return $nombre; // 10 intentos sin nombre libre (grupo muy grande) — se acepta la repetición
    }

    /**
     * Grupo = clase real del encuentro (ej. "2ºB"), no un código derivado del proyecto —
     * así lo interpreta el frontend (ver Encuentro.grupo en MisGruposDetalle.vue).
     */
    private function generarGrupo(?string $curso): string
    {
        $numero = match ($curso) {
            '1' => '1',
            '2' => '2',
            default => (string) random_int(1, 2), // 'ambos_cursos' u otro valor: uno de los dos al azar
        };
        $letra = self::LETRAS_GRUPO[array_rand(self::LETRAS_GRUPO)];

        return "{$numero}º{$letra}";
    }

    /**
     * Fecha del encuentro para proyectos que SÍ se llevan a completado: repartida en
     * días distintos del curso 2025-2026 (diciembre de 2025 a junio de 2026), no
     * agrupada en las últimas semanas antes de hoy como antes.
     */
    private function fechaCursoAnterior(): string
    {
        $inicio = \Carbon\Carbon::create(2025, 12, 1);
        $fin    = \Carbon\Carbon::create(2026, 6, 30);

        return $inicio->copy()->addDays(random_int(0, $inicio->diffInDays($fin)))->toDateString();
    }

    /**
     * Fecha del encuentro para proyectos que se dejan EN CURSO (sin completar): dentro del
     * curso actual (septiembre, octubre o diciembre de 2026) — pedido explícito del usuario,
     * representa trabajo que todavía está en marcha, a diferencia de los ya cerrados del
     * curso anterior (fechaCursoAnterior()).
     */
    private function fechaEnCurso(): string
    {
        $meses = [[2026, 9, 30], [2026, 10, 31], [2026, 12, 31]];
        [$anio, $mes, $diasEnMes] = $meses[array_rand($meses)];

        return \Carbon\Carbon::create($anio, $mes, random_int(1, $diasEnMes))->toDateString();
    }

    /**
     * Avanza un equipo "en curso" solo parcialmente por el workflow — F0 siempre completa
     * (el equipo ya se ha formado), y con probabilidad variable también F1 y/o el inicio de
     * F2 (guardado pero SIN completar, para que se note que está a medias). Nunca llega a
     * evaluación ni diagnóstico final, y el proyecto se queda tal cual estaba ('validado'),
     * nunca 'completado' — es justo lo que representa "en curso".
     */
    private function avanzarWorkflowParcial(Equipo $equipo, Microproyecto $proyecto, ?\App\Models\Microreto $microreto): void
    {
        $token = $equipo->token;

        $this->guardarFase($token, 0, ['contrato_firmado' => true]);
        app(EquipoPublicoController::class)->confirmarNombres($token);
        $this->completarFase($token, 0);

        // 0 = recién empezado (solo F0), 1 = ya en análisis del reto, 2 = ya iniciando diseño
        // de la solución (pero sin completar esa fase) — variado para que no todos los
        // proyectos "en curso" estén exactamente en el mismo punto.
        $progreso = random_int(0, 2);
        if ($progreso < 1) {
            return;
        }

        $sintesis = $this->generarSintesisReto($microreto, $proyecto->empresa);
        $hallazgos = collect($microreto?->dificultades ?? [])
            ->take(3)->map(fn ($d) => "Hemos observado que: {$d}")->values()->all();
        if (empty($hallazgos)) {
            $hallazgos = ['La empresa no dispone hoy de una forma sistemática de abordar este problema.'];
        }
        $this->guardarFase($token, 1, [
            'sintesis'   => $sintesis,
            'reto_frase' => $microreto?->pregunta_reto ?? 'Cómo podríamos ayudar a la empresa a resolver su reto.',
            'hallazgos'  => $hallazgos,
        ]);
        $this->completarFase($token, 1);

        if ($progreso < 2) {
            return;
        }

        // F2 se GUARDA pero no se completa a propósito: es la fase en la que "está" ahora
        // mismo el equipo, con trabajo empezado y sin terminar.
        $tipoPrototipo = collect($microreto?->prototipos ?? [])->first() ?? 'Prototipo funcional sencillo';
        $necesidad     = collect($microreto?->que_necesitan ?? [])->first() ?? 'una solución sencilla y aplicable';
        $this->guardarFase($token, 2, [
            'propuesta'             => "El equipo propone desarrollar {$necesidad}, adaptado a las limitaciones de la empresa.",
            'explicacion_propuesta' => 'Responde directamente a la pregunta del reto y es viable con los recursos descritos por la empresa.',
            'tipo_prototipo'        => $tipoPrototipo,
            'prototipo_url'         => '',
            'iteracion'             => 1,
        ]);
    }

    /**
     * Modo de corrección (--corregir-sintesis): regenera con IA la síntesis F1 de equipos
     * YA EXISTENTES, sin tocar el resto del workflow (fases, evaluación, diagnóstico...).
     * Pensado para corregir contenido antiguo generado antes de tener generarSintesisReto()
     * (la respuesta genérica "... (dato de demo) ...").
     */
    private function corregirSintesisExistente(bool $commit, int $limite): int
    {
        $fasesQuery = EquipoFase::where('numero_fase', 1)
            ->whereHas('equipo.microproyecto', fn ($q) => $q->where('es_demo', true)->where('centro_id', self::CENTRO_ID))
            ->with('equipo.microproyecto.microreto', 'equipo.microproyecto.empresa')
            ->orderBy('id');
        if ($limite > 0) {
            $fasesQuery->limit($limite);
        }
        $fases = $fasesQuery->get();

        if ($fases->isEmpty()) {
            $this->info('No hay fases F1 de equipos simulados que corregir.');
            return self::SUCCESS;
        }

        $this->info("Fases F1 a regenerar: {$fases->count()}.");
        $this->newLine();

        $corregidas = 0;
        foreach ($fases as $fase) {
            $proyecto  = $fase->equipo->microproyecto;
            $microreto = $proyecto->microreto;

            $this->line("Equipo #{$fase->equipo_id} (proyecto \"{$proyecto->titulo}\")");

            if (!$commit) {
                continue;
            }

            $sintesis = $this->generarSintesisReto($microreto, $proyecto->empresa);
            $datos = $fase->datos ?? [];
            $datos['sintesis'] = $sintesis;
            $fase->update(['datos' => $datos]);
            $corregidas++;
        }

        $this->newLine();
        if ($commit) {
            $this->info("Fases F1 regeneradas en esta ejecución: {$corregidas}.");
        } else {
            $this->comment('Dry-run: ninguna llamada a IA, ninguna escritura. Relanza con --commit para corregir de verdad.');
        }

        return self::SUCCESS;
    }

    /**
     * Modo de corrección (--corregir-reflexiones): regenera la reflexión grupal (con las
     * preguntas reales, antes eran solo 2 genéricas hardcodeadas) Y añade las reflexiones
     * individuales (una por miembro) que faltaban por completo en equipos YA EXISTENTES.
     * Borra las reflexiones previas del equipo antes de recrearlas (idempotente en
     * relanzamientos, nunca duplica).
     */
    private function corregirReflexionesExistentes(bool $commit, int $limite): int
    {
        $equiposQuery = Equipo::whereHas('microproyecto', fn ($q) => $q->where('es_demo', true)->where('centro_id', self::CENTRO_ID))
            ->with('microproyecto.microreto', 'microproyecto.empresa', 'miembros')
            ->orderBy('id');
        if ($limite > 0) {
            $equiposQuery->limit($limite);
        }
        $equipos = $equiposQuery->get();

        if ($equipos->isEmpty()) {
            $this->info('No hay equipos simulados que corregir.');
            return self::SUCCESS;
        }

        $this->info("Equipos a regenerar reflexiones: {$equipos->count()}.");
        $this->newLine();

        $corregidos = 0;
        foreach ($equipos as $equipo) {
            $proyecto  = $equipo->microproyecto;
            $microreto = $proyecto->microreto;
            $miembros  = $equipo->miembros;

            $this->line("Equipo #{$equipo->id} (proyecto \"{$proyecto->titulo}\", {$miembros->count()} miembros)");

            if (!$commit) {
                continue;
            }

            $reflexionFinal = $this->generarReflexionFinal($microreto, $proyecto->empresa, $miembros->count());

            EquipoReflexion::where('equipo_id', $equipo->id)->delete();

            $reqGrupal = $this->peticion(StoreEquipoReflexionRequest::class, [
                'tipo'       => 'grupal',
                'respuestas' => collect(self::PREGUNTAS_GRUPAL)
                    ->map(fn ($p, $i) => ['pregunta' => $p, 'respuesta' => $reflexionFinal['grupal'][$i]])
                    ->values()->all(),
            ]);
            app(EquipoPublicoController::class)->storeReflexion($reqGrupal, $equipo->token);

            foreach ($miembros->values() as $i => $miembro) {
                $respuestasIndiv = collect(self::PREGUNTAS_INDIVIDUAL)
                    ->map(fn ($p, $j) => ['pregunta' => $p, 'respuesta' => $reflexionFinal['individual'][$i][$j] ?? $reflexionFinal['individual'][0][$j]])
                    ->values()->all();

                $reqIndiv = $this->peticion(StoreEquipoReflexionRequest::class, [
                    'tipo'         => 'individual',
                    'autor_nombre' => $miembro->nombre,
                    'respuestas'   => $respuestasIndiv,
                ]);
                app(EquipoPublicoController::class)->storeReflexion($reqIndiv, $equipo->token);
            }

            $corregidos++;
        }

        $this->newLine();
        if ($commit) {
            $this->info("Equipos con reflexiones regeneradas en esta ejecución: {$corregidos}.");
        } else {
            $this->comment('Dry-run: ninguna llamada a IA, ninguna escritura. Relanza con --commit para corregir de verdad.');
        }

        return self::SUCCESS;
    }

    /**
     * Responde con IA real las 5 preguntas guía de F1 (EquipoPublicoController::PREGUNTAS_F0)
     * en primera persona de equipo ("hemos detectado…"), a partir del contexto REAL del reto
     * y la empresa — nunca la respuesta genérica de antes ("dato de demo"), que se veía tal
     * cual en el workspace público del alumnado (EquipoWorkspace.vue), sin login.
     *
     * @return array<int, array{pregunta:string, respuesta:string}>
     */
    /**
     * Genera con IA, en una sola llamada por equipo, la reflexión final de cierre completa:
     * la grupal (una sola voz de equipo) y una individual por cada miembro (en primera
     * persona del singular, con matices distintos entre miembros) — mismas preguntas exactas
     * que EquipoWorkspace.vue. Nunca menciona que el proyecto es ficticio/de demo.
     *
     * @return array{grupal: array<int,string>, individual: array<int,array<int,string>>}
     */
    private function generarReflexionFinal(?\App\Models\Microreto $microreto, ?Empresa $empresa, int $numMiembros): array
    {
        $numMiembros = max(1, $numMiembros);

        $fallbackGrupal = array_fill(0, count(self::PREGUNTAS_GRUPAL),
            'El equipo ha valorado su forma de trabajar a lo largo del proyecto y ha extraído conclusiones útiles para futuros retos.');
        $fallbackIndividual = array_fill(0, $numMiembros, array_fill(0, count(self::PREGUNTAS_INDIVIDUAL),
            'He aprendido a aplicar lo trabajado en clase a un caso real y a organizarme mejor dentro del equipo.'));
        $fallback = ['grupal' => $fallbackGrupal, 'individual' => $fallbackIndividual];

        if (!$microreto) {
            return $fallback;
        }

        $contexto = "PREGUNTA DEL RETO: {$microreto->pregunta_reto}\n"
            . "QUIÉN ES LA EMPRESA: {$microreto->quien_es}\n"
            . "DIFICULTADES AFRONTADAS: " . collect($microreto->dificultades ?? [])->implode('; ') . "\n"
            . "ENTREGABLE/PROTOTIPO TRABAJADO: " . collect($microreto->prototipos ?? [])->implode('; ') . "\n";

        $preguntasGrupalNum = collect(self::PREGUNTAS_GRUPAL)->map(fn ($p, $i) => ($i + 1) . ". {$p}")->implode("\n");
        $preguntasIndivNum  = collect(self::PREGUNTAS_INDIVIDUAL)->map(fn ($p, $i) => ($i + 1) . ". {$p}")->implode("\n");

        $systemPrompt = 'Eres el equipo de alumnado de Formación Profesional que acaba de terminar su proyecto de '
            . 'Aprendizaje Basado en Retos con una empresa colaboradora, y escribe la reflexión final de cierre del '
            . 'proyecto. Te basas únicamente en el contexto real del reto que se te da. NUNCA mencionas que la '
            . 'información es ficticia, de ejemplo, de prueba o generada automáticamente: para ti este proyecto es '
            . 'completamente real.';

        $userPrompt = "Contexto real del proyecto ya finalizado:\n{$contexto}\n"
            . "1) REFLEXIÓN GRUPAL — una sola voz de equipo ('hemos...', 'como equipo...'), responde exactamente "
            . "estas " . count(self::PREGUNTAS_GRUPAL) . " preguntas en este orden:\n{$preguntasGrupalNum}\n\n"
            . "2) REFLEXIONES INDIVIDUALES — hay {$numMiembros} miembros en el equipo. Cada uno responde EN PRIMERA "
            . "PERSONA DEL SINGULAR ('yo...', 'a mí...', 'lo que más me costó...') estas mismas " . count(self::PREGUNTAS_INDIVIDUAL)
            . " preguntas, con una perspectiva y énfasis distintos entre miembros (nunca la misma respuesta para "
            . "todos):\n{$preguntasIndivNum}\n\n"
            . "Devuelve SOLO este JSON: {\"grupal\": [\"...\"], \"individual\": [[\"...\"], [\"...\"]]} — \"grupal\" "
            . "con exactamente " . count(self::PREGUNTAS_GRUPAL) . " textos, e \"individual\" con exactamente {$numMiembros} "
            . "arrays (uno por miembro), cada uno con exactamente " . count(self::PREGUNTAS_INDIVIDUAL) . " textos, en el mismo orden que las preguntas.";

        for ($intento = 1; $intento <= self::MAX_INTENTOS_SINTESIS; $intento++) {
            try {
                $response = Http::withToken(config('services.openai.key'))
                    ->timeout(60)
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model'           => 'gpt-4o',
                        'messages'        => [
                            ['role' => 'system', 'content' => $systemPrompt],
                            ['role' => 'user',   'content' => $userPrompt],
                        ],
                        'response_format' => ['type' => 'json_object'],
                        'temperature'     => 0.8,
                    ]);

                if ($response->successful()) {
                    $data = json_decode($response->json('choices.0.message.content'), true);
                    $grupal = $data['grupal'] ?? null;
                    $individual = $data['individual'] ?? null;

                    if (
                        is_array($grupal) && count($grupal) === count(self::PREGUNTAS_GRUPAL)
                        && is_array($individual) && count($individual) === $numMiembros
                        && collect($individual)->every(fn ($r) => is_array($r) && count($r) === count(self::PREGUNTAS_INDIVIDUAL))
                    ) {
                        return ['grupal' => array_values($grupal), 'individual' => array_values($individual)];
                    }
                }
            } catch (\Throwable $e) {
                // sigue al reintento
            }

            if ($intento < self::MAX_INTENTOS_SINTESIS) {
                sleep(self::ESPERA_SINTESIS);
            }
        }

        $this->warn("  … no se pudo generar la reflexión final con IA para el reto #{$microreto->id}, usando respuesta de reserva (sin mención a demo).");

        return $fallback;
    }

    private function generarSintesisReto(?\App\Models\Microreto $microreto, ?Empresa $empresa): array
    {
        $preguntas = EquipoPublicoController::PREGUNTAS_F0;

        // Fallback sin IA (reto no disponible, o los 3 intentos de la IA fallan): genérico
        // pero construido a partir de datos reales del propio reto, nunca menciona demo.
        $fallback = function () use ($preguntas, $microreto) {
            $base = $microreto?->pregunta_reto
                ? "En relación con \"{$microreto->pregunta_reto}\", el equipo ha valorado la información disponible de la empresa para dar una respuesta ajustada a su situación real."
                : 'El equipo ha valorado la información disponible de la empresa para dar una respuesta ajustada a su situación real.';

            return collect($preguntas)->map(fn ($p) => ['pregunta' => $p, 'respuesta' => $base])->values()->all();
        };

        if (!$microreto) {
            return $fallback();
        }

        $contexto = "PREGUNTA DEL RETO: {$microreto->pregunta_reto}\n"
            . "QUIÉN ES LA EMPRESA: {$microreto->quien_es}\n"
            . "DÍA A DÍA / DÓNDE FALLA EL PROCESO: {$microreto->dia_a_dia}\n"
            . "DIFICULTADES: " . collect($microreto->dificultades ?? [])->implode('; ') . "\n"
            . "LIMITACIONES: " . collect($microreto->limitaciones ?? [])->implode('; ') . "\n"
            . "QUÉ NECESITAN: " . collect($microreto->que_necesitan ?? [])->implode('; ') . "\n";

        if ($empresa) {
            $contexto .= "OPERATIVA DE LA EMPRESA: {$empresa->dia_a_normal}\n"
                . "ÁREA CON MÁS FRICCIÓN: {$empresa->friccion_area}\n"
                . "DETALLE DEL PROBLEMA: {$empresa->friccion_problema}\n"
                . "RESTRICCIONES: {$empresa->restricciones}\n"
                . "CONSECUENCIAS ACTUALES: {$empresa->consecuencias}\n";
        }

        $preguntasNumeradas = collect($preguntas)->map(fn ($p, $i) => ($i + 1) . ". {$p}")->implode("\n");

        $systemPrompt = 'Eres un equipo de alumnado de Formación Profesional que acaba de analizar el reto de una '
            . 'empresa colaboradora, en la fase de "Análisis del reto" de un proyecto de Aprendizaje Basado en Retos. '
            . 'Respondes en primera persona del plural ("hemos detectado", "creemos que", "nuestro equipo considera"), '
            . 'con naturalidad de estudiante, nunca como si fueras la empresa ni un consultor externo. Te basas '
            . 'ÚNICAMENTE en la información real del reto que se te da — nunca inventas datos que la contradigan, y '
            . 'NUNCA mencionas que la información es ficticia, de ejemplo, de prueba o generada automáticamente: '
            . 'para ti este reto es completamente real.';

        $userPrompt = "Contexto real del reto:\n{$contexto}\nResponde exactamente estas " . count($preguntas)
            . " preguntas, en el mismo orden, con 2-3 frases cada una:\n{$preguntasNumeradas}\n\n"
            . 'Devuelve SOLO este JSON: {"respuestas": ["...", "...", "...", "...", "..."]}';

        for ($intento = 1; $intento <= self::MAX_INTENTOS_SINTESIS; $intento++) {
            try {
                $response = Http::withToken(config('services.openai.key'))
                    ->timeout(60)
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model'           => 'gpt-4o',
                        'messages'        => [
                            ['role' => 'system', 'content' => $systemPrompt],
                            ['role' => 'user',   'content' => $userPrompt],
                        ],
                        'response_format' => ['type' => 'json_object'],
                        'temperature'     => 0.8,
                    ]);

                if ($response->successful()) {
                    $data = json_decode($response->json('choices.0.message.content'), true);
                    $respuestas = $data['respuestas'] ?? null;
                    if (is_array($respuestas) && count($respuestas) === count($preguntas)) {
                        return collect($preguntas)
                            ->map(fn ($p, $i) => ['pregunta' => $p, 'respuesta' => (string) $respuestas[$i]])
                            ->values()->all();
                    }
                }
            } catch (\Throwable $e) {
                // sigue al reintento
            }

            if ($intento < self::MAX_INTENTOS_SINTESIS) {
                sleep(self::ESPERA_SINTESIS);
            }
        }

        $this->warn("  … no se pudo generar la síntesis F1 con IA para el reto #{$microreto->id}, usando respuesta de reserva (sin mención a demo).");

        return $fallback();
    }

    private function completarWorkflowEquipo(Equipo $equipo, Microproyecto $proyecto, ?\App\Models\Microreto $microreto, User $usuario): void
    {
        $token = $equipo->token;

        // F0 — Inicio del equipo: confirmar contrato + nombres (los miembros ya existen,
        // dados de alta por crearCodigo(), no hace falta reenviarlos en 'datos').
        $this->guardarFase($token, 0, ['contrato_firmado' => true]);
        app(EquipoPublicoController::class)->confirmarNombres($token);
        $this->completarFase($token, 0);

        // F1 — Análisis del reto: síntesis de las preguntas guía + hallazgos.
        $sintesis = $this->generarSintesisReto($microreto, $proyecto->empresa);
        $hallazgos = collect($microreto?->dificultades ?? [])
            ->take(3)
            ->map(fn ($d) => "Hemos observado que: {$d}")
            ->values()->all();
        if (empty($hallazgos)) {
            $hallazgos = ['La empresa no dispone hoy de una forma sistemática de abordar este problema.'];
        }
        $this->guardarFase($token, 1, [
            'sintesis'   => $sintesis,
            'reto_frase' => $microreto?->pregunta_reto ?? 'Cómo podríamos ayudar a la empresa a resolver su reto.',
            'hallazgos'  => $hallazgos,
        ]);
        $this->completarFase($token, 1); // auto-siembra las tareas genéricas de F2

        // F2 — Diseño de la solución: propuesta + prototipo elegido.
        $tipoPrototipo = collect($microreto?->prototipos ?? [])->first() ?? 'Prototipo funcional sencillo';
        $necesidad     = collect($microreto?->que_necesitan ?? [])->first() ?? 'una solución sencilla y aplicable';
        $this->guardarFase($token, 2, [
            'propuesta'                    => "El equipo propone desarrollar {$necesidad}, adaptado a las limitaciones de la empresa.",
            'explicacion_propuesta'        => 'Responde directamente a la pregunta del reto y es viable con los recursos descritos por la empresa.',
            'solucion_final'               => "Versión final: {$tipoPrototipo}.",
            'justificacion_solucion_final' => 'Se ha simplificado la propuesta inicial tras valorar el tiempo disponible del equipo.',
            'tipo_prototipo'               => $tipoPrototipo,
            'prototipo_url'                => '',
            'iteracion'                    => 1,
        ]);
        $this->completarFase($token, 2);

        // Un par de tareas auto-sembradas se marcan como realizadas, para que el tablero
        // de tareas no quede con todo en 'pendiente' en un proyecto ya completado.
        EquipoTarea::where('equipo_id', $equipo->id)->orderBy('orden')->limit(2)->update(['estado' => 'realizado']);

        // F3 — Entrega de la solución.
        $this->guardarFase($token, 3, [
            'descripcion_entregable' => "Entrega final: {$tipoPrototipo}, documentada y lista para presentar a la empresa.",
            'url_entregable'         => '',
        ]);
        $this->completarFase($token, 3);

        // F4 — Presentación + reflexión grupal de cierre.
        $this->guardarFase($token, 4, [
            'expone_clase' => true,
            'organizacion' => ['modo_intervencion' => 'portavoz', 'tipo_exposicion' => ['PowerPoint / resumen visual']],
        ]);
        $this->completarFase($token, 4);

        $miembros = $equipo->miembros;
        $reflexionFinal = $this->generarReflexionFinal($microreto, $proyecto->empresa, $miembros->count());

        $reqGrupal = $this->peticion(StoreEquipoReflexionRequest::class, [
            'tipo'       => 'grupal',
            'respuestas' => collect(self::PREGUNTAS_GRUPAL)
                ->map(fn ($p, $i) => ['pregunta' => $p, 'respuesta' => $reflexionFinal['grupal'][$i]])
                ->values()->all(),
        ]);
        app(EquipoPublicoController::class)->storeReflexion($reqGrupal, $token);

        foreach ($miembros->values() as $i => $miembro) {
            $respuestasIndiv = collect(self::PREGUNTAS_INDIVIDUAL)
                ->map(fn ($p, $j) => ['pregunta' => $p, 'respuesta' => $reflexionFinal['individual'][$i][$j] ?? $reflexionFinal['individual'][0][$j]])
                ->values()->all();

            $reqIndiv = $this->peticion(StoreEquipoReflexionRequest::class, [
                'tipo'         => 'individual',
                'autor_nombre' => $miembro->nombre,
                'respuestas'   => $respuestasIndiv,
            ]);
            app(EquipoPublicoController::class)->storeReflexion($reqIndiv, $token);
        }

        // Evaluación curricular del docente sobre el RA/CE ya asociado al proyecto (heredado
        // del reto) — EquipoGestionController exige usuario autenticado + editablesPara().
        $ras = collect($proyecto->evaluacion_oficial ?? [])
            ->filter(fn ($item) => !empty($item['ra']))
            ->map(fn ($item) => [
                'ra'            => $item['ra'],
                'nivel'         => 'alcanzado',
                'observaciones' => '',
            ])
            ->values()->all();
        if (empty($ras)) {
            $ras = [['ra' => 'Resultado de aprendizaje trabajado en el proyecto.', 'nivel' => 'alcanzado', 'observaciones' => '']];
        }
        // Texto neutro sin ninguna mención a que el dato es de demo — esto se ve en el
        // workspace público del alumnado (EquipoWorkspace.vue), sin login.
        $observaciones = self::OBSERVACIONES_DOCENTE[$equipo->id % count(self::OBSERVACIONES_DOCENTE)];
        $reqEvaluar = $this->peticion(EvaluarEquipoRequest::class, [
            'evaluacion' => [
                'ras'           => $ras,
                'nota_opcional' => 8,
            ],
            'nota_docente'          => 8,
            'observaciones_docente' => $observaciones,
        ], $usuario);
        app(EquipoGestionController::class)->evaluar($reqEvaluar, $equipo->id);

        // Diagnóstico final IA — misma lógica que en producción (DiagnosticoFinalService +
        // OpenAI), requiere las 5 fases completadas (ya lo están).
        $reqDiagnostico = $this->peticion(Request::class, [], $usuario);
        app(EquipoGestionController::class)->diagnosticoFinal($reqDiagnostico, $equipo->id);
    }

    private function guardarFase(string $token, int $numeroFase, array $datos): void
    {
        $request = $this->peticion(GuardarFaseEquipoRequest::class, ['datos' => $datos]);
        app(EquipoPublicoController::class)->guardarFase($request, $token, $numeroFase);
    }

    private function completarFase(string $token, int $numeroFase): void
    {
        $request = $this->peticion(Request::class, []);
        app(EquipoPublicoController::class)->completarFase($request, $token, $numeroFase);
    }
}
