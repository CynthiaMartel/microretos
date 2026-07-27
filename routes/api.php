<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DatosFPController;
use App\Http\Controllers\MicroretoIAController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\MicroretoTokenController;
use App\Http\Controllers\MicroproyectoController;
use App\Http\Controllers\EncuentroController;
use App\Http\Controllers\EmpresaContactoController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\PapeleraController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\EquipoPublicoController;
use App\Http\Controllers\EquipoGestionController;

/*
|--------------------------------------------------------------------------
| API Routes - DuaLab Studio
|--------------------------------------------------------------------------
*/

// --- RUTAS PÚBLICAS (sin autenticación) ---

// Datos académicos: familias públicas para formularios de contacto u otros usos
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/familias', [DatosFPController::class, 'getFamilias']);
});

// Acceso público por token temporal (QR para alumnado — ficha del microreto)
Route::middleware('throttle:60,1')
     ->get('/public/microreto/{token}', [MicroretoTokenController::class, 'show']);

// ── Workspace de equipos (alumnado) — acceso público por código o token ────
// throttle más restrictivo para evitar fuerza bruta sobre códigos cortos
Route::middleware('throttle:20,1')->group(function () {
    // Código de clase → devuelve proyecto + lista de equipos para seleccionar
    Route::get('/clase/{codigo}',          [EquipoPublicoController::class, 'porCodigoClase']);
    // Código de equipo → acceso directo al workspace (backwards compat)
    Route::get('/equipo/unirse/{codigo}',  [EquipoPublicoController::class, 'unirse']);
});

Route::middleware('throttle:120,1')->group(function () {
    Route::get('/equipo/{token}',                          [EquipoPublicoController::class, 'show']);
    Route::get('/equipo/{token}/reto',                     [EquipoPublicoController::class, 'verReto']);
    Route::put('/equipo/{token}/fase/{fase}',              [EquipoPublicoController::class, 'guardarFase'])
        ->whereNumber('fase');
    Route::post('/equipo/{token}/fase/{fase}/completar',   [EquipoPublicoController::class, 'completarFase'])
        ->whereNumber('fase');
    Route::post('/equipo/{token}/tareas',                  [EquipoPublicoController::class, 'storeTarea']);
    Route::post('/equipo/{token}/fase/2/restablecer-tareas-genericas', [EquipoPublicoController::class, 'restablecerTareasGenericas']);
    Route::put('/equipo/{token}/tareas/{tarea}',           [EquipoPublicoController::class, 'updateTarea'])
        ->whereNumber('tarea');
    Route::delete('/equipo/{token}/tareas/{tarea}',        [EquipoPublicoController::class, 'destroyTarea'])
        ->whereNumber('tarea');
    Route::post('/equipo/{token}/reflexiones',             [EquipoPublicoController::class, 'storeReflexion']);
});

Route::middleware('throttle:30,1')->group(function () {
    Route::post('/equipo/{token}/prototipos',       [EquipoPublicoController::class, 'storePrototipo']);
    Route::delete('/equipo/{token}/prototipos/{id}', [EquipoPublicoController::class, 'destroyPrototipo'])
        ->whereNumber('id');
});

// Sugerencias IA del workspace de alumnado — throttle propio, más restrictivo
// (llamadas a OpenAI, no comparten cupo con el resto de endpoints públicos)
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/equipo/{token}/fase/1/sugerir-hallazgo', [EquipoPublicoController::class, 'sugerirHallazgo']);
    Route::post('/equipo/{token}/fase/2/sugerir-tareas',   [EquipoPublicoController::class, 'sugerirTareas']);
});

// Validación pública del microproyecto por parte de la empresa (acceso por token)
Route::middleware('throttle:30,1')->group(function () {
    Route::get('/startup/landing/{token}',          [MicroproyectoController::class, 'showByToken']);
    Route::post('/startup/landing/{token}/validar', [MicroproyectoController::class, 'validarEmpresa']);
});

// Datos académicos (ciclos, módulos) — throttle estándar
Route::middleware('throttle:120,1')->group(function () {
    Route::get('/familias/{familia}/ciclos',  [DatosFPController::class, 'getCiclos']);
    Route::get('/ciclos/{idCiclo}/modulos',   [DatosFPController::class, 'getModulos']);
    Route::get('/modulos/{idModulo}/ra-ce',   [DatosFPController::class, 'getRaCe']);
    Route::get('/centros',                    [DatosFPController::class, 'getCentros']);
});

Route::get('/demos', [DemoController::class, 'index']);
Route::get('/demos/{familia}/microretos', [DemoController::class, 'microretos'])
    ->where('familia', '[a-zA-ZÀ-ÿ0-9 ,.\-]{1,100}');
Route::get('/demos/{familia}', [DemoController::class, 'show'])
    ->where('familia', '[a-zA-ZÀ-ÿ0-9 ,.\-]{1,100}');

// Auth pública — throttle estricto para prevenir fuerza bruta
Route::middleware('throttle:5,1')
    ->post('/admin/login', [AdminAuthController::class, 'login']);


// --- RUTAS PROTEGIDAS (requieren token Sanctum) ---

Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/admin/logout',          [AdminAuthController::class, 'logout']);
    Route::post('/admin/refresh',         [AdminAuthController::class, 'refresh']);
    Route::middleware('throttle:5,1')
        ->post('/admin/verify-password', [AdminAuthController::class, 'verifyPassword']);

    // Perfil propio (accesible por todos los roles autenticados)
    Route::get('/perfil',   [AdminAuthController::class, 'getPerfil']);
    Route::patch('/perfil', [AdminAuthController::class, 'updatePerfil']);

    // Biblioteca de microretos (lectura — todos los roles autenticados)
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('/microretos',      [MicroretoIAController::class, 'index']);
        Route::get('/microretos/{id}', [MicroretoIAController::class, 'show']);
    });

    // ── Solo admin: centros, familias, ciclos, papelera ──────────────────────
    Route::middleware('admin')->group(function () {

        // Centros educativos
        Route::post('/centros',        [DatosFPController::class, 'guardarCentro']);
        Route::put('/centros/{id}',    [DatosFPController::class, 'actualizarCentro']);
        Route::delete('/centros/{id}', [DatosFPController::class, 'eliminarCentro']);

        // Familias profesionales
        Route::post('/familias',       [DatosFPController::class, 'storeFamilia']);
        Route::put('/familias/{id}',   [DatosFPController::class, 'updateFamilia']);
        Route::delete('/familias/{id}',[DatosFPController::class, 'destroyFamilia']);

        // Ciclos formativos
        Route::post('/ciclos',         [DatosFPController::class, 'storeCiclo']);
        Route::put('/ciclos/{id}',     [DatosFPController::class, 'updateCiclo']);
        Route::delete('/ciclos/{id}',  [DatosFPController::class, 'destroyCiclo']);

        // Papelera
        Route::prefix('papelera')->group(function () {
            Route::get('/',                        [PapeleraController::class, 'index']);
            Route::delete('/',                     [PapeleraController::class, 'vaciar']);
            Route::patch('/{tipo}/{id}/restaurar', [PapeleraController::class, 'restaurar'])
                ->whereNumber('id');
            Route::delete('/{tipo}/{id}',          [PapeleraController::class, 'destruir'])
                ->whereNumber('id');
        });
    });

    // Empresas — lectura para todos, escritura solo admin
    Route::get('/empresas',                [DatosFPController::class, 'getEmpresas']);
    Route::get('/empresas/dashboard',      [DatosFPController::class, 'getDashboardEmpresas']);
    Route::get('/empresas/{id}/familias',  [DatosFPController::class, 'getFamiliasPorEmpresa']);
    Route::middleware('admin')->group(function () {
        Route::post('/empresas',               [DatosFPController::class, 'guardarEmpresa']);
        Route::put('/empresas/{id}',           [DatosFPController::class, 'actualizarEmpresa']);
        Route::patch('/empresas/{id}/estado',  [DatosFPController::class, 'actualizarEstadoEmpresa']);
        Route::delete('/empresas/{id}',        [DatosFPController::class, 'eliminarEmpresa']);
    });

    // ── Solo docente (+ admin): generación IA, microretos, sesiones, uploads, startup ──
    Route::middleware('docente')->group(function () {

        // Generación IA: throttle estricto (5/min por usuario)
        Route::middleware('throttle:5,1')->group(function () {
            Route::post('/generar-microreto',    [MicroretoIAController::class, 'generar']);
            Route::post('/simular-info-empresa', [MicroretoIAController::class, 'simularInfoEmpresa']);
        });

        // Guardado y borrado de microretos
        Route::post('/guardar-microreto-bd',    [MicroretoIAController::class, 'guardarEnBD']);
        Route::post('/guardar-microretos-lote', [MicroretoIAController::class, 'guardarLote']);
        Route::delete('/microretos/{id}',       [MicroretoIAController::class, 'destroy']);

        // Tokens QR temporales
        Route::get('/microretos/{id}/token',    [MicroretoTokenController::class, 'get']);
        Route::post('/microretos/{id}/token',   [MicroretoTokenController::class, 'generate']);
        Route::delete('/microretos/{id}/token', [MicroretoTokenController::class, 'destroy']);

        // Encuentros
        Route::get('/encuentros',                          [EncuentroController::class, 'index']);
        Route::post('/encuentros',                         [EncuentroController::class, 'store']);
        Route::post('/encuentros/lote',                    [EncuentroController::class, 'storeLote']);
        // Ruta específica antes de la paramétrica {id} — si no, "mis-grupos" se capturaría como {id}
        Route::get('/encuentros/mis-grupos',               [EncuentroController::class, 'misGrupos']);
        Route::post('/encuentros/{id}/crear-codigo',       [EncuentroController::class, 'crearCodigo'])->whereNumber('id');
        Route::get('/encuentros/{id}/workspace',           [EncuentroController::class, 'workspace'])->whereNumber('id');
        Route::get('/encuentros/{id}',                     [EncuentroController::class, 'show']);
        Route::put('/encuentros/{id}',                     [EncuentroController::class, 'update'])->whereNumber('id');
        Route::delete('/encuentros/{id}',                  [EncuentroController::class, 'destroy']);

        // Subida y gestión de recursos (Cloudinary)
        Route::middleware('throttle:30,1')->group(function () {
            Route::get('/upload/recursos',   [UploadController::class, 'listar']);
            Route::post('/upload/recurso',   [UploadController::class, 'recurso']);
            Route::delete('/upload/recurso', [UploadController::class, 'destroy']);
        });

        // Módulo Empresas — contacto y validación
        Route::middleware('throttle:10,1')
            ->post('/empresas/verificar-acceso', [EmpresaContactoController::class, 'verificarAcceso']);
        Route::post('/empresas/{id}/contactar',          [EmpresaContactoController::class, 'contactar']);
        Route::post('/empresas/{id}/enviar-validacion',  [EmpresaContactoController::class, 'enviarValidacion']);

        // StartUp Day — microproyectos
        Route::get('/startup/proyectos',           [MicroproyectoController::class, 'index']);
        Route::post('/startup/proyectos',          [MicroproyectoController::class, 'store']);
        Route::get('/startup/proyectos/{uuid}',                 [MicroproyectoController::class, 'show']);
        Route::put('/startup/proyectos/{uuid}',                 [MicroproyectoController::class, 'update']);
        Route::post('/startup/proyectos/{uuid}/validar-docente',[MicroproyectoController::class, 'validarDocente']);
        Route::delete('/startup/proyectos/{uuid}',              [MicroproyectoController::class, 'destroy']);

        // StartUp Day — IA: sugerencia de RA/CE y KPIs
        Route::middleware('throttle:10,1')->group(function () {
            Route::post('/startup/sugerir-ra-ce',           [MicroproyectoController::class, 'sugerirRaCe']);
            Route::post('/startup/sugerir-kpis',            [MicroproyectoController::class, 'sugerirKpis']);
            Route::post('/startup/sugerir-objetivos',       [MicroproyectoController::class, 'sugerirObjetivos']);
            Route::post('/startup/sugerir-fundamentacion',  [MicroproyectoController::class, 'sugerirFundamentacion']);
            Route::post('/startup/sugerir-metodologia',     [MicroproyectoController::class, 'sugerirMetodologia']);
        });

        // ── Gestión de equipos (docente) ──────────────────────────────────────
        // Nota: la creación de equipos y el listado con progreso viven en
        // EncuentroController (crearCodigo/workspace) — no duplicar aquí.
        Route::get('/startup/proyectos/{uuid}/pantalla-acceso',[EquipoGestionController::class, 'pantallaAcceso']);
        Route::delete('/startup/equipos/{id}',                 [EquipoGestionController::class, 'destroy'])
            ->whereNumber('id');
        Route::patch('/startup/equipos/{id}/fase/{fase}/validar',  [EquipoGestionController::class, 'validarFase'])
            ->whereNumber('id')->whereNumber('fase');
        Route::patch('/startup/equipos/{id}/fase/{fase}/rechazar', [EquipoGestionController::class, 'rechazarFase'])
            ->whereNumber('id')->whereNumber('fase');
        Route::patch('/startup/equipos/{id}/evaluar',              [EquipoGestionController::class, 'evaluar'])
            ->whereNumber('id');
    });

    // ── Gestión de usuarios (solo admin) ─────────────────────────────
    Route::middleware('admin')->prefix('admin/usuarios')->group(function () {
        Route::get('/',                       [AdminUserController::class, 'index']);
        Route::post('/',                      [AdminUserController::class, 'store']);
        Route::get('/papelera',               [AdminUserController::class, 'papelera']);
        Route::patch('/{user}',               [AdminUserController::class, 'update']);
        Route::patch('/{user}/activar',       [AdminUserController::class, 'activar']);
        Route::patch('/{user}/bloquear',      [AdminUserController::class, 'toggleBloquear']);
        Route::delete('/{user}',              [AdminUserController::class, 'destroy']);
        Route::post('/{id}/restaurar',        [AdminUserController::class, 'restaurar']);
        Route::delete('/{id}/destruir',       [AdminUserController::class, 'destruir']);
        Route::patch('/{user}/centro',        [AdminUserController::class, 'asociarCentro'])->middleware('superadmin');
    });

});


// -------- IMPORTACIONES (protegidas con auth) ---------
Route::middleware('auth:sanctum')->get('/importar-excel', function () {
    $path = storage_path('app/agraria.csv'); ///////////////////////////////////////
    if (!file_exists($path)) return "No se encuentra el archivo agraria.csv en storage/app/";

    $familiaImport = 'Agraria'; /////////////////////////////////////
    $familiaId     = \App\Models\Familia::where('nombre', $familiaImport)->value('id');

    $file     = fopen($path, 'r');
    $contador = 0;

    // Ignoramos las primeras 4 líneas (cabeceras)
    for ($i = 0; $i < 4; $i++) fgetcsv($file, 0, ',', '"');

    \Illuminate\Support\Facades\DB::transaction(function () use ($file, $familiaImport, $familiaId, &$contador) {
        while (($data = fgetcsv($file, 0, ',', '"')) !== false) {
            if (empty($data[4])) continue;

            $empresa = \App\Models\Empresa::create([
                'cif'               => $data[2]  ?? null,
                'nombre_comercial'  => $data[4],
                'telefono'          => $data[5]  ?? null,
                'estado_contacto'   => $data[6]  ?? null,
                'fecha_cita'        => $data[7]  ?? null,
                'persona_contacto'  => $data[8]  ?? null,
                'email_general'     => $data[9]  ?? null,
                'posicion_contacto' => $data[10] ?? null,
                'sector'            => $data[11] ?? null,
                'actividad'         => $data[12] ?? null,
                'horario_atencion'  => $data[13] ?? null,
                'direccion'         => $data[14] ?? null,
                'numero'            => $data[15] ?? null,
                'otros_direccion'   => $data[16] ?? null,
                'codigo_postal'     => $data[17] ?? null,
                'municipio'         => $data[18] ?? null,
                'provincia'         => $data[19] ?? null,
                'web'               => $data[20] ?? null,
                'proyecto_asociado' => $data[21] ?? null,
            ]);

            \Illuminate\Support\Facades\DB::table('empresa_familia')->insert([
                'empresa_id' => $empresa->id,
                'familia'    => $familiaImport,
                'familia_id' => $familiaId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $contador++;
        }
    });

    fclose($file);
    return "¡BINGO! Se han importado correctamente {$contador} empresas.";
});

/* 

Route::get('/importar-excel-nuevo', function () {
    // 1. CAMBIA AQUÍ el nombre de tu archivo CSV
    $path = storage_path('app/madera_2.csv'); ////////////////////////////////////////////
    if (!file_exists($path)) return "No se encuentra el archivo en storage/app/";

    $file = fopen($path, 'r');

    // Como en tu foto las cabeceras están en la fila 1, solo ignoramos 1 línea
    fgetcsv($file, 0, ',', '"');

    $contador = 0;

    // Leemos línea por línea
    while (($data = fgetcsv($file, 0, ',', '"')) !== false) {
        
        // Si la columna 0 (Nombre) está vacía, saltamos esta fila
        if (!isset($data[0]) || empty(trim($data[0]))) continue; 

        // 2. Mapeo adaptado a tu nueva imagen
        $empresa = \App\Models\Empresa::create([
            'nombre_comercial'  => trim($data[0]),
            'direccion'         => trim($data[1]) ?? null,
            'cif'               => trim($data[2]) ?? null,
            'provincia'         => trim($data[3]) ?? null,
            'municipio'         => trim($data[4]) ?? null,
            'sector'            => trim($data[5]) ?? null,
            'email_general'     => trim($data[6]) ?? null,
            'telefono'          => trim($data[7]) ?? null,
            'estado_contacto'   => trim($data[8]) ?? null, // Usamos la columna "NOTA" para esto
            
            // --- ASIGNAMOS EL CENTRO EDUCATIVO ---
            // 3. CAMBIA AQUÍ el nombre del nuevo colegio
            'centro_educativo'  => 'IES Aguas Nuevas', 
        ]);

        // 4. La vinculamos a la Familia Profesional
        // Viendo tu Excel, casi todas son Agrarias, así que lo puedes dejar fijo aquí
        \Illuminate\Support\Facades\DB::table('empresa_familia')->insert([
            'empresa_id' => $empresa->id,
            'familia'    => 'Madera, Mueble y Corcho' //////////////////////////////////////////////////////////
        ]);

        $contador++;
    }

    fclose($file);
    return "¡BINGO! Se han importado correctamente {$contador} nuevas empresas.";
}); */

/* Route::get('/importar-excel-3', function () {
    // 1. CAMBIA AQUÍ el nombre de tu nuevo archivo CSV
    $path = storage_path('app/madera_2.csv'); ///////////////////////////////////////77
    if (!file_exists($path)) return "No se encuentra el archivo en storage/app/";

    $file = fopen($path, 'r');

    // Ignoramos la primera línea (cabeceras rojas)
    fgetcsv($file, 0, ',', '"');

    $contador = 0;

    // Leemos línea por línea
    while (($data = fgetcsv($file, 0, ',', '"')) !== false) {
        
        // El Nombre Comercial está en la columna D (índice 3). Si está vacía, saltamos.
        if (!isset($data[3]) || empty(trim($data[3]))) continue; 

        // 2. Mapeo adaptado a la estructura de tu nuevo Excel (Filas amarillas)
        $empresa = \App\Models\Empresa::create([
            'cif'               => trim($data[2] ?? null),  // C
            'nombre_comercial'  => trim($data[3] ?? null),  // D
            'razon_social'      => trim($data[4] ?? null),  // E
            'telefono'          => trim($data[5] ?? null),  // F
            'estado_contacto'   => trim($data[6] ?? null),  // G
            'fecha_cita'        => trim($data[7] ?? null),  // H
            'persona_contacto'  => trim($data[8] ?? null),  // I
            'email_general'     => trim($data[9] ?? null),  // J
            'web'               => trim($data[10] ?? null), // K
            'posicion_contacto' => trim($data[11] ?? null), // L
            'sector'            => trim($data[12] ?? null), // M
            'actividad'         => trim($data[13] ?? null), // N
            'horario_atencion'  => trim($data[14] ?? null), // O
            'direccion'         => trim($data[15] ?? null), // P
            'numero'            => trim($data[16] ?? null), // Q
            'otros_direccion'   => trim($data[17] ?? null), // R
            'codigo_postal'     => trim($data[18] ?? null), // S
            'municipio'         => trim($data[19] ?? null), // T
            'provincia'         => trim($data[20] ?? null), // U (Asumiendo que está en la U)
            
            // --- ASIGNAMOS EL CENTRO EDUCATIVO ---
            
            'centro_educativo'  => 'IES Nombre Del Centro', 
        ]);

        // 4. La vinculamos a la Familia Profesional
        // Veo en la imagen sectores como "Madera, Mueble y Corcho" o el ciclo "CFGM - SMR" (Informática)
        \Illuminate\Support\Facades\DB::table('empresa_familia')->insert([
            'empresa_id' => $empresa->id,
            'familia'    => 'Madera, Mueble y Corcho' // //////////////////////////////////77
        ]);

        $contador++;
    }

    fclose($file);
    return "¡BINGO! Se han importado correctamente {$contador} nuevas empresas con la nueva estructura.";
}); */



/* Route::get('/importar-excel-4', function () {
    // 1. CAMBIA AQUÍ el nombre de tu nuevo archivo CSV
    $path = storage_path('app/transporte_2.csv');  //////////////////////////////////////
    if (!file_exists($path)) return "No se encuentra el archivo en storage/app/";

    $file = fopen($path, 'r');

    // Ignoramos la primera línea (cabeceras)
    fgetcsv($file, 0, ',', '"');

    $contador = 0;

    // Leemos línea por línea
    while (($data = fgetcsv($file, 0, ',', '"')) !== false) {
        
        // El Nombre Comercial está en la columna D (índice 3). Si está vacía, saltamos.
        if (!isset($data[3]) || empty(trim($data[3]))) continue; 

        // 2. Mapeo adaptado a la estructura de tu nuevo Excel (Filas Rosas/Naranjas)
        $empresa = \App\Models\Empresa::create([
            'cif'               => trim($data[2] ?? null),  // Col C
            'nombre_comercial'  => trim($data[3] ?? null),  // Col D
            
            // Faltan Razón Social en el Excel, pasamos directo al teléfono
            'telefono'          => trim($data[4] ?? null),  // Col E
            'estado_contacto'   => trim($data[5] ?? null),  // Col F
            'fecha_cita'        => trim($data[6] ?? null),  // Col G
            'persona_contacto'  => trim($data[7] ?? null),  // Col H
            'email_general'     => trim($data[8] ?? null),  // Col I
            'web'               => trim($data[9] ?? null),  // Col J
            
            // Falta "Posición", pasamos directo al Sector
            'sector'            => trim($data[10] ?? null), // Col K
            'actividad'         => trim($data[11] ?? null), // Col L
            'horario_atencion'  => trim($data[12] ?? null), // Col M
            'direccion'         => trim($data[13] ?? null), // Col N
            'numero'            => trim($data[14] ?? null), // Col O
            'otros_direccion'   => trim($data[15] ?? null), // Col P
            'codigo_postal'     => trim($data[16] ?? null), // Col Q
            'municipio'         => trim($data[17] ?? null), // Col R
            'provincia'         => trim($data[18] ?? null), // Col S
            
           
            'centro_educativo'  => 'IES Aguas Nuevas', 
        ]);

        // 4. La vinculamos a la Familia Profesional
        // Viendo el Excel, el sector es Transporte y Mantenimiento
        \Illuminate\Support\Facades\DB::table('empresa_familia')->insert([
            'empresa_id' => $empresa->id,
            'familia'    => 'Transporte y Mantenimiento de Vehículos' // <--- CAMBIA ESTO si es necesario
        ]);

        $contador++;
    }

    fclose($file);
    return "¡BINGO! Se han importado correctamente {$contador} nuevas empresas (Formato Transporte).";
}); */

Route::middleware('auth:sanctum')->get('/importar-excel-5', function () {
    // 1. CAMBIA AQUÍ el nombre de tu archivo CSV de Energías
    $path = storage_path('app/energia_2.csv'); 
    if (!file_exists($path)) return "No se encuentra el archivo en storage/app/";

    $file = fopen($path, 'r');

    // Ignoramos la primera línea (cabeceras)
    fgetcsv($file, 0, ',', '"');

    $contador = 0;

    // Leemos línea por línea
    while (($data = fgetcsv($file, 0, ',', '"')) !== false) {
        
        // ¡OJO! Ahora el Nombre Comercial cae en el índice 4 por el desfase del CSV
        if (!isset($data[4]) || empty(trim($data[4]))) continue; 

        // 2. Mapeo corregido con el desfase exacto del log de SQL
        $empresa = \App\Models\Empresa::create([
            'cif'               => trim($data[3] ?? null),
            'nombre_comercial'  => trim($data[4] ?? null),
            'razon_social'      => trim($data[5] ?? null),
            'telefono'          => trim($data[6] ?? null),
            'estado_contacto'   => trim($data[7] ?? null),
            'fecha_cita'        => trim($data[8] ?? null),
            'persona_contacto'  => trim($data[9] ?? null),
            'email_general'     => trim($data[10] ?? null),
            'web'               => trim($data[11] ?? null), // ¡Ahora sí cae en el campo TEXT!
            'posicion_contacto' => trim($data[12] ?? null),
            'sector'            => trim($data[13] ?? null), // Aquí pondrá "Energía"
            'actividad'         => trim($data[14] ?? null),
            'horario_atencion'  => trim($data[15] ?? null),
            'direccion'         => trim($data[16] ?? null),
            'numero'            => trim($data[17] ?? null),
            'otros_direccion'   => trim($data[18] ?? null),
            'codigo_postal'     => trim($data[19] ?? null),
            'municipio'         => trim($data[20] ?? null),
            'provincia'         => trim($data[21] ?? null),
            
            
            'centro_educativo'  => 'IES Aguas Nuevas', 
        ]);

        // 4. Asignamos la Familia Profesional (con FK normalizada)
        $familiaImport5 = 'Energía y Agua';
        $familiaId5 = \App\Models\Familia::where('nombre', $familiaImport5)->value('id');
        \Illuminate\Support\Facades\DB::table('empresa_familia')->insert([
            'empresa_id' => $empresa->id,
            'familia'    => $familiaImport5,
            'familia_id' => $familiaId5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $contador++;
    }

    fclose($file);
    return "¡BINGO! Se han importado correctamente {$contador} nuevas empresas del sector Energía.";
});