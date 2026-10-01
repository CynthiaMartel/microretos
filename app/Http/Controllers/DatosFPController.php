<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\CicloFormativo;
use App\Models\Modulo;
use App\Models\ResultadoAprendizaje;
use App\Models\Empresa;
use App\Models\Familia;
use App\Models\CentroEducativo;
use App\Models\Encuentro;
use App\Models\Microproyecto;
use App\Http\Requests\AsociarEmpresasCentroRequest;
use App\Http\Requests\StoreEmpresaRequest;
use App\Http\Requests\UpdateEmpresaRequest;
use App\Http\Resources\EmpresaResource;
use App\Http\Resources\EmpresaDashboardResource;

class DatosFPController extends Controller
{
    // Techo de seguridad para GET /empresas: el frontend filtra y pagina en cliente,
    // pero sin límite un catálogo que crece podría agotar memoria en una sola petición.
    private const MAX_EMPRESAS_LISTADO = 2000;

    // ==========================================
    // FLUJO B2B: Empresas
    // ==========================================

    /**
     * GET /empresas
     * Devuelve las empresas disponibles. Docentes y admin de centro: solo las de su centro.
     * Rol empresa: solo la suya. Superadmin: todas.
     */
    public function getEmpresas(Request $request)
    {
        // Sin 'centroEducativo' en with(): la columna centro_educativo (string) y la relación
        // tienen el mismo nombre JSON → la relación machaca el string y rompe el frontend.
        // El string legacy es suficiente para el selector de centros.
        $user  = $request->user();
        $query = Empresa::with('familias:id,nombre')->orderBy('nombre_comercial');

        if ($user->isDocente() || $user->isAdmin()) {
            $query->delCentroDe($user);
        } elseif ($user->isEmpresa()) {
            $query->whereKey($user->empresa_id);
        } elseif (!$user->isSuperAdmin()) {
            $query->whereRaw('0 = 1');
        }

        return response()->json(
            EmpresaResource::collection($query->take(self::MAX_EMPRESAS_LISTADO)->get())
        );
    }

    /**
     * GET /empresas/{id}/familias
     * Devuelve las familias profesionales vinculadas a una empresa.
     */
    public function getFamiliasPorEmpresa(Request $request, $idEmpresa)
    {
        $empresa = Empresa::with('familias')->find($idEmpresa);
        $user    = $request->user();

        // Mismo alcance que GET /empresas. Una empresa fuera de alcance responde 404
        // (no 403) para no revelar qué ids existen en otros centros.
        $enAlcance = $empresa && (
            $user->isSuperAdmin()
            || (($user->isDocente() || $user->isAdmin()) && $empresa->perteneceAlCentroDe($user))
            || ($user->isEmpresa() && (int) $user->empresa_id === (int) $empresa->id)
        );

        if (!$enAlcance) {
            return response()->json([], 404);
        }

        // Devolvemos siempre strings (nombres) para mantener compatibilidad con el frontend.
        // El frontend usa el nombre directamente en la URL /familias/{nombre}/ciclos.
        if ($empresa->familias->isNotEmpty()) {
            return response()->json($empresa->familias->pluck('nombre'));
        }

        // Fallback legacy: columna 'familia' string todavía sin normalizar
        return response()->json(
            DB::table('empresa_familia')
                ->where('empresa_id', $idEmpresa)
                ->whereNotNull('familia')
                ->pluck('familia')
        );
    }

    // ==========================================
    // ENDPOINTS ACADÉMICOS
    // ==========================================

    public function getFamilias()
    {
        $familias = Familia::select('id', 'nombre', 'imagen_url')
            ->withCount('ciclos')
            ->orderBy('nombre')
            ->get()
            ->map(function ($familia) {
                if ($familia->imagen_url) {
                    $familia->imagen_url = asset($familia->imagen_url);
                }
                return $familia;
            });

        return response()->json($familias);
    }

    /**
     * GET /familias/{familia}/ciclos?centro=...
     */
    public function getCiclos(Request $request, $familia)
    {
        $nombreFamilia = urldecode($familia);

        $familiaModel = Familia::where('nombre', $nombreFamilia)->first();
        $query = $familiaModel
            ? CicloFormativo::where('familia_id', $familiaModel->id)
            : CicloFormativo::where('familia', $nombreFamilia); // fallback defensivo

        if ($request->filled('centro')) {
            $centro = $request->centro;

            // Primero buscamos por centro_id normalizado
            $centroModel = CentroEducativo::where('nombre', $centro)->first();

            if ($centroModel) {
                $ciclosDelCentro = DB::table('centro_ciclo')
                    ->where('centro_id', $centroModel->id)
                    ->pluck('ciclo_id');
            } else {
                // Fallback legacy: columna 'centro_educativo' string
                $ciclosDelCentro = DB::table('centro_ciclo')
                    ->where('centro_educativo', $centro)
                    ->pluck('ciclo_id');
            }

            // Solo filtramos si el centro ya tiene ciclos vinculados.
            // Si está vacío (centro recién creado o sin configurar), devolvemos
            // todos los ciclos de la familia para que la empresa sea usable.
            if ($ciclosDelCentro->isNotEmpty()) {
                $query->whereIn('id', $ciclosDelCentro);
            }
        }

        return response()->json($query->orderBy('nombre')->get());
    }

    public function getModulos($idCiclo)
    {
        return response()->json(
            Modulo::where('idcicloformativo', $idCiclo)
                ->orderBy('curso')
                ->orderBy('nombre')
                ->get()
        );
    }

    public function getRaCe($idModulo)
    {
        $ras = ResultadoAprendizaje::with('criteriosEvaluacion')
            ->where('idmodulo', $idModulo)
            ->get();

        return response()->json([
            'ra' => $ras->values()->map(function ($ra, $idx) {
                return [
                    'id'          => $ra->id,
                    'orden'       => $idx + 1,
                    'descripcion' => $ra->ra,
                    'criterios'   => $ra->criteriosEvaluacion->values()->map(function ($ce, $ci) {
                        return [
                            'id'          => $ce->id,
                            'orden'       => $ci + 1,
                            'descripcion' => $ce->ce,
                        ];
                    }),
                ];
            }),
        ]);
    }

    // ==========================================
    // CENTROS EDUCATIVOS
    // ==========================================

    /**
     * GET /centros
     * Devuelve todos los centros con sus ciclos agrupados por familia.
     */
    public function getCentros()
    {
        $centros = CentroEducativo::orderBy('nombre')->get();

        return response()->json($centros->map(function ($centro) {
            $ciclos = DB::table('centro_ciclo')
                ->where('centro_id', $centro->id)
                ->join('ciclos_formativos', 'ciclos_formativos.id', '=', 'centro_ciclo.ciclo_id')
                ->leftJoin('familias', 'familias.id', '=', 'ciclos_formativos.familia_id')
                ->select(
                    'ciclos_formativos.id',
                    'ciclos_formativos.nombre',
                    'familias.id as familia_id',
                    'familias.nombre as familia_nombre'
                )
                ->distinct()
                ->orderBy('familias.nombre')
                ->orderBy('ciclos_formativos.nombre')
                ->get();

            return [
                'id'     => $centro->id,
                'nombre' => $centro->nombre,
                'img'    => $centro->img,
                'ciclos' => $ciclos,
            ];
        }));
    }

    /**
     * DELETE /centros/{id}
     * Mueve el centro a la papelera (soft delete). Sus empresas y ciclos no se tocan
     * para que una restauración posterior recupere todo el estado original.
     */
    public function eliminarCentro($id)
    {
        $centro = CentroEducativo::find($id);

        if (!$centro) {
            return response()->json(['error' => 'Centro no encontrado'], 404);
        }

        $centro->delete();

        return response()->json(['message' => 'Centro movido a la papelera']);
    }

    /**
     * POST /centros/{id}/impacto-cambio
     * Previsualiza, SIN GUARDAR NADA, cuántos registros denormalizados (que copiaron el
     * nombre/municipio del centro en su momento — ver actualizarCentro()) se actualizarían
     * si se confirma el cambio propuesto. El frontend llama a esto antes de actualizarCentro()
     * para poder mostrar el aviso "esto va a afectar a X empresas, Y encuentros, Z proyectos".
     */
    public function impactoCambioCentro(Request $request, $id)
    {
        $centro = CentroEducativo::find($id);

        if (!$centro) {
            return response()->json(['error' => 'Centro no encontrado'], 404);
        }

        $request->validate([
            'nombre'    => 'required|string|max:255',
            'municipio' => 'nullable|string|max:255',
        ]);

        $cambiaNombre    = $request->nombre !== $centro->nombre;
        $cambiaMunicipio = ($request->municipio ?: null) !== ($centro->municipio ?: null);

        if (!$cambiaNombre && !$cambiaMunicipio) {
            return response()->json(['requiere_confirmacion' => false]);
        }

        return response()->json([
            'requiere_confirmacion' => true,
            'cambia_nombre'         => $cambiaNombre,
            'cambia_municipio'      => $cambiaMunicipio,
            'afectados' => [
                'empresas'   => Empresa::where('centro_id', $id)->count(),
                'encuentros' => Encuentro::where('centro_educativo_id', $id)->count(),
                'proyectos'  => Microproyecto::where('centro_id', $id)->count(),
            ],
        ]);
    }

    /**
     * PUT /centros/{id}
     * Actualiza el nombre, municipio y los ciclos de un centro educativo, y propaga el
     * cambio de nombre/municipio a los registros que guardaron una copia en su momento
     * (Empresa.centro_educativo, Encuentro.centro_educativo, Microproyecto.datos_centro) —
     * antes de esto solo se propagaba a Empresa/centro_ciclo, dejando Encuentro y
     * Microproyecto desactualizados si se renombraba un centro.
     * Los ciclos anteriores se reemplazan completamente por los nuevos.
     */
    public function actualizarCentro(Request $request, $id)
    {
        $centro = CentroEducativo::find($id);

        if (!$centro) {
            return response()->json(['error' => 'Centro no encontrado'], 404);
        }

        $request->validate([
            'nombre'      => 'required|string|max:255|unique:centro_educativo,nombre,' . $id,
            'municipio'   => 'nullable|string|max:255',
            'ciclosIds'   => 'required|array|min:1',
            'ciclosIds.*' => 'integer|exists:ciclos_formativos,id',
            'img'         => 'sometimes|nullable|string|max:2048',
        ]);

        $nombreAnterior    = $centro->nombre;
        $municipioAnterior = $centro->municipio;
        $update = ['nombre' => $request->nombre, 'municipio' => $request->municipio];
        if ($request->has('img')) $update['img'] = $request->img;
        $centro->update($update);

        $cambioNombre    = $nombreAnterior !== $request->nombre;
        $cambioMunicipio = $municipioAnterior !== $request->municipio;

        if ($cambioNombre) {
            // Por centro_id (no por el nombre viejo): así se corrigen también las
            // empresas cuyo string legacy ya estuviera desincronizado del real.
            Empresa::where('centro_id', $id)->update(['centro_educativo' => $centro->nombre]);
            DB::table('centro_ciclo')->where('centro_id', $id)->update(['centro_educativo' => $centro->nombre]);
            Encuentro::where('centro_educativo_id', $id)->update(['centro_educativo' => $centro->nombre]);
        }

        if ($cambioNombre || $cambioMunicipio) {
            // datos_centro es JSON (snapshot tomado por el wizard) — no se puede
            // actualizar con un solo UPDATE, hay que leer y rescribir cada fila
            // conservando docente_nombre/docente_email tal cual estaban.
            Microproyecto::where('centro_id', $id)->whereNotNull('datos_centro')
                ->chunkById(200, function ($proyectos) use ($centro) {
                    foreach ($proyectos as $proyecto) {
                        $datos = $proyecto->datos_centro;
                        $datos['nombre']    = $centro->nombre;
                        $datos['municipio'] = $centro->municipio;
                        $proyecto->update(['datos_centro' => $datos]);
                    }
                });
        }

        // Reemplazar todos los ciclos del centro
        DB::table('centro_ciclo')->where('centro_id', $id)->delete();

        $rows = collect($request->ciclosIds)->map(fn($cicloId) => [
            'centro_id'        => $id,
            'centro_educativo' => $centro->nombre,
            'ciclo_id'         => $cicloId,
        ])->all();

        DB::table('centro_ciclo')->insertOrIgnore($rows);

        return response()->json([
            'message' => 'Centro actualizado correctamente',
            'centro'  => ['id' => $centro->id, 'nombre' => $centro->nombre, 'municipio' => $centro->municipio, 'img' => $centro->img],
        ]);
    }

    /**
     * POST /centros
     * Crea un centro educativo con sus ciclos asociados.
     */
    public function guardarCentro(Request $request)
    {
        $request->validate([
            'nombre'      => 'required|string|max:255|unique:centro_educativo,nombre',
            'municipio'   => 'nullable|string|max:255',
            'ciclosIds'   => 'required|array|min:1',
            'ciclosIds.*' => 'integer|exists:ciclos_formativos,id',
            'img'         => 'sometimes|nullable|string|max:2048',
        ]);

        $centro = CentroEducativo::create($request->only(['nombre', 'municipio', 'img']));

        $rows = collect($request->ciclosIds)->map(fn($cicloId) => [
            'centro_id'        => $centro->id,
            'centro_educativo' => $centro->nombre,
            'ciclo_id'         => $cicloId,
        ])->all();

        DB::table('centro_ciclo')->insertOrIgnore($rows);

        return response()->json([
            'message' => 'Centro educativo creado correctamente',
            'centro'  => ['id' => $centro->id, 'nombre' => $centro->nombre, 'municipio' => $centro->municipio, 'img' => $centro->img],
        ], 201);
    }

    /**
     * POST /centros/{id}/empresas/asociar
     * Asocia empresas ya existentes (sin centro o de otro centro) a este centro,
     * reasignando su centro_id. No crea empresas nuevas.
     */
    public function asociarEmpresas(AsociarEmpresasCentroRequest $request, $id)
    {
        $centro = CentroEducativo::find($id);

        if (!$centro) {
            return response()->json(['error' => 'Centro no encontrado'], 404);
        }

        Empresa::whereIn('id', $request->validated('empresa_ids'))
            ->update([
                'centro_id'        => $centro->id,
                'centro_educativo' => $centro->nombre, // legacy
            ]);

        return response()->json(['message' => 'Empresas asociadas correctamente']);
    }

    // ==========================================
    // CRUD FAMILIAS PROFESIONALES
    // ==========================================

    public function storeFamilia(Request $request)
    {
        $request->validate([
            'nombre'     => 'required|string|max:255|unique:familias,nombre',
            'imagen_url' => 'nullable|string|max:255',
        ]);

        $familia = Familia::create([
            'nombre'     => $request->nombre,
            'imagen_url' => $request->imagen_url,
        ]);

        return response()->json(['message' => 'Familia creada', 'familia' => $familia], 201);
    }

    public function updateFamilia(Request $request, $id)
    {
        $familia = Familia::find($id);
        if (!$familia) return response()->json(['error' => 'Familia no encontrada'], 404);

        $request->validate([
            'nombre'     => 'required|string|max:255|unique:familias,nombre,' . $id,
            'imagen_url' => 'nullable|string|max:255',
        ]);

        $nombreAnterior = $familia->nombre;
        $familia->update([
            'nombre'     => $request->nombre,
            'imagen_url' => $request->imagen_url,
        ]);

        if ($nombreAnterior !== $request->nombre) {
            CicloFormativo::where('familia', $nombreAnterior)->update(['familia' => $request->nombre]);
            DB::table('empresa_familia')->where('familia', $nombreAnterior)->update(['familia' => $request->nombre]);
        }

        return response()->json(['message' => 'Familia actualizada', 'familia' => $familia]);
    }

    public function destroyFamilia($id)
    {
        $familia = Familia::find($id);
        if (!$familia) return response()->json(['error' => 'Familia no encontrada'], 404);

        $numCiclos   = CicloFormativo::where('familia_id', $id)->count();
        $numEmpresas = DB::table('empresa_familia')->where('familia_id', $id)->count();

        if ($numCiclos > 0 || $numEmpresas > 0) {
            return response()->json([
                'error'        => 'No se puede eliminar: tiene ciclos o empresas asociados.',
                'num_ciclos'   => $numCiclos,
                'num_empresas' => $numEmpresas,
            ], 422);
        }

        $familia->delete();
        return response()->json(['message' => 'Familia movida a la papelera']);
    }

    // ==========================================
    // CRUD CICLOS FORMATIVOS
    // ==========================================

    public function storeCiclo(Request $request)
    {
        $request->validate([
            'nombre'      => 'required|string|max:255',
            'familia_id'  => 'required|integer|exists:familias,id',
            'grado'       => 'nullable|string|max:100',
            'siglasGrado' => 'nullable|string|max:3',
        ]);

        $familia = Familia::find($request->familia_id);

        $ciclo = CicloFormativo::create([
            'idCiclo'     => 0,
            'nombre'      => $request->nombre,
            'familia'     => $familia->nombre,
            'familia_id'  => $request->familia_id,
            'grado'       => $request->grado,
            'siglasGrado' => $request->siglasGrado ?? 'FP',
        ]);

        return response()->json(['message' => 'Ciclo creado', 'ciclo' => $ciclo], 201);
    }

    public function updateCiclo(Request $request, $id)
    {
        $ciclo = CicloFormativo::find($id);
        if (!$ciclo) return response()->json(['error' => 'Ciclo no encontrado'], 404);

        $request->validate([
            'nombre'      => 'required|string|max:255',
            'familia_id'  => 'required|integer|exists:familias,id',
            'grado'       => 'nullable|string|max:100',
            'siglasGrado' => 'nullable|string|max:3',
        ]);

        $familia = Familia::find($request->familia_id);

        $ciclo->nombre      = $request->nombre;
        $ciclo->familia_id  = $request->familia_id;
        $ciclo->familia     = $familia->nombre;
        $ciclo->grado       = $request->grado;
        if ($request->filled('siglasGrado')) {
            $ciclo->siglasGrado = $request->siglasGrado;
        }
        $ciclo->save();

        return response()->json(['message' => 'Ciclo actualizado', 'ciclo' => $ciclo]);
    }

    public function destroyCiclo($id)
    {
        $ciclo = CicloFormativo::find($id);
        if (!$ciclo) return response()->json(['error' => 'Ciclo no encontrado'], 404);

        $numCentros = DB::table('centro_ciclo')->where('ciclo_id', $id)->count();

        if ($numCentros > 0) {
            return response()->json([
                'error'       => 'No se puede eliminar: el ciclo está asignado a ' . $numCentros . ' centro(s).',
                'num_centros' => $numCentros,
            ], 422);
        }

        $ciclo->delete();
        return response()->json(['message' => 'Ciclo movido a la papelera']);
    }

    // ==========================================
    // GUARDADO Y ACTUALIZACIÓN DE EMPRESAS
    // ==========================================

    // Campos del formulario (camelCase, contrato del frontend) → columnas de empresas.
    // centroEducativo, familia y ciclosIds no están aquí: tienen tratamiento propio.
    private const CAMPOS_EMPRESA = [
        'nombreComercial'  => 'nombre_comercial',
        'razonSocial'      => 'razon_social',
        'cif'              => 'cif',
        'sector'           => 'sector',
        'tamano'           => 'tamano',
        'web'              => 'web',
        'actividad'        => 'actividad',
        'personaContacto'  => 'persona_contacto',
        'telefono'         => 'telefono',
        'emailGeneral'     => 'email_general',
        'direccion'        => 'direccion',
        'municipio'        => 'municipio',
        'provincia'        => 'provincia',
        'codigoPostal'     => 'codigo_postal',
        'diaANormal'       => 'dia_a_normal',
        'friccionArea'     => 'friccion_area',
        'friccionProblema' => 'friccion_problema',
        'consecuencias'    => 'consecuencias',
        'restricciones'    => 'restricciones',
        'loQueNoQuieren'   => 'lo_que_no_quieren',
        'esSimulada'       => 'es_simulada',
        'estadoContacto'   => 'estado_contacto',
    ];

    /** Traduce solo los campos validados que llegaron en la petición a columnas de BD. */
    private function columnasEmpresa(array $validated): array
    {
        $columnas = [];
        foreach (self::CAMPOS_EMPRESA as $campo => $columna) {
            if (array_key_exists($campo, $validated)) {
                $columnas[$columna] = $validated[$campo];
            }
        }
        return $columnas;
    }

    /** Vincula la familia a la empresa: FK normalizada + string legacy. */
    private function guardarFamiliaEmpresa(int $empresaId, string $familia, bool $reemplazar): void
    {
        $familiaId = Familia::where('nombre', $familia)->value('id');
        $valores = [
            'familia'    => $familia,     // legacy
            'familia_id' => $familiaId,   // normalizado
            'updated_at' => now(),
        ];

        if ($reemplazar) {
            DB::table('empresa_familia')->updateOrInsert(['empresa_id' => $empresaId], $valores);
        } else {
            DB::table('empresa_familia')->insert($valores + ['empresa_id' => $empresaId, 'created_at' => now()]);
        }
    }

    /** Vincula los ciclos seleccionados al centro de la empresa. */
    private function vincularCiclosCentro(?int $centroId, ?string $centroNombre, array $ciclosIds): void
    {
        if (!$centroId || empty($ciclosIds)) {
            return;
        }

        $rows = collect($ciclosIds)->map(fn ($cicloId) => [
            'centro_id'        => $centroId,
            'centro_educativo' => $centroNombre,  // legacy
            'ciclo_id'         => $cicloId,
        ])->all();
        DB::table('centro_ciclo')->insertOrIgnore($rows);
    }

    public function guardarEmpresa(StoreEmpresaRequest $request)
    {
        $auth      = $request->user();
        $validated = $request->validated();

        // Admin de centro: la empresa que crea es siempre de su propio centro, nunca
        // el que mande el cliente — solo superadmin puede elegir centro libremente.
        $centroEducativoNombre = $auth->isAdmin()
            ? $auth->centroEducativo?->nombre
            : ($validated['centroEducativo'] ?? null);

        // Resolvemos o creamos el centro educativo
        $centroId = null;
        if ($centroEducativoNombre) {
            $centro   = CentroEducativo::firstOrCreate(['nombre' => $centroEducativoNombre]);
            $centroId = $centro->id;
        }

        $empresa = Empresa::create($this->columnasEmpresa($validated) + [
            'centro_educativo' => $centroEducativoNombre, // legacy
            'centro_id'        => $centroId,
            'es_simulada'      => (bool) ($validated['esSimulada'] ?? false),
        ]);

        if (!empty($validated['familia'])) {
            $this->guardarFamiliaEmpresa($empresa->id, $validated['familia'], reemplazar: false);
        }

        $this->vincularCiclosCentro($centroId, $centroEducativoNombre, $validated['ciclosIds'] ?? []);

        return response()->json([
            'message' => 'Empresa creada correctamente',
            // Con familias cargadas: el generador inserta/reemplaza esta empresa en su
            // lista local y el filtro por familia la necesita sin recargar.
            'empresa' => new EmpresaResource($empresa->load('familias:id,nombre')),
        ]);
    }

    /**
     * GET /empresas/dashboard
     * Devuelve todas las empresas con sus familias para el dashboard de base de datos.
     */
    public function getDashboardEmpresas()
    {
        $empresas = Empresa::with('familias:id,nombre')
            ->orderBy('nombre_comercial')
            ->take(self::MAX_EMPRESAS_LISTADO)
            ->get();

        return response()->json(EmpresaDashboardResource::collection($empresas));
    }

    /**
     * DELETE /empresas/{id}
     * Mueve la empresa a la papelera (soft delete). Las relaciones pivot y los microretos
     * asociados no se tocan para que una restauración recupere todo el estado original.
     */
    public function eliminarEmpresa($id)
    {
        $empresa = Empresa::find($id);

        if (!$empresa) {
            return response()->json(['error' => 'Empresa no encontrada'], 404);
        }

        $empresa->delete();

        return response()->json(['message' => 'Empresa movida a la papelera']);
    }

    public function actualizarEmpresa(UpdateEmpresaRequest $request, $id)
    {
        $empresa = Empresa::find($id);

        if (!$empresa) {
            return response()->json(['error' => 'Empresa no encontrada'], 404);
        }

        $auth = $request->user();
        if ($auth->isAdmin() && !$empresa->perteneceAlCentroDe($auth)) {
            return response()->json(['error' => 'No autorizado: esta empresa no pertenece a tu centro educativo.'], 403);
        }

        $validated = $request->validated();

        // Solo se actualizan los campos que llegan: el Generador de Retos envía únicamente
        // el diagnóstico y no debe vaciar CIF, contacto, dirección, etc.
        $updateData = $this->columnasEmpresa($validated);

        // Admin de centro: no puede mover la empresa a otro centro ni desvincularla del
        // suyo — el campo se ignora y se mantiene el centro actual (que ya se comprobó
        // arriba que es el propio). Solo superadmin puede reasignar libremente.
        $centroEducativoNombre = $empresa->centro_educativo;
        $centroId              = $empresa->centro_id;
        if (!$auth->isAdmin() && array_key_exists('centroEducativo', $validated)) {
            $centroEducativoNombre = $validated['centroEducativo'];
            $centroId = $centroEducativoNombre
                ? CentroEducativo::firstOrCreate(['nombre' => $centroEducativoNombre])->id
                : null;
            $updateData['centro_educativo'] = $centroEducativoNombre; // legacy
            $updateData['centro_id']        = $centroId;
        }

        $empresa->update($updateData);

        if (!empty($validated['familia'])) {
            $this->guardarFamiliaEmpresa($empresa->id, $validated['familia'], reemplazar: true);
        }

        $this->vincularCiclosCentro($centroId, $centroEducativoNombre, $validated['ciclosIds'] ?? []);

        return response()->json([
            'message' => 'Empresa actualizada correctamente',
            // Con familias cargadas: el generador inserta/reemplaza esta empresa en su
            // lista local y el filtro por familia la necesita sin recargar.
            'empresa' => new EmpresaResource($empresa->load('familias:id,nombre')),
        ]);
    }

    public function actualizarEstadoEmpresa(Request $request, $id)
    {
        $empresa = Empresa::find($id);
        if (!$empresa) {
            return response()->json(['error' => 'Empresa no encontrada'], 404);
        }

        if ($request->user()->isAdmin() && !$empresa->perteneceAlCentroDe($request->user())) {
            return response()->json(['error' => 'No autorizado: esta empresa no pertenece a tu centro educativo.'], 403);
        }

        $estadosPermitidos = ['Pendiente de llamar', 'Llamado - Información obtenida', 'Llamado - Negativa', 'Llamado - Llamar más tarde', 'En colaboración activa', 'Descartada'];

        $request->validate([
            'estadoContacto' => 'nullable|string|in:' . implode(',', $estadosPermitidos),
        ]);

        $empresa->update(['estado_contacto' => $request->estadoContacto ?: null]);

        return response()->json(['message' => 'Estado actualizado', 'empresa' => $empresa]);
    }
}