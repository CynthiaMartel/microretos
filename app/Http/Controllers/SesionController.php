<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSesionLoteRequest;
use App\Http\Requests\StoreSesionRequest;
use App\Models\Equipo;
use App\Models\Microreto;
use App\Models\Sesion;
use Illuminate\Http\Request;

class SesionController extends Controller
{
    public function index(\Illuminate\Http\Request $request)
    {
        $user  = $request->user();
        $query = Sesion::with([
            'docente:id,name',
            'microreto.empresa.centroEducativo',
            'microreto.empresa.familias',
            'microproyectos:id,sesion_id,uuid,titulo',
        ])->orderBy('created_at', 'desc');

        if ($user?->isDocente()) {
            // Docente: solo sus propias sesiones
            $query->where('user_id', $user->id);
        } elseif ($user?->isAdmin() && $user->centro_educativo_id) {
            // Admin: todas las sesiones de su centro educativo
            $nombreCentro = $user->centroEducativo?->nombre;
            if ($nombreCentro) {
                $query->where('centro_educativo', $nombreCentro);
            }
        }
        // Superadmin: sin filtro, ve todo

        return $query->get()->map(function ($sesion) {
            $data = $sesion->toArray();
            $data['microproyecto_uuid'] = $sesion->microproyectos->sortByDesc('id')->first()?->uuid;
            unset($data['microproyectos']);
            return $data;
        });
    }

    public function store(StoreSesionRequest $request)
    {
        $validated = $request->validated();
        $validated['microreto_id'] = $this->resolverMicroretoId($validated['microreto_id'] ?? null);

        $user = $request->user();
        $validated['user_id'] = $user->id;

        // Forzar el centro del usuario autenticado — el frontend no puede sobreescribirlo
        if ($user->centro_educativo_id && $user->centroEducativo) {
            $validated['centro_educativo'] = $user->centroEducativo->nombre;
        }

        return response()->json(Sesion::create($validated), 201);
    }

    public function storeLote(StoreSesionLoteRequest $request)
    {
        $userId = $request->user()->id;
        foreach ($request->validated()['sesiones'] as $s) {
            $s['microreto_id'] = $this->resolverMicroretoId($s['microreto_id'] ?? null);
            $s['user_id']      = $userId;
            Sesion::create($s);
        }

        return response()->noContent();
    }

    public function show($id)
    {
        return Sesion::findOrFail($id);
    }

    public function destroy($id)
    {
        $sesion = Sesion::findOrFail($id);
        $sesion->delete();
        return response()->noContent();
    }

    /**
     * POST /api/sesiones/{id}/crear-codigo
     * Crea equipos en el microproyecto publicado de la sesión y genera un codigo_clase.
     */
    public function crearCodigo(Request $request, $id)
    {
        $sesion = Sesion::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $proyecto = $sesion->microproyectos()
            ->whereIn('estado', ['propuesta', 'validado'])
            ->latest()
            ->first();

        if (!$proyecto) {
            return response()->json([
                'error' => 'Esta sesión no tiene ningún proyecto publicado. Crea el proyecto desde el Startup Day y márcalo como Propuesta primero.',
            ], 422);
        }

        $numEquipos = max(1, min(30, (int) ($sesion->num_equipos ?? 3)));
        $alumnados  = $sesion->alumnados ?? [];

        // Eliminar equipos existentes y recrear
        $proyecto->equipos()->delete();

        for ($n = 1; $n <= $numEquipos; $n++) {
            $equipo = Equipo::create([
                'microproyecto_id' => $proyecto->id,
                'sesion_id'        => $sesion->id,
                'nombre'           => "Equipo {$n}",
            ]);

            foreach ($alumnados as $a) {
                if ((int) ($a['equipo_num'] ?? 0) === $n && !empty($a['nombre'])) {
                    $equipo->miembros()->create([
                        'nombre' => $a['nombre'],
                        'rol'    => $a['rol'] ?? null,
                    ]);
                }
            }
        }

        $codigo = $this->generarCodigoClase();
        $sesion->update(['codigo_clase' => $codigo]);

        $equipos = $proyecto->equipos()->with('miembros')->get()->map(fn($e) => [
            'id'       => $e->id,
            'nombre'   => $e->nombre,
            'token'    => $e->token,
            'miembros' => $e->miembros->map(fn($m) => [
                'nombre' => $m->nombre,
                'rol'    => $m->rol,
            ]),
        ]);

        return response()->json(['codigo_clase' => $codigo, 'equipos' => $equipos], 201);
    }

    /**
     * GET /api/startup/sesiones/{id}/workspace
     * Dashboard docente de workspace: progreso de todos los equipos de una sesión.
     */
    public function workspace(Request $request, $id)
    {
        $sesion = Sesion::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $proyecto = $sesion->microproyectos()
            ->with([
                'equipos.miembros',
                'equipos.fases',
                'equipos.reflexiones',
            ])
            ->latest()
            ->first();

        $equipos = $proyecto?->equipos->map(function ($equipo) {
            $fasesCompletas = $equipo->fases->filter(fn($f) => $f->completada)->count();

            $fases = collect(range(0, 4))->map(function ($n) use ($equipo) {
                $fase = $equipo->fases->firstWhere('numero_fase', $n);
                return [
                    'numero_fase'           => $n,
                    'completada'            => $fase?->completada ?? false,
                    'validado_docente'      => $fase?->validado_docente ?? false,
                    'nota_docente'          => $fase?->nota_docente,
                    'observaciones_docente' => $fase?->observaciones_docente,
                    'datos'                 => $fase?->datos,
                    'fecha_completada'      => $fase?->fecha_completada,
                ];
            });

            return [
                'id'              => $equipo->id,
                'nombre'          => $equipo->nombre,
                'codigo_acceso'   => $equipo->codigo_acceso,
                'token'           => $equipo->token,
                'fase_actual'     => $equipo->fase_actual,
                'fases_completas' => $fasesCompletas,
                'miembros'        => $equipo->miembros->map(fn($m) => [
                    'id'     => $m->id,
                    'nombre' => $m->nombre,
                    'rol'    => $m->rol,
                ]),
                'fases' => $fases,
                'reflexiones' => $equipo->reflexiones->map(fn($r) => [
                    'id'           => $r->id,
                    'tipo'         => $r->tipo,
                    'autor_nombre' => $r->autor_nombre,
                    'respuestas'   => $r->respuestas,
                    'created_at'   => $r->created_at,
                ]),
            ];
        }) ?? collect();

        return response()->json([
            'sesion' => [
                'id'               => $sesion->id,
                'centro_educativo' => $sesion->centro_educativo,
                'ciclo_formativo'  => $sesion->ciclo_formativo,
                'curso'            => $sesion->curso,
                'grupo'            => $sesion->grupo,
                'fecha'            => $sesion->fecha,
                'num_alumnos'      => $sesion->num_alumnos,
            ],
            'proyecto' => $proyecto ? [
                'uuid'   => $proyecto->uuid,
                'titulo' => $proyecto->titulo,
                'estado' => $proyecto->estado,
            ] : null,
            'equipos' => $equipos,
        ]);
    }

    // Acepta tanto UUID string (migración desde localStorage) como ID entero
    private function resolverMicroretoId(mixed $value): ?int
    {
        if (!$value) return null;
        if (is_numeric($value)) return (int) $value;
        return Microreto::where('uuid', $value)->value('id');
    }

    private function generarCodigoClase(): string
    {
        $charset = 'ABCDEFGHJKLMNPQRTUVWXY';
        do {
            $letras  = $charset[random_int(0, strlen($charset) - 1)]
                     . $charset[random_int(0, strlen($charset) - 1)]
                     . $charset[random_int(0, strlen($charset) - 1)];
            $numeros = str_pad((string) random_int(100, 999), 3, '0', STR_PAD_LEFT);
            $codigo  = $letras . '-' . $numeros;
        } while (Sesion::where('codigo_clase', $codigo)->exists());
        return $codigo;
    }
}
