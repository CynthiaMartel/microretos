<?php

namespace Tests\Feature;

use App\Models\CaptacionOrigen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as PeticionHttp;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * DuaLab Central → «Primer contacto»: la bandeja de formularios de la web por su API firmada.
 * - Solo el superadmin; la petición a la web va firmada y la respuesta se filtra por lista blanca.
 * - Cada formulario se cruza con las captaciones de aquí (origen 2, origen_ref = id).
 * - Si la web no responde, 502 con un mensaje claro.
 */
class PrimerContactoCentralTest extends TestCase
{
    use RefreshDatabase;

    private const SECRETO = 'secreto-de-prueba-con-mas-de-32-caracteres';
    private User $dualab;
    private User $docente;
    private User $atencion;

    protected function setUp(): void
    {
        parent::setUp();
        config(['frontoffice.api_url' => 'https://web.test/api-backend/public', 'frontoffice.central_secreto' => self::SECRETO]);

        $centro = DB::table('centro_educativo')->insertGetId(['nombre' => 'IES A']);
        $crear = fn (string $n, int $rol) => User::forceCreate([
            'name' => $n, 'email' => Str::slug($n) . '@test.local', 'password' => bcrypt('x'),
            'role' => $rol, 'centro_educativo_id' => $rol === User::ROLE_SUPERADMIN ? null : $centro, 'email_verified_at' => now(),
        ]);
        $this->dualab  = $crear('DuaLab', User::ROLE_SUPERADMIN);
        $this->docente = $crear('Ana', User::ROLE_DOCENTE);
        $this->atencion = $crear('Laura', User::ROLE_RED_DUALAB);
    }

    private function formulario(int $id, array $extra = []): array
    {
        return array_merge([
            'id' => $id, 'formulario' => 'empresa', 'formulario_nombre' => 'Empresas', 'accion' => 'info',
            'accion_nombre' => 'Pide información', 'nombre' => 'Ana', 'apellidos' => 'Pérez', 'email' => 'ana@empresa.test',
            'telefono' => null, 'cargo' => null, 'pagina_origen' => '/contacto', 'estado' => 'nuevo',
            'leido_en' => null, 'created_at' => '2026-11-17T10:00:00Z', 'campo_interno' => 'no debe salir',
        ], $extra);
    }

    public function test_lista_firmada_filtrada_y_cruzada_con_captaciones(): void
    {
        DB::table('empresa_captaciones')->insert([
            'origen_id' => CaptacionOrigen::FORMULARIO, 'origen_ref' => '7', 'estado' => 'pendiente_validacion',
            'nombre_comercial' => 'Empresa Test', 'created_at' => now(), 'updated_at' => now(),
        ]);
        Http::fake(['web.test/*' => Http::response([
            'data' => [$this->formulario(7), $this->formulario(8, ['formulario' => 'centro'])],
            'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 25, 'total' => 2, 'links' => ['x']],
            'resumen' => ['por_estado' => ['nuevo' => 2, 'leido' => 0, 'archivado' => 0], 'por_formulario' => [['clave' => 'empresa', 'nombre' => 'Empresas', 'total' => 1]]],
            'acciones' => ['info' => 'Pide información'],
        ])]);

        Sanctum::actingAs($this->dualab);
        $r = $this->getJson('/api/central/primer-contacto/formularios?estado=nuevo&buscar=ana')->assertOk()
            ->assertJsonPath('data.0.captacion.tipo', 'empresa')
            ->assertJsonPath('data.0.captacion.estado', 'pendiente_validacion')
            ->assertJsonPath('data.1.captacion', null)
            ->assertJsonPath('resumen.por_estado.nuevo', 2)
            ->assertJsonMissingPath('data.0.campo_interno')
            ->assertJsonMissingPath('meta.links');

        Http::assertSent(function (PeticionHttp $p) {
            parse_str((string) parse_url($p->url(), PHP_URL_QUERY), $q);
            ksort($q);
            $cadena = implode("\n", [$p->header('X-DuaLab-Fecha')[0], 'GET', '/api/integraciones/central/formularios',
                http_build_query($q), $p->header('X-DuaLab-Usuario')[0], '']);

            return str_starts_with($p->url(), 'https://web.test/api-backend/public/api/integraciones/central/formularios?')
                && $q['estado'] === 'nuevo' && $q['buscar'] === 'ana'
                && $p->header('X-DuaLab-Usuario')[0] === 'DuaLab'
                && hash_equals(hash_hmac('sha256', $cadena, self::SECRETO), $p->header('X-DuaLab-Firma')[0]);
        });
    }

    public function test_solo_atencion_al_cliente_y_superadmin(): void
    {
        Http::fake(['web.test/*' => Http::response(['data' => [], 'meta' => [], 'resumen' => []])]);
        $this->getJson('/api/central/primer-contacto/formularios')->assertUnauthorized();
        Sanctum::actingAs($this->docente);
        $this->getJson('/api/central/primer-contacto/formularios')->assertForbidden();
        $this->getJson('/api/central/primer-contacto/correo')->assertForbidden();
        Http::assertNothingSent();

        Sanctum::actingAs($this->atencion);
        $this->getJson('/api/central/primer-contacto/formularios')->assertOk();
        // Red DuaLab lleva Red DuaLab (captaciones incluidas), no Seguimiento de prácticas
        $this->getJson('/api/central/destacados')->assertForbidden();
        $this->getJson('/api/captacion/resumen')->assertOk();
        Http::assertSent(fn (PeticionHttp $p) => $p->header('X-DuaLab-Usuario')[0] === 'Laura');
    }

    public function test_correo_enviar_reenvia_adjuntos_en_base64_firmados(): void
    {
        Http::fake(['web.test/*' => Http::response(['id' => 5, 'estado' => 'pendiente'], 202)]);
        Sanctum::actingAs($this->atencion);
        $this->post('/api/central/primer-contacto/correo/enviar', [
            'tipo' => 'responder', 'correo_id' => 3, 'contacto_id' => 7, 'para' => ['empresa@ejemplo.com'],
            'asunto' => 'Re: hola', 'texto' => 'Gracias', 'adjuntos' => [UploadedFile::fake()->createWithContent('nota.txt', 'hola')],
        ], ['Accept' => 'application/json'])->assertStatus(202)->assertJsonPath('id', 5);

        Http::assertSent(function (PeticionHttp $p) {
            $cuerpo = json_decode($p->body(), true);
            $cadena = implode("\n", [$p->header('X-DuaLab-Fecha')[0], 'POST', '/api/integraciones/central/correo/enviar', '', 'Laura', $p->body()]);

            return $p->method() === 'POST' && $cuerpo['contacto_id'] === 7 && $cuerpo['adjuntos_b64'][0]['nombre'] === 'nota.txt'
                && base64_decode($cuerpo['adjuntos_b64'][0]['contenido']) === 'hola' && !isset($cuerpo['adjuntos'])
                && hash_equals(hash_hmac('sha256', $cadena, self::SECRETO), $p->header('X-DuaLab-Firma')[0]);
        });
    }

    public function test_correo_errores_de_la_web_y_adjuntos_no_permitidos(): void
    {
        Http::fake(['web.test/*' => Http::response(['message' => 'Límite', 'errors' => ['para' => ['Se ha alcanzado el límite.']]], 422)]);
        Sanctum::actingAs($this->dualab);
        $base = ['tipo' => 'nuevo', 'para' => ['a@ejemplo.com'], 'asunto' => 'Hola', 'texto' => 'x'];
        $this->postJson('/api/central/primer-contacto/correo/enviar', $base)->assertStatus(422)
            ->assertJsonPath('errors.para.0', 'Se ha alcanzado el límite.');

        // Un .exe no llega ni a la web
        Http::fake();
        $this->post('/api/central/primer-contacto/correo/enviar', $base + ['adjuntos' => [UploadedFile::fake()->create('virus.exe', 10)]],
            ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('adjuntos.0');
        Http::assertNothingSent();
    }

    public function test_correo_ficha_y_adjunto_como_descarga(): void
    {
        Http::fake([
            'web.test/*/correo/3/adjuntos/4' => Http::response('%PDF', 200, ['Content-Disposition' => 'attachment; filename=informe.pdf', 'Content-Type' => 'application/pdf']),
            'web.test/*/correo/3' => Http::response(['id' => 3, 'asunto' => 'Hola', 'de_email' => 'a@b.es', 'html' => '<p>Hola</p>', 'raro' => 'x',
                'adjuntos' => [['id' => 4, 'nombre' => 'informe.pdf', 'tamano' => 4, 'disponible' => true, 'ruta' => '/secreto']]]),
        ]);
        Sanctum::actingAs($this->atencion);
        $this->getJson('/api/central/primer-contacto/correo/3')->assertOk()
            ->assertJsonPath('data.html', '<p>Hola</p>')->assertJsonMissingPath('data.raro')
            ->assertJsonMissingPath('data.adjuntos.0.ruta');
        $this->get('/api/central/primer-contacto/correo/3/adjuntos/4')->assertOk()
            ->assertHeader('Content-Type', 'application/octet-stream')
            ->assertHeader('Content-Disposition', 'attachment; filename=informe.pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_filtros_fuera_de_la_lista_blanca_se_rechazan(): void
    {
        Http::fake();
        Sanctum::actingAs($this->dualab);
        $this->getJson('/api/central/primer-contacto/formularios?estado=borrado')->assertUnprocessable();
        $this->getJson('/api/central/primer-contacto/formularios?per_page=500')->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_ficha_con_datos_planos_y_404_si_no_existe(): void
    {
        Http::fake([
            'web.test/*/formularios/7' => Http::response($this->formulario(7, [
                'datos' => ['empresa' => 'Empresa Test', 'familias' => ['Informática', 'Comercio'], 'anidado' => ['a' => ['b' => 1]]],
                'respuestas' => [['id' => 3, 'asunto' => 'Re: info', 'estado' => 'enviado', 'fecha' => '2026-11-17', 'por' => 'Laura']],
            ])),
            'web.test/*/formularios/9' => Http::response(['message' => 'No encontrado'], 404),
        ]);
        Sanctum::actingAs($this->dualab);
        $this->getJson('/api/central/primer-contacto/formularios/7')->assertOk()
            ->assertJsonPath('data.datos.empresa', 'Empresa Test')
            ->assertJsonPath('data.datos.familias', 'Informática, Comercio')
            ->assertJsonPath('data.respuestas.0.asunto', 'Re: info');
        $this->getJson('/api/central/primer-contacto/formularios/9')->assertNotFound();
    }

    public function test_cambiar_estado_envia_cuerpo_firmado(): void
    {
        Http::fake(['web.test/*' => Http::response($this->formulario(7, ['estado' => 'archivado']))]);
        Sanctum::actingAs($this->dualab);
        $this->patchJson('/api/central/primer-contacto/formularios/7', ['estado' => 'archivado'])->assertOk()
            ->assertJsonPath('data.estado', 'archivado');
        $this->patchJson('/api/central/primer-contacto/formularios/7', ['estado' => 'otro'])->assertUnprocessable();

        Http::assertSent(fn (PeticionHttp $p) => $p->method() === 'PATCH' && $p->body() === '{"estado":"archivado"}'
            && hash_equals(hash_hmac('sha256', implode("\n", [$p->header('X-DuaLab-Fecha')[0], 'PATCH',
                '/api/integraciones/central/formularios/7', '', 'DuaLab', '{"estado":"archivado"}']), self::SECRETO), $p->header('X-DuaLab-Firma')[0]));
    }

    public function test_papelera_del_correo_solo_superadmin_y_en_el_historial(): void
    {
        Http::fake([
            'web.test/*/correo/papelera' => Http::response(['eliminados' => 3]),
            'web.test/*/correo/5' => fn (PeticionHttp $p) => $p->method() === 'DELETE' ? Http::response(null, 204) : Http::response(['id' => 5, 'estado' => 'papelera', 'asunto' => 'Hola']),
            'web.test/*/correo/6' => Http::response(['message' => 'no'], 422),
            'web.test/*/correo*' => Http::response(['data' => [['id' => 5, 'asunto' => 'Hola', 'de_email' => 'a@b.test', 'texto' => 'no debe salir']], 'meta' => ['total' => 1]]),
        ]);

        // Red DuaLab la manda a la papelera, pero no la ve ni la vacía
        Sanctum::actingAs($this->atencion);
        $this->patchJson('/api/central/primer-contacto/correo/5', ['estado' => 'papelera'])->assertOk();
        $this->getJson('/api/central/primer-contacto/correo/papelera')->assertForbidden();
        $this->deleteJson('/api/central/primer-contacto/correo/5')->assertForbidden();
        $this->deleteJson('/api/central/primer-contacto/correo/papelera')->assertForbidden();

        Sanctum::actingAs($this->dualab);
        $this->getJson('/api/central/primer-contacto/correo/papelera')->assertOk()
            ->assertJsonPath('data.0.de', 'a@b.test')->assertJsonMissingPath('data.0.texto');
        $this->deleteJson('/api/central/primer-contacto/correo/5')->assertNoContent();
        $this->deleteJson('/api/central/primer-contacto/correo/6')->assertUnprocessable();
        $this->deleteJson('/api/central/primer-contacto/correo/papelera')->assertOk()->assertJsonPath('eliminados', 3);

        $this->assertSame(1, DB::table('actividad_central')->where('accion', 'correo_papelera')->where('critica', false)->count());
        $this->assertSame(2, DB::table('actividad_central')->whereIn('accion', ['correo_borrar', 'correo_vaciar'])->where('critica', true)->count());
        $this->assertSame(0, DB::table('actividad_central')->where('descripcion', 'like', '%a@b.test%')->count());
    }

    public function test_web_caida_o_sin_configurar_da_502(): void
    {
        Http::fake(['web.test/*' => Http::response(['error' => 'x'], 500)]);
        Sanctum::actingAs($this->dualab);
        $this->getJson('/api/central/primer-contacto/formularios')->assertStatus(502)->assertJsonStructure(['error']);

        config(['frontoffice.central_secreto' => 'corto']);
        $this->getJson('/api/central/primer-contacto/formularios')->assertStatus(502);
    }
    public function test_envios_quedan_en_el_historial_y_se_abren_con_su_contenido(): void
    {
        Http::fake([
            'web.test/*/correo/enviar' => Http::response(['id' => 5, 'estado' => 'pendiente'], 202),
            'web.test/*/correo/envios/5' => Http::response([
                'id' => 5, 'tipo' => 'nuevo', 'para' => ['empresa@ejemplo.com'], 'cc' => [], 'cco' => [],
                'asunto' => 'Propuesta', 'texto' => 'Hola', 'estado' => 'enviado', 'adjuntos' => [], 'interno' => 'x',
            ]),
            'web.test/*/correo/envios/6' => Http::response(['error' => 'no'], 404),
        ]);
        Sanctum::actingAs($this->atencion);
        $this->postJson('/api/central/primer-contacto/correo/enviar', [
            'tipo' => 'nuevo', 'para' => ['empresa@ejemplo.com'], 'asunto' => 'Propuesta', 'texto' => 'Hola',
        ])->assertStatus(202);

        // En el historial: quién y el asunto, sin direcciones; y el envío para abrirlo
        Sanctum::actingAs($this->dualab);
        $this->getJson('/api/central/historial?seccion=correo')->assertOk()
            ->assertJsonPath('data.0.quien', 'Laura')
            ->assertJsonPath('data.0.envio_id', 5)
            ->assertJsonPath('data.0.descripcion', 'Envió un correo desde info@: «Propuesta».');
        $this->assertDatabaseMissing('actividad_central', ['descripcion' => 'empresa@ejemplo.com']);

        $this->getJson('/api/central/historial/envios/5')->assertOk()
            ->assertJsonPath('para', ['empresa@ejemplo.com'])->assertJsonPath('texto', 'Hola')
            ->assertJsonMissingPath('interno');
        $this->getJson('/api/central/historial/envios/6')->assertNotFound();

        // El historial solo lo ven superadmin y Admin Central DuaLab
        Sanctum::actingAs($this->atencion);
        $this->getJson('/api/central/historial/envios/5')->assertForbidden();
    }
}
