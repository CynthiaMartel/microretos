<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DatosFPController;
use App\Http\Controllers\MicroretoIAController;
use App\Http\Controllers\EmpresaFicticiaController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\SoporteController;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\MicroretoTokenController;
use App\Http\Controllers\MicroproyectoController;
use App\Http\Controllers\EncuentroColaboradorController;
use App\Http\Controllers\EncuentroController;
use App\Http\Controllers\EmpresaContactoController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\PapeleraController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\EquipoPublicoController;
use App\Http\Controllers\FormularioEmpresaController;
use App\Http\Controllers\EquipoGestionController;
use App\Http\Controllers\DocenteNotaController;
use App\Http\Controllers\DocenteTareaController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\AlumnadoController;
use App\Http\Controllers\ModulosDocenteController;
use App\Http\Controllers\PublicMicroretoCatalogoController;
use App\Http\Controllers\PublicMicroproyectoCatalogoController;
use App\Http\Controllers\CurriculoController;
use App\Http\Controllers\ComercialCentroController;
use App\Http\Controllers\IntegracionFrontofficeController;
use App\Http\Controllers\ComercialEmpresaController;
use App\Http\Controllers\CaptacionAdminController;
use App\Http\Controllers\ValoracionEmpresaController;
use App\Http\Controllers\DestacadoCentralController;
use App\Http\Controllers\PrimerContactoController;
use App\Http\Controllers\PrimerContactoCorreoController;
use App\Http\Controllers\PlanProyectoController;
use App\Http\Controllers\AgendaPracticasController;
use App\Http\Controllers\RegistroHorasController;
use App\Http\Controllers\ActividadCentralController;
use App\Http\Controllers\ConsultaCentralController;
use App\Http\Controllers\SolicitudRetoController;
use App\Http\Controllers\SolicitudRetoCorreoController;
use App\Http\Controllers\SolicitudRetoPublicaController;

/*
|--------------------------------------------------------------------------
| API Routes - DuaLab Studio
|--------------------------------------------------------------------------
*/

// --- RUTAS PÚBLICAS (sin autenticación) ---

// Datos académicos: familias públicas para formularios de contacto u otros usos
Route::middleware('throttle:publico-lectura')->group(function () {
    Route::get('/familias', [DatosFPController::class, 'getFamilias']);
});

// Formulario de la entrevista que el comercial envía por correo a la empresa (app comercial,
// /formulario/{token}): sin sesión, el token es la credencial (FormularioEmpresaService)
Route::middleware('throttle:formulario-empresa')->prefix('public/formulario-empresa/{token}')
    ->where(['token' => '[A-Za-z0-9]{64}'])->group(function () {
        Route::get('/',         [FormularioEmpresaController::class, 'show']);
        Route::put('/empresa',  [FormularioEmpresaController::class, 'guardarEmpresa']);
        Route::put('/',         [FormularioEmpresaController::class, 'guardar']);
        Route::post('/firma',   [FormularioEmpresaController::class, 'firmar'])->middleware('throttle:10,1,formulario-firma');
    });

// Solicitud de retos que DuaLab envía al docente (app DuaLab, /solicitud-reto/{token}): sin
// sesión, el token es la credencial (SolicitudRetoService). Con el enlace del aviso, sus retos.
Route::middleware('throttle:solicitud-reto')->prefix('public/solicitud-reto/{token}')
    ->where(['token' => '[A-Za-z0-9]{64}'])->group(function () {
        Route::get('/',        [SolicitudRetoPublicaController::class, 'show']);
        Route::put('/',        [SolicitudRetoPublicaController::class, 'guardar']);
        Route::post('/enviar', [SolicitudRetoPublicaController::class, 'enviar'])->middleware('throttle:10,1,solicitud-reto-enviar');
        Route::get('/retos',   [SolicitudRetoPublicaController::class, 'retos']);
    });

// Acceso público por token temporal (QR para alumnado — ficha del microreto)
Route::middleware('throttle:microreto-publico')
     ->get('/public/microreto/{token}', [MicroretoTokenController::class, 'show']);

// ── Workspace de equipos (alumnado) — acceso público por código o token ────
// throttle más restrictivo para evitar fuerza bruta sobre códigos cortos
Route::middleware('throttle:workspace-codigo')->group(function () {
    // Código de clase → devuelve proyecto + lista de equipos para seleccionar
    Route::get('/clase/{codigo}',          [EquipoPublicoController::class, 'porCodigoClase']);
    // Código de equipo → acceso directo al workspace (backwards compat)
    Route::get('/equipo/unirse/{codigo}',  [EquipoPublicoController::class, 'unirse']);
});

Route::middleware('throttle:workspace-lectura')->group(function () {
    Route::get('/equipo/{token}',                          [EquipoPublicoController::class, 'show']);
    Route::get('/equipo/{token}/reto',                     [EquipoPublicoController::class, 'verReto']);
    Route::put('/equipo/{token}/fase/{fase}',              [EquipoPublicoController::class, 'guardarFase'])
        ->whereNumber('fase');
    Route::post('/equipo/{token}/fase/{fase}/completar',   [EquipoPublicoController::class, 'completarFase'])
        ->whereNumber('fase');
    Route::post('/equipo/{token}/fase/0/confirmar-nombres', [EquipoPublicoController::class, 'confirmarNombres']);
    Route::post('/equipo/{token}/tareas',                  [EquipoPublicoController::class, 'storeTarea']);
    Route::post('/equipo/{token}/fase/2/restablecer-tareas-genericas', [EquipoPublicoController::class, 'restablecerTareasGenericas']);
    Route::put('/equipo/{token}/tareas/{tarea}',           [EquipoPublicoController::class, 'updateTarea'])
        ->whereNumber('tarea');
    Route::delete('/equipo/{token}/tareas/{tarea}',        [EquipoPublicoController::class, 'destroyTarea'])
        ->whereNumber('tarea');
    Route::post('/equipo/{token}/reflexiones',             [EquipoPublicoController::class, 'storeReflexion']);
    Route::put('/equipo/{token}/miembros/{miembroId}/alias', [EquipoPublicoController::class, 'actualizarAliasMiembro'])
        ->whereNumber('miembroId');
});

Route::middleware('throttle:workspace-prototipos')->group(function () {
    Route::post('/equipo/{token}/prototipos',       [EquipoPublicoController::class, 'storePrototipo']);
    Route::delete('/equipo/{token}/prototipos/{id}', [EquipoPublicoController::class, 'destroyPrototipo'])
        ->whereNumber('id');
});

// Banco de imágenes del proyecto — el alumnado sube al mismo banco que el docente,
// cupo propio por equipo (no comparte cuota con prototipos ni con otros endpoints públicos)
Route::middleware('throttle:workspace-imagenes')->group(function () {
    Route::post('/equipo/{token}/imagenes',                [EquipoPublicoController::class, 'storeImagen']);
    Route::delete('/equipo/{token}/imagenes/{id}',          [EquipoPublicoController::class, 'destroyImagen'])
        ->whereNumber('id');
    Route::put('/equipo/{token}/imagenes/{id}/portada',     [EquipoPublicoController::class, 'marcarPortadaImagen'])
        ->whereNumber('id');
});

// Sugerencias IA del workspace de alumnado — cupo propio por equipo (no por IP),
// así no comparte contador ni con el resto de endpoints públicos ni con otros equipos
Route::middleware('throttle:workspace-ia')->group(function () {
    Route::post('/equipo/{token}/fase/1/sugerir-hallazgo', [EquipoPublicoController::class, 'sugerirHallazgo']);
    Route::post('/equipo/{token}/fase/2/sugerir-tareas',   [EquipoPublicoController::class, 'sugerirTareas']);
});

// Desbloqueo del módulo IA del workspace — cupo propio por equipo
// (evita fuerza bruta sobre el código corto que reparte el docente)
Route::middleware('throttle:workspace-ia-codigo')
    ->post('/equipo/{token}/ia/verificar-codigo', [EquipoPublicoController::class, 'verificarCodigoIa']);

// Validación pública del microproyecto por parte de la empresa (acceso por token)
Route::middleware('throttle:startup-landing')->group(function () {
    Route::get('/startup/landing/{token}',          [MicroproyectoController::class, 'showByToken']);
    Route::post('/startup/landing/{token}/validar', [MicroproyectoController::class, 'validarEmpresa']);
});

// Valoración final de la empresa sobre la propuesta de un encuentro creativo (acceso por token
// que caduca). Cupo por IP: con uno por token, probar tokens al azar no tendría límite.
Route::middleware('throttle:valoracion-empresa')->group(function () {
    Route::get('/valoracion-empresa/{token}',  [ValoracionEmpresaController::class, 'showPublico'])->where('token', '[A-Za-z0-9]{20,64}');
    Route::post('/valoracion-empresa/{token}', [ValoracionEmpresaController::class, 'responder'])->where('token', '[A-Za-z0-9]{20,64}');
});

// Datos académicos (ciclos, módulos) — throttle estándar
Route::middleware('throttle:datos-academicos')->group(function () {
    Route::get('/familias/{familia}/ciclos',  [DatosFPController::class, 'getCiclos']);
    Route::get('/ciclos/{idCiclo}/modulos',   [DatosFPController::class, 'getModulos']);
    Route::get('/modulos/{idModulo}/ra-ce',   [DatosFPController::class, 'getRaCe']);
    Route::get('/centros',                    [DatosFPController::class, 'getCentros']);
});

// Demos públicas: mismo límite que el resto de lecturas públicas
Route::middleware('throttle:publico-lectura')->group(function () {
    Route::get('/demos', [DemoController::class, 'index']);
    Route::get('/demos/{familia}/microretos', [DemoController::class, 'microretos'])
        ->where('familia', '[a-zA-ZÀ-ÿ0-9 ,.\-]{1,100}');
    Route::get('/demos/{familia}', [DemoController::class, 'show'])
        ->where('familia', '[a-zA-ZÀ-ÿ0-9 ,.\-]{1,100}');
});

// Escaparate público de microretos (frontoffice dualab.es / info.dualab.es) — solo
// lectura, solo retos marcados visible_publico=true. Rutas específicas antes de la
// paramétrica {uuid} para que "familias" no se interprete como un uuid.
Route::middleware('throttle:catalogo-publico')->group(function () {
    Route::get('/public/microretos/familias', [PublicMicroretoCatalogoController::class, 'familias']);
    Route::get('/public/microretos/{uuid}',    [PublicMicroretoCatalogoController::class, 'show']);
    Route::get('/public/microretos',           [PublicMicroretoCatalogoController::class, 'index']);

    // Proyectos completados marcados visible_publico=true — misma política de acceso.
    Route::get('/public/microproyectos/{uuid}/equipos', [PublicMicroproyectoCatalogoController::class, 'equipos']);
    Route::get('/public/microproyectos/{uuid}',          [PublicMicroproyectoCatalogoController::class, 'show']);
    Route::get('/public/microproyectos',                 [PublicMicroproyectoCatalogoController::class, 'index']);
});

// Auth pública — throttle estricto para prevenir fuerza bruta
Route::middleware('throttle:admin-login')
    ->post('/admin/login', [AdminAuthController::class, 'login']);

// "¿Has olvidado tu contraseña?" — además del throttle por IP, el broker limita a
// 1 enlace por email cada 5 minutos (config/auth.php → passwords.users.throttle)
Route::middleware('throttle:5,1,forgot-password')
    ->post('/password/forgot', [PasswordResetController::class, 'forgot']);
Route::middleware('throttle:5,1,reset-password')
    ->post('/password/reset', [PasswordResetController::class, 'reset']);

// "Contacta con soporte" (pública: quien no consigue entrar también tiene que poder escribir)
Route::middleware('throttle:soporte')
    ->post('/soporte', [SoporteController::class, 'store']);

// Webhook del frontoffice (dualab.es/contacto → captaciones). Sin sesión: lo protege la firma
// HMAC del cuerpo (VerificarFirmaFrontoffice). La comprobación de firma va antes del throttle
// para que nadie sin el secreto gaste el cupo de los envíos legítimos.
Route::middleware(['firma.frontoffice', 'throttle:60,1,frontoffice-webhook'])
    ->post('/integraciones/frontoffice/formularios', [IntegracionFrontofficeController::class, 'recibir']);


// --- RUTAS PROTEGIDAS (requieren token Sanctum) ---

Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/admin/logout',          [AdminAuthController::class, 'logout']);
    // Todo `throttle:N,M` lleva un prefijo propio (3.er argumento): sin él, la clave del
    // contador es solo el id del usuario y TODAS las rutas con throttle comparten cupo
    // (6 consultas a /notificaciones/no-leidas agotaban el 5/min de /generar-microreto → 429).
    Route::middleware('throttle:5,1,verify-password')
        ->post('/admin/verify-password', [AdminAuthController::class, 'verifyPassword']);

    // Perfil propio (accesible por todos los roles autenticados)
    Route::get('/perfil',   [AdminAuthController::class, 'getPerfil']);
    Route::patch('/perfil', [AdminAuthController::class, 'updatePerfil']);
    // Módulos que imparte el propio docente (limitan a quién puede invitar como colaborador)
    Route::get('/perfil/modulos', [ModulosDocenteController::class, 'mios']);
    Route::middleware('throttle:30,1,perfil-modulos')->put('/perfil/modulos', [ModulosDocenteController::class, 'actualizarMios']);

    // Biblioteca de microretos (lectura). El controlador acota qué ve cada rol (limitarAlcance)
    Route::middleware(['rol:superadmin,admin,docente,empresa', 'throttle:60,1,microretos-lectura'])->group(function () {
        Route::get('/microretos',      [MicroretoIAController::class, 'index']);
        Route::get('/microretos/{id}', [MicroretoIAController::class, 'show']);
    });

    // ── Solo superadmin: catálogo global (centros, familias, ciclos) y papelera ──
    // Es gestión de la app a nivel de "intranet", no ligada a un centro concreto —
    // el admin de centro tiene el mismo alcance que el docente aquí, ninguno.
    Route::middleware('superadmin')->group(function () {

        // Centros educativos
        Route::post('/centros',                        [DatosFPController::class, 'guardarCentro']);
        Route::post('/centros/{id}/impacto-cambio',     [DatosFPController::class, 'impactoCambioCentro']);
        Route::put('/centros/{id}',                    [DatosFPController::class, 'actualizarCentro']);
        Route::delete('/centros/{id}',                  [DatosFPController::class, 'eliminarCentro']);
        Route::post('/centros/{id}/empresas/asociar',   [DatosFPController::class, 'asociarEmpresas']);
        Route::post('/centros/imagen',                  [UploadController::class, 'imagenCentro']);

        // Familias profesionales
        Route::post('/familias',       [DatosFPController::class, 'storeFamilia']);
        Route::put('/familias/{id}',   [DatosFPController::class, 'updateFamilia']);
        Route::delete('/familias/{id}',[DatosFPController::class, 'destroyFamilia']);

        // Ciclos formativos
        Route::post('/ciclos',         [DatosFPController::class, 'storeCiclo']);
        Route::put('/ciclos/{id}',     [DatosFPController::class, 'updateCiclo']);
        Route::delete('/ciclos/{id}',  [DatosFPController::class, 'destroyCiclo']);
        // Currículo del ciclo (módulos, RA y CE) desde CSV; throttle propio: procesa archivos grandes
        Route::post('/ciclos/{id}/curriculo/importar', [CurriculoController::class, 'importar'])
            ->whereNumber('id')->middleware('throttle:20,1,curriculo-importar');

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

    // Empresas — lectura (propio centro para docente/admin) para todos los roles autenticados.
    // Alta/estado: admin (acotado a su propio centro, ver DatosFPController) o superadmin
    // — se usa tanto desde "Base de datos" como desde el alta inline en el Generador de Retos.
    // La edición (PUT) está en el grupo 'docente': la acota EmpresaPolicy::update.
    // El dashboard global (todos los centros) y el borrado son solo superadmin: es catálogo
    // cross-centro, no trabajo del día a día de un centro concreto.
    // admin_central_dualab: Central → Usuarios, para asignar empresa a una cuenta
    Route::get('/empresas',                [DatosFPController::class, 'getEmpresas'])
        ->middleware('rol:superadmin,admin_central_dualab,admin,docente,empresa');
    // whereNumber: sin él capturaba /empresas/ficticia-ia/familias (registrada más abajo)
    Route::get('/empresas/{id}/familias',  [DatosFPController::class, 'getFamiliasPorEmpresa'])->whereNumber('id')
        ->middleware('rol:superadmin,admin,docente,empresa');
    Route::middleware('admin')->group(function () {
        Route::post('/empresas',               [DatosFPController::class, 'guardarEmpresa']);
        Route::patch('/empresas/{id}/estado',  [DatosFPController::class, 'actualizarEstadoEmpresa']);
    });
    Route::middleware('superadmin')->group(function () {
        Route::get('/empresas/dashboard',      [DatosFPController::class, 'getDashboardEmpresas']);
        Route::delete('/empresas/{id}',        [DatosFPController::class, 'eliminarEmpresa']);
    });

    // ── Solo docente (+ admin): generación IA, microretos, sesiones, uploads, startup ──
    Route::middleware('docente')->group(function () {

        // Notificaciones in-app y notas personales (vistas Notificaciones y Calendario).
        // Siempre sobre el usuario autenticado; el contador se consulta al navegar.
        // Rutas fijas antes que las paramétricas.
        Route::middleware('throttle:60,1,docente-panel')->group(function () {
            Route::get('/notificaciones',                [NotificacionController::class, 'index']);
            Route::get('/notificaciones/no-leidas',      [NotificacionController::class, 'contadorNoLeidas']);
            Route::post('/notificaciones/leer-todas',    [NotificacionController::class, 'marcarTodasLeidas']);
            Route::patch('/notificaciones/{id}/leida',   [NotificacionController::class, 'marcarLeida'])
                ->whereUuid('id');

            Route::get('/notas',            [DocenteNotaController::class, 'index']);
            Route::post('/notas',           [DocenteNotaController::class, 'store']);
            Route::patch('/notas/{nota}',   [DocenteNotaController::class, 'update'])->whereNumber('nota');
            Route::delete('/notas/{nota}',  [DocenteNotaController::class, 'destroy'])->whereNumber('nota');

            Route::get('/tareas',             [DocenteTareaController::class, 'index']);
            Route::post('/tareas',            [DocenteTareaController::class, 'store']);
            Route::patch('/tareas/{tarea}',   [DocenteTareaController::class, 'update'])->whereNumber('tarea');
            Route::delete('/tareas/{tarea}',  [DocenteTareaController::class, 'destroy'])->whereNumber('tarea');
        });

        // Listado de alumnado participante (por encuentros visibles para el usuario).
        // Respuesta pesada (equipos, fases, tareas…): throttle propio más bajo.
        Route::get('/alumnado', [AlumnadoController::class, 'index'])->middleware('throttle:30,1,alumnado');

        // Generación IA: throttle estricto (5/min por usuario)
        Route::middleware('throttle:5,1,ia-generacion')->group(function () {
            Route::post('/generar-microreto',        [MicroretoIAController::class, 'generar']);
            Route::post('/simular-info-empresa',     [MicroretoIAController::class, 'simularInfoEmpresa']);
            // Empresa ficticia completa (datos + diagnóstico) desde el Generador: la IA genera
            // una propuesta que el usuario revisa antes de guardarla (ver rutas de abajo).
            Route::post('/empresas/ficticia-ia/propuesta', [EmpresaFicticiaController::class, 'propuesta']);
        });

        // Confirmar (guardar) o descartar la propuesta de empresa ficticia generada con IA.
        Route::middleware('throttle:30,1,ficticia-ia')->group(function () {
            Route::post('/empresas/ficticia-ia',                     [EmpresaFicticiaController::class, 'store']);
            // Ruta fija antes que las paramétricas de /empresas/ficticia-ia/propuesta/{token}
            Route::get('/empresas/ficticia-ia/familias',             [EmpresaFicticiaController::class, 'familiasDisponibles']);
            Route::get('/empresas/ficticia-ia/propuesta/{token}',    [EmpresaFicticiaController::class, 'verPropuesta'])
                ->whereUuid('token');
            Route::delete('/empresas/ficticia-ia/propuesta/{token}', [EmpresaFicticiaController::class, 'descartar'])
                ->whereUuid('token');
        });

        // Catálogo DuaLab de empresas ficticias (T2): lectura y copia al propio centro.
        // Rutas específicas antes que /empresas/{id}/... (no colisionan: prefijo 'catalogo').
        Route::get('/empresas/catalogo',             [EmpresaFicticiaController::class, 'catalogo']);
        Route::post('/empresas/catalogo/{id}/usar',  [EmpresaFicticiaController::class, 'usarEnCentro'])
            ->whereNumber('id')
            ->middleware('throttle:30,1,catalogo-usar');

        // Datos de ficha de una empresa: docente solo sus ficticias, admin las de su centro,
        // superadmin todas (EmpresaPolicy::update). Alta y estado siguen siendo de admin.
        Route::put('/empresas/{id}', [DatosFPController::class, 'actualizarEmpresa'])
            ->whereNumber('id')
            ->middleware('throttle:30,1,empresa-editar');

        // Diagnóstico de empresa desde el Generador (P1–P4): solo ficticias de su centro,
        // las reales están protegidas (ver EmpresaPolicy::actualizarDiagnostico).
        Route::patch('/empresas/{id}/diagnostico', [DatosFPController::class, 'actualizarDiagnosticoEmpresa'])
            ->whereNumber('id')
            ->middleware('throttle:30,1,empresa-diagnostico');

        // Interés de la empresa en una familia (paso 3 del Generador): mismas reglas que el
        // diagnóstico (EmpresaPolicy::actualizarDiagnostico).
        Route::patch('/empresas/{id}/interes', [DatosFPController::class, 'actualizarInteresEmpresa'])
            ->whereNumber('id')
            ->middleware('throttle:30,1,empresa-interes');

        // Guardado y borrado de microretos
        Route::post('/guardar-microreto-bd',    [MicroretoIAController::class, 'guardarEnBD']);
        Route::post('/guardar-microretos-lote', [MicroretoIAController::class, 'guardarLote']);
        Route::delete('/microretos/{id}',       [MicroretoIAController::class, 'destroy']);
        Route::patch('/microretos/{id}/visible-publico', [MicroretoIAController::class, 'toggleVisiblePublico']);

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
        Route::post('/encuentros/{id}/codigo-ia',          [EncuentroController::class, 'generarCodigoIa'])->whereNumber('id');
        Route::patch('/encuentros/{id}/reestructurar-equipos', [EncuentroController::class, 'reestructurarEquipos'])->whereNumber('id');
        Route::patch('/encuentros/{id}/miembros/{miembroId}/alias', [EncuentroController::class, 'actualizarAliasMiembro'])->whereNumber('id')->whereNumber('miembroId');
        Route::get('/encuentros/{id}/workspace',           [EncuentroController::class, 'workspace'])->whereNumber('id');
        // Valoración final de la empresa (P3): ver estado, pedirla (genera/renueva el enlace) y marcarla enviada
        Route::get('/encuentros/{id}/valoracion-empresa',           [ValoracionEmpresaController::class, 'show'])->whereNumber('id');
        Route::post('/encuentros/{id}/valoracion-empresa',          [ValoracionEmpresaController::class, 'solicitar'])->whereNumber('id');
        Route::patch('/encuentros/{id}/valoracion-empresa/enviada', [ValoracionEmpresaController::class, 'marcarEnviada'])->whereNumber('id');
        // Ruta específica antes de la paramétrica {id} de abajo
        Route::get('/encuentros/{id}/colaboradores/candidatos', [EncuentroColaboradorController::class, 'candidatos'])->whereNumber('id');
        Route::get('/encuentros/{id}/colaboradores',             [EncuentroColaboradorController::class, 'index'])->whereNumber('id');
        Route::post('/encuentros/{id}/colaboradores',            [EncuentroColaboradorController::class, 'store'])->whereNumber('id');
        Route::patch('/encuentros/{id}/colaboradores/{userId}',  [EncuentroColaboradorController::class, 'update'])->whereNumber('id')->whereNumber('userId');
        Route::delete('/encuentros/{id}/colaboradores/{userId}', [EncuentroColaboradorController::class, 'destroy'])->whereNumber('id')->whereNumber('userId');
        Route::get('/encuentros/{id}',                     [EncuentroController::class, 'show']);
        Route::put('/encuentros/{id}',                     [EncuentroController::class, 'update'])->whereNumber('id');
        Route::delete('/encuentros/{id}',                  [EncuentroController::class, 'destroy']);

        // Subida y gestión de recursos (Cloudinary)
        Route::middleware('throttle:30,1,upload')->group(function () {
            Route::get('/upload/recursos',           [UploadController::class, 'listar']);
            Route::post('/upload/recurso',           [UploadController::class, 'recurso']);
            Route::delete('/upload/recurso',         [UploadController::class, 'destroy']);
            Route::put('/upload/recurso/portada',    [UploadController::class, 'marcarPortada']);
        });

        // Módulo Empresas — contacto y validación
        Route::middleware('throttle:empresas-acceso')
            ->post('/empresas/verificar-acceso', [EmpresaContactoController::class, 'verificarAcceso']);
        Route::post('/empresas/{id}/contactar',          [EmpresaContactoController::class, 'contactar']);
        Route::post('/empresas/{id}/enviar-validacion',  [EmpresaContactoController::class, 'enviarValidacion']);

        // Taller de Ideas — microproyectos
        Route::get('/startup/proyectos',           [MicroproyectoController::class, 'index']);
        Route::post('/startup/proyectos',          [MicroproyectoController::class, 'store']);
        Route::get('/startup/proyectos/{uuid}',                 [MicroproyectoController::class, 'show']);
        Route::put('/startup/proyectos/{uuid}',                 [MicroproyectoController::class, 'update']);
        Route::post('/startup/proyectos/{uuid}/validar-docente',[MicroproyectoController::class, 'validarDocente']);
        Route::patch('/startup/proyectos/{uuid}/visible-publico',[MicroproyectoController::class, 'toggleVisiblePublico']);
        Route::delete('/startup/proyectos/{uuid}',              [MicroproyectoController::class, 'destroy']);

        // Plan de proyecto (etapa 2, P4): crearlo (desde una propuesta o un reto) y su cronograma.
        // Listado y edición: los de la propuesta (/startup/proyectos?etapa=plan y el asistente).
        Route::post('/planes',                    [PlanProyectoController::class, 'store']);
        Route::post('/planes/{uuid}/cronograma',  [PlanProyectoController::class, 'storeCronograma'])->whereUuid('uuid');
        // Agenda de prácticas: avance de los grupos de cada cronograma (docente: los suyos; superadmin/Central: todos)
        Route::get('/agenda-practicas',           [AgendaPracticasController::class, 'index'])->middleware('throttle:60,1,agenda-practicas');
        // Horas de prácticas (A3): las anotan docente y DuaLab; cupo propio para las escrituras
        Route::get('/agenda-practicas/{id}/horas',    [RegistroHorasController::class, 'index'])->whereNumber('id');
        Route::middleware('throttle:60,1,registro-horas')->group(function () {
            Route::post('/agenda-practicas/{id}/horas',   [RegistroHorasController::class, 'store'])->whereNumber('id');
            Route::delete('/agenda-practicas/horas/{id}', [RegistroHorasController::class, 'destroy'])->whereNumber('id');
        });

        // Taller de Ideas — IA: sugerencia de RA/CE y KPIs
        Route::middleware('throttle:10,1,startup-ia')->group(function () {
            Route::post('/startup/sugerir-ra-ce',           [MicroproyectoController::class, 'sugerirRaCe']);
            Route::post('/startup/sugerir-kpis',            [MicroproyectoController::class, 'sugerirKpis']);
            Route::post('/startup/sugerir-objetivos',       [MicroproyectoController::class, 'sugerirObjetivos']);
            Route::post('/startup/sugerir-fundamentacion',  [MicroproyectoController::class, 'sugerirFundamentacion']);
            Route::post('/startup/sugerir-metodologia',     [MicroproyectoController::class, 'sugerirMetodologia']);
        });

        // ── Gestión de equipos (docente) ──────────────────────────────────────
        // Nota: la creación de equipos y el listado con progreso viven en
        // EncuentroController (crearCodigo/workspace) — no duplicar aquí.
        Route::get('/startup/proyectos/{uuid}/equipos',         [EncuentroController::class, 'equiposDeProyecto']);
        // Workspace de un grupo en solo lectura para el docente (por id y con su sesión, sin token)
        Route::get('/startup/equipos/{id}/workspace',          [EquipoPublicoController::class, 'showDocente'])
            ->whereNumber('id');
        Route::get('/startup/proyectos/{uuid}/pantalla-acceso',[EquipoGestionController::class, 'pantallaAcceso']);
        Route::delete('/startup/equipos/{id}',                 [EquipoGestionController::class, 'destroy'])
            ->whereNumber('id');
        Route::patch('/startup/equipos/{id}/fase/{fase}/validar',  [EquipoGestionController::class, 'validarFase'])
            ->whereNumber('id')->whereNumber('fase');
        Route::patch('/startup/equipos/{id}/fase/{fase}/rechazar', [EquipoGestionController::class, 'rechazarFase'])
            ->whereNumber('id')->whereNumber('fase');
        Route::patch('/startup/equipos/{id}/evaluar',              [EquipoGestionController::class, 'evaluar'])
            ->whereNumber('id');

        // Diagnóstico final IA — mismo throttle que el resto de llamadas de generación (arriba)
        Route::middleware('throttle:10,1,diagnostico-final')
            ->post('/startup/equipos/{id}/diagnostico-final', [EquipoGestionController::class, 'diagnosticoFinal'])
            ->whereNumber('id');
        // Edición manual del diagnóstico — sin llamada externa, basta el throttle global
        Route::patch('/startup/equipos/{id}/diagnostico-final', [EquipoGestionController::class, 'actualizarDiagnosticoFinal'])
            ->whereNumber('id');
    });

    // ── app comercial (comercial.dualab.es): comercial (+ superadmin) ──────────
    // El comercial no pasa por el grupo 'docente': solo ve las empresas que capta él
    // (EmpresaCaptacionPolicy::ver). Rutas fijas antes que las paramétricas.
    Route::middleware('comercial')->prefix('comercial')->group(function () {
        Route::middleware('throttle:60,1,comercial-lectura')->group(function () {
            Route::get('/textos-legales',          [ComercialEmpresaController::class, 'textosLegales']);
            Route::get('/empresas',                [ComercialEmpresaController::class, 'index']);
            Route::get('/empresas/{id}',           [ComercialEmpresaController::class, 'show'])->whereNumber('id');
            Route::get('/empresas/{id}/borradores', [ComercialEmpresaController::class, 'borradores'])->whereNumber('id');
            Route::get('/papelera',                [ComercialEmpresaController::class, 'papelera']);
        });
        // A la papelera: pide la contraseña, así que cupo bajo contra fuerza bruta
        Route::middleware('throttle:5,1,comercial-papelera')
            ->delete('/empresas/{id}', [ComercialEmpresaController::class, 'destroy'])->whereNumber('id');
        Route::middleware('throttle:30,1,comercial-escritura')
            ->patch('/papelera/{id}/restaurar', [ComercialEmpresaController::class, 'restaurar'])->whereNumber('id');
        Route::middleware('throttle:30,1,comercial-escritura')->group(function () {
            Route::post('/empresas',                  [ComercialEmpresaController::class, 'store']);
            Route::put('/empresas/{id}',              [ComercialEmpresaController::class, 'update'])->whereNumber('id');
            Route::patch('/empresas/{id}/diagnostico', [ComercialEmpresaController::class, 'diagnostico'])->whereNumber('id');
        });
        // Llamada a OpenAI: mismo cupo que la generación del Generador de Retos
        Route::middleware('throttle:5,1,comercial-ia')
            ->post('/empresas/{id}/generar-retos', [ComercialEmpresaController::class, 'generarRetos'])->whereNumber('id');
        // Ideas de la IA para la entrevista y dictado de pago: cupos propios, no el de generar retos
        Route::middleware('throttle:10,1,comercial-sugerencias')
            ->post('/empresas/{id}/sugerencias', [ComercialEmpresaController::class, 'sugerencias'])->whereNumber('id');
        Route::middleware('throttle:20,1,comercial-dictado')
            ->post('/transcribir', [ComercialEmpresaController::class, 'transcribir']);
        // Formulario por correo: cada envío manda un email
        Route::middleware('throttle:5,1,comercial-formulario')->group(function () {
            Route::post('/formularios',                [ComercialEmpresaController::class, 'crearFormulario']);
            Route::post('/empresas/{id}/formulario',   [ComercialEmpresaController::class, 'enviarFormulario'])->whereNumber('id');
            Route::delete('/empresas/{id}/formulario', [ComercialEmpresaController::class, 'anularFormulario'])->whereNumber('id');
        });
        Route::middleware('throttle:10,1,comercial-firma')
            ->post('/empresas/{id}/firma', [ComercialEmpresaController::class, 'firmar'])->whereNumber('id');

        // Entrevista a centros educativos (ruta fija antes que la paramétrica)
        Route::middleware('throttle:60,1,comercial-lectura')->group(function () {
            Route::get('/centros/textos-legales', [ComercialCentroController::class, 'textosLegales']);
            Route::get('/centros',                [ComercialCentroController::class, 'index']);
            Route::get('/centros/{id}',           [ComercialCentroController::class, 'show'])->whereNumber('id');
        });
        Route::middleware('throttle:30,1,comercial-escritura')
            ->post('/centros', [ComercialCentroController::class, 'store']);
    });

    // ── DuaLab Central → Alumnado destacado para prácticas (P3b, solo superadmin) ──────
    Route::middleware(['practicas', 'throttle:60,1,central-destacados'])->prefix('central/destacados')->group(function () {
        Route::get('/',                    [DestacadoCentralController::class, 'index']);
        Route::put('/miembros/{id}',       [DestacadoCentralController::class, 'marcar'])->whereNumber('id');
    });

    // ── DuaLab Central → Primer contacto: formularios y correo de la web por su API firmada
    //    (red_dualab, central_dualab y superadmin) ──
    Route::middleware(['primer.contacto', 'throttle:60,1,central-primer-contacto'])->prefix('central/primer-contacto')->group(function () {
        Route::get('/formularios',         [PrimerContactoController::class, 'index']);
        Route::get('/formularios/{id}',    [PrimerContactoController::class, 'show'])->whereNumber('id');
        Route::patch('/formularios/{id}',  [PrimerContactoController::class, 'update'])->whereNumber('id');

        // Correo de info@ (específicas antes de las paramétricas)
        Route::post('/correo/enviar',                     [PrimerContactoCorreoController::class, 'enviar'])->middleware('throttle:20,1,central-correo-enviar');
        Route::post('/correo/envios/{id}/reintentar',     [PrimerContactoCorreoController::class, 'reintentar'])->whereNumber('id');
        Route::delete('/correo/envios/{id}',              [PrimerContactoCorreoController::class, 'descartar'])->whereNumber('id');
        // Papelera del correo: la ve y la borra del todo solo el superadmin (como Red DuaLab → Papelera)
        Route::middleware('superadmin')->group(function () {
            Route::get('/correo/papelera',                [PrimerContactoCorreoController::class, 'papelera']);
            Route::delete('/correo/papelera',             [PrimerContactoCorreoController::class, 'vaciarPapelera']);
            Route::delete('/correo/{id}',                 [PrimerContactoCorreoController::class, 'destroy'])->whereNumber('id');
        });
        Route::get('/correo',                             [PrimerContactoCorreoController::class, 'index']);
        Route::get('/correo/{id}',                        [PrimerContactoCorreoController::class, 'show'])->whereNumber('id');
        Route::patch('/correo/{id}',                      [PrimerContactoCorreoController::class, 'update'])->whereNumber('id');
        Route::get('/correo/{correo}/adjuntos/{adjunto}', [PrimerContactoCorreoController::class, 'adjunto'])->whereNumber(['correo', 'adjunto']);
    });

    // ── DuaLab Central → Historial: qué se hizo, quién y cuándo (superadmin y admin_central_dualab) ──
    Route::middleware(['admin.central', 'throttle:60,1,central-historial'])->group(function () {
        Route::get('/central/historial', [ActividadCentralController::class, 'index']);
        Route::get('/central/historial/envios/{id}', [ActividadCentralController::class, 'envio'])->whereNumber('id');
    });

    // ── DuaLab Central → Red DuaLab → Empresas y Centros: consulta de solo lectura (editar es de Base de datos) ──
    Route::middleware(['primer.contacto', 'throttle:60,1,central-consulta'])->prefix('central')->group(function () {
        Route::get('/empresas', [ConsultaCentralController::class, 'empresas']);
        Route::get('/centros',  [ConsultaCentralController::class, 'centros']);
    });

    // ── DuaLab Central → Primer contacto → Solicitudes de retos de docentes (red_dualab, central_dualab
    //    y superadmin; los datos para el generador, solo superadmin, que es quien genera) ──
    Route::middleware(['primer.contacto', 'throttle:60,1,central-solicitudes-reto'])->prefix('central/solicitudes-reto')->group(function () {
        Route::get('/',                [SolicitudRetoController::class, 'index']);
        // Respuestas de los docentes en info@ (por la referencia del asunto); específica antes de /{id}
        Route::get('/correos',         [SolicitudRetoCorreoController::class, 'index']);
        Route::get('/para-generador',  [SolicitudRetoController::class, 'paraGenerador'])->middleware('admin.central');
        Route::get('/{id}',            [SolicitudRetoController::class, 'show'])->whereNumber('id');
        Route::get('/{id}/correos',    [SolicitudRetoCorreoController::class, 'deSolicitud'])->whereNumber('id');
        Route::patch('/{id}',          [SolicitudRetoController::class, 'update'])->whereNumber('id');
        // Gestión de las peticiones (superadmin y admin_central_dualab): empresa, generar y publicar retos
        Route::middleware('admin.central')->group(function () {
            Route::get('/{id}/generador',   [SolicitudRetoController::class, 'generador'])->whereNumber('id');
            Route::get('/{id}/generacion',  [SolicitudRetoController::class, 'generacion'])->whereNumber('id');
            Route::get('/{id}/empresas',    [SolicitudRetoController::class, 'empresas'])->whereNumber('id');
            Route::post('/{id}/publicar',   [SolicitudRetoController::class, 'publicar'])->whereNumber('id');
            // A la papelera; restaurarla o borrarla del todo es de la Papelera, solo superadmin
            Route::delete('/{id}',          [SolicitudRetoController::class, 'destroy'])->whereNumber('id');
        });
        // Envían correo: cupo propio
        Route::middleware('throttle:20,1,central-solicitudes-reto-correo')->group(function () {
            Route::post('/',               [SolicitudRetoController::class, 'store']);
            Route::post('/{id}/reenviar',  [SolicitudRetoController::class, 'reenviar'])->whereNumber('id');
            Route::post('/{id}/avisar',    [SolicitudRetoController::class, 'avisar'])->whereNumber('id');
        });
    });

    // ── Revisión de lo captado por comerciales (Central → Red DuaLab: red_dualab, central_dualab y superadmin) ──
    Route::middleware(['red.dualab', 'throttle:60,1,captacion-admin'])->prefix('captacion')->group(function () {
        Route::get('/resumen',                          [CaptacionAdminController::class, 'resumen']);
        Route::get('/empresas',                         [CaptacionAdminController::class, 'empresas']);
        Route::put('/empresas/{id}/validar',            [CaptacionAdminController::class, 'validar'])->whereNumber('id');
        Route::post('/empresas/{id}/descartar',         [CaptacionAdminController::class, 'descartarEmpresa'])->whereNumber('id');
        Route::get('/empresas/{id}/coincidencias',      [CaptacionAdminController::class, 'coincidenciasEmpresa'])->whereNumber('id');
        Route::get('/centros',                          [CaptacionAdminController::class, 'centros']);
        Route::put('/centros/{id}/validar',             [CaptacionAdminController::class, 'validarCentro'])->whereNumber('id');
        Route::post('/centros/{id}/descartar',          [CaptacionAdminController::class, 'descartarCentro'])->whereNumber('id');
        Route::get('/centros/{id}/coincidencias',       [CaptacionAdminController::class, 'coincidenciasCentro'])->whereNumber('id');
        Route::get('/borradores',                       [CaptacionAdminController::class, 'borradores']);
        Route::get('/borradores/{uuid}',                [CaptacionAdminController::class, 'borrador'])->whereUuid('uuid');
        Route::post('/borradores/{uuid}/publicar',      [CaptacionAdminController::class, 'publicar'])->whereUuid('uuid');
        Route::delete('/borradores/{uuid}',             [CaptacionAdminController::class, 'descartar'])->whereUuid('uuid');
        Route::get('/firmas/{uuid}/imagen',             [CaptacionAdminController::class, 'imagenFirma'])->whereUuid('uuid');
        // A la papelera: acción crítica, superadmin y admin_central_dualab
        Route::middleware('admin.central')->group(function () {
            Route::delete('/empresas/{id}',             [CaptacionAdminController::class, 'eliminarEmpresa'])->whereNumber('id');
            Route::delete('/centros/{id}',              [CaptacionAdminController::class, 'eliminarCentro'])->whereNumber('id');
            Route::delete('/borradores/{uuid}/papelera', [CaptacionAdminController::class, 'eliminarBorrador'])->whereUuid('uuid');
        });
    });

    // ── Gestión de usuarios (solo admin) ─────────────────────────────
    // Superadmin, admin_central_dualab (todas las cuentas menos superadmin) y admin de centro (las de su centro)
    Route::middleware('usuarios')->prefix('admin/usuarios')->group(function () {
        Route::get('/',                       [AdminUserController::class, 'index']);
        Route::post('/',                      [AdminUserController::class, 'store']);
        Route::get('/papelera',               [AdminUserController::class, 'papelera']);
        // Throttle propio: implica cambio de contraseña de otra cuenta, más restrictivo que el resto del CRUD
        Route::middleware('throttle:20,1,admin-usuario-editar')
            ->patch('/{user}',                [AdminUserController::class, 'update']);
        Route::patch('/{user}/activar',       [AdminUserController::class, 'activar']);
        Route::patch('/{user}/bloquear',      [AdminUserController::class, 'toggleBloquear']);
        Route::delete('/{user}',              [AdminUserController::class, 'destroy']);
        Route::post('/{id}/restaurar',        [AdminUserController::class, 'restaurar']);
        Route::delete('/{id}/destruir',       [AdminUserController::class, 'destruir']);
        Route::patch('/{user}/centro',        [AdminUserController::class, 'asociarCentro'])->middleware('superadmin');
        Route::get('/{user}/modulos',         [ModulosDocenteController::class, 'delDocente']);
        Route::middleware('throttle:30,1,admin-usuario-modulos')
            ->put('/{user}/modulos',          [ModulosDocenteController::class, 'actualizarDelDocente']);
    });

});
