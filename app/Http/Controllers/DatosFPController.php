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

class DatosFPController extends Controller
{
    // ==========================================
    // FLUJO B2B: Empresas
    // ==========================================

    /**
     * GET /empresas
     * Devuelve las empresas disponibles. Docentes y admin de centro: solo las de su centro.
     */
    public function getEmpresas(Request $request)
    {
        // Sin 'centroEducativo' en with(): la columna centro_educativo (string) y la relación
        // tienen el mismo nombre JSON → la relación machaca el string y rompe el frontend.
        // El string legacy es suficiente para el selector de centros.
        $user  = $request->user();
        $query = Empresa::orderBy('nombre_comercial');

        if ($user && ($user->isDocente() || $user->isAdmin())) {
            $query->delCentroDe($user);
        }

        return response()->json($query->get());
    }

    /**
     * GET /empresas/{id}/familias
     * Devuelve las familias profesionales vinculadas a una empresa.
     */
    public function getFamiliasPorEmpresa($idEmpresa)
    {
        $empresa = Empresa::with('familias')->find($idEmpresa);

        if (!$empresa) {
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
     * PUT /centros/{id}
     * Actualiza el nombre y los ciclos de un centro educativo.
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
            'ciclosIds'   => 'required|array|min:1',
            'ciclosIds.*' => 'integer|exists:ciclos_formativos,id',
            'img'         => 'sometimes|nullable|string|max:2048',
        ]);

        $nombreAnterior = $centro->nombre;
        $update = ['nombre' => $request->nombre];
        if ($request->has('img')) $update['img'] = $request->img;
        $centro->update($update);

        // Si cambió el nombre, actualizar el campo legacy en empresas y en centro_ciclo
        if ($nombreAnterior !== $request->nombre) {
            Empresa::where('centro_educativo', $nombreAnterior)
                ->update(['centro_educativo' => $request->nombre]);
            DB::table('centro_ciclo')
                ->where('centro_id', $id)
                ->update(['centro_educativo' => $request->nombre]);
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
            'centro'  => ['id' => $centro->id, 'nombre' => $centro->nombre],
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
            'ciclosIds'   => 'required|array|min:1',
            'ciclosIds.*' => 'integer|exists:ciclos_formativos,id',
            'img'         => 'sometimes|nullable|string|max:2048',
        ]);

        $centro = CentroEducativo::create($request->only(['nombre', 'img']));

        $rows = collect($request->ciclosIds)->map(fn($cicloId) => [
            'centro_id'        => $centro->id,
            'centro_educativo' => $centro->nombre,
            'ciclo_id'         => $cicloId,
        ])->all();

        DB::table('centro_ciclo')->insertOrIgnore($rows);

        return response()->json([
            'message' => 'Centro educativo creado correctamente',
            'centro'  => ['id' => $centro->id, 'nombre' => $centro->nombre],
        ], 201);
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

    public function guardarEmpresa(Request $request)
    {
        $auth = $request->user();
        $estadosPermitidos = ['Pendiente de llamar', 'Llamado - Información obtenida', 'Llamado - Negativa', 'Llamado - Llamar más tarde', 'En colaboración activa', 'Descartada'];

        $request->validate([
            'nombreComercial'  => 'required|string|max:255',
            'razonSocial'      => 'nullable|string|max:255',
            'cif'              => 'nullable|string|max:20',
            'sector'           => 'nullable|string|max:255',
            'tamano'           => 'nullable|string|max:50',
            'web'              => 'nullable|string|max:255',
            'actividad'        => 'nullable|string|max:500',
            'personaContacto'  => 'nullable|string|max:255',
            'telefono'         => 'nullable|string|max:20',
            'emailGeneral'     => 'nullable|email|max:255',
            'direccion'        => 'nullable|string|max:255',
            'municipio'        => 'nullable|string|max:255',
            'provincia'        => 'nullable|string|max:255',
            'codigoPostal'     => 'nullable|string|max:10',
            'diaANormal'       => 'nullable|string|max:1000',
            'friccionArea'     => 'nullable|string|max:400',
            'friccionProblema' => 'nullable|string|max:1200',
            'restricciones'    => 'nullable|string|max:600',
            'loQueNoQuieren'   => 'nullable|string|max:500',
            'consecuencias'    => 'nullable',
            'esSimulada'       => 'nullable|boolean',
            'estadoContacto'   => 'nullable|string|in:' . implode(',', $estadosPermitidos),
        ]);

        $consecuenciasTexto = is_array($request->consecuencias)
            ? implode(', ', $request->consecuencias)
            : $request->consecuencias;

        // Admin de centro: la empresa que crea es siempre de su propio centro, nunca
        // el que mande el cliente — solo superadmin puede elegir centro libremente.
        $centroEducativoNombre = $request->centroEducativo;
        if ($auth->isAdmin()) {
            $centroEducativoNombre = $auth->centroEducativo?->nombre;
        }

        // Resolvemos o creamos el centro educativo
        $centroId = null;
        if ($centroEducativoNombre) {
            $centro   = CentroEducativo::firstOrCreate(['nombre' => $centroEducativoNombre]);
            $centroId = $centro->id;
        }

        $empresa = Empresa::create([
            'nombre_comercial'  => $request->nombreComercial,
            'razon_social'      => $request->razonSocial,
            'cif'               => $request->cif,
            'centro_educativo'  => $centroEducativoNombre, // legacy
            'centro_id'         => $centroId,
            'sector'            => $request->sector,
            'tamano'            => $request->tamano,
            'web'               => $request->web,
            'actividad'         => $request->actividad,
            'persona_contacto'  => $request->personaContacto,
            'telefono'          => $request->telefono,
            'email_general'     => $request->emailGeneral,
            'direccion'         => $request->direccion,
            'municipio'         => $request->municipio,
            'provincia'         => $request->provincia,
            'codigo_postal'     => $request->codigoPostal,
            'dia_a_normal'      => $request->diaANormal,
            'friccion_area'     => $request->friccionArea,
            'friccion_problema' => $request->friccionProblema,
            'consecuencias'     => $consecuenciasTexto,
            'restricciones'     => $request->restricciones,
            'lo_que_no_quieren' => $request->loQueNoQuieren,
            'es_simulada'       => $request->boolean('esSimulada', false),
            'estado_contacto'   => $request->estadoContacto,
        ]);

        // Guardamos la familia usando FK si existe, además del string legacy
        if ($request->filled('familia')) {
            $familiaModel = Familia::where('nombre', $request->familia)->first();
            DB::table('empresa_familia')->insert([
                'empresa_id' => $empresa->id,
                'familia'    => $request->familia,                    // legacy
                'familia_id' => $familiaModel?->id,                   // normalizado
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Vinculamos los ciclos al nuevo centro si el usuario los seleccionó
        if ($centroId && $request->filled('ciclosIds') && is_array($request->ciclosIds)) {
            $rows = collect($request->ciclosIds)->map(fn($cicloId) => [
                'centro_id'         => $centroId,
                'centro_educativo'  => $centroEducativoNombre,  // legacy
                'ciclo_id'          => $cicloId,
            ])->all();
            DB::table('centro_ciclo')->insertOrIgnore($rows);
        }

        return response()->json([
            'message' => 'Empresa creada correctamente',
            'empresa' => $empresa,
        ]);
    }

    /**
     * GET /empresas/dashboard
     * Devuelve todas las empresas con sus familias para el dashboard de base de datos.
     */
    public function getDashboardEmpresas()
    {
        $empresas = Empresa::with('familias')
            ->orderBy('nombre_comercial')
            ->get();

        return response()->json($empresas->map(function ($empresa) {
            $data = $empresa->toArray();
            $data['familias_nombres'] = $empresa->familias->pluck('nombre')->toArray();
            return $data;
        }));
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

    public function actualizarEmpresa(Request $request, $id)
    {
        $empresa = Empresa::find($id);

        if (!$empresa) {
            return response()->json(['error' => 'Empresa no encontrada'], 404);
        }

        $auth = $request->user();
        if ($auth->isAdmin() && !$empresa->perteneceAlCentroDe($auth)) {
            return response()->json(['error' => 'No autorizado: esta empresa no pertenece a tu centro educativo.'], 403);
        }

        $estadosPermitidos = ['Pendiente de llamar', 'Llamado - Información obtenida', 'Llamado - Negativa', 'Llamado - Llamar más tarde', 'En colaboración activa', 'Descartada'];

        $request->validate([
            'nombreComercial'  => 'required|string|max:255',
            'razonSocial'      => 'nullable|string|max:255',
            'cif'              => 'nullable|string|max:20',
            'sector'           => 'nullable|string|max:255',
            'tamano'           => 'nullable|string|max:50',
            'web'              => 'nullable|string|max:255',
            'actividad'        => 'nullable|string|max:500',
            'personaContacto'  => 'nullable|string|max:255',
            'telefono'         => 'nullable|string|max:20',
            'emailGeneral'     => 'nullable|email|max:255',
            'direccion'        => 'nullable|string|max:255',
            'municipio'        => 'nullable|string|max:255',
            'provincia'        => 'nullable|string|max:255',
            'codigoPostal'     => 'nullable|string|max:10',
            'diaANormal'       => 'nullable|string|max:1000',
            'friccionArea'     => 'nullable|string|max:400',
            'friccionProblema' => 'nullable|string|max:1200',
            'restricciones'    => 'nullable|string|max:600',
            'loQueNoQuieren'   => 'nullable|string|max:500',
            'consecuencias'    => 'nullable',
            'esSimulada'       => 'nullable|boolean',
            'estadoContacto'   => 'nullable|string|in:' . implode(',', $estadosPermitidos),
        ]);

        $consecuenciasTexto = is_array($request->consecuencias)
            ? implode(', ', $request->consecuencias)
            : $request->consecuencias;

        // Admin de centro: no puede mover la empresa a otro centro ni desvincularla del
        // suyo — el campo se ignora y se mantiene el centro actual (que ya se comprobó
        // arriba que es el propio). Solo superadmin puede reasignar libremente.
        $centroEducativoNombre = $request->centroEducativo;
        if ($auth->isAdmin()) {
            $centroEducativoNombre = $empresa->centro_educativo;
        }

        // Resolvemos o creamos el centro educativo
        $centroId = $empresa->centro_id;
        if ($auth->isAdmin()) {
            // Se mantiene el centro_id actual (ya validado arriba).
        } elseif ($request->filled('centroEducativo')) {
            $centro   = CentroEducativo::firstOrCreate(['nombre' => $request->centroEducativo]);
            $centroId = $centro->id;
        } elseif ($request->has('centroEducativo') && !$request->centroEducativo) {
            $centroId = null;
        }

        $updateData = [
            'nombre_comercial'  => $request->nombreComercial,
            'razon_social'      => $request->razonSocial,
            'cif'               => $request->cif,
            'centro_educativo'  => $centroEducativoNombre, // legacy
            'centro_id'         => $centroId,
            'sector'            => $request->sector,
            'tamano'            => $request->tamano,
            'web'               => $request->web,
            'actividad'         => $request->actividad,
            'persona_contacto'  => $request->personaContacto,
            'telefono'          => $request->telefono,
            'email_general'     => $request->emailGeneral,
            'direccion'         => $request->direccion,
            'municipio'         => $request->municipio,
            'provincia'         => $request->provincia,
            'codigo_postal'     => $request->codigoPostal,
            'dia_a_normal'      => $request->diaANormal,
            'friccion_area'     => $request->friccionArea,
            'friccion_problema' => $request->friccionProblema,
            'consecuencias'     => $consecuenciasTexto,
            'restricciones'     => $request->restricciones,
            'lo_que_no_quieren' => $request->loQueNoQuieren,
        ];

        if ($request->has('esSimulada')) {
            $updateData['es_simulada'] = $request->boolean('esSimulada');
        }
        if ($request->has('estadoContacto')) {
            $updateData['estado_contacto'] = $request->estadoContacto;
        }

        $empresa->update($updateData);

        if ($request->filled('familia')) {
            $familiaModel = Familia::where('nombre', $request->familia)->first();
            DB::table('empresa_familia')->updateOrInsert(
                ['empresa_id' => $id],
                [
                    'familia'    => $request->familia,      // legacy
                    'familia_id' => $familiaModel?->id,     // normalizado
                    'updated_at' => now(),
                ]
            );
        }

        // Vinculamos los ciclos al nuevo centro si el usuario los seleccionó
        if ($centroId && $request->filled('ciclosIds') && is_array($request->ciclosIds)) {
            $rows = collect($request->ciclosIds)->map(fn($cicloId) => [
                'centro_id'         => $centroId,
                'centro_educativo'  => $centroEducativoNombre,  // legacy
                'ciclo_id'          => $cicloId,
            ])->all();
            DB::table('centro_ciclo')->insertOrIgnore($rows);
        }

        return response()->json([
            'message' => 'Empresa actualizada correctamente',
            'empresa' => $empresa,
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