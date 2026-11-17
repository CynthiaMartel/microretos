<?php

namespace Tests\Feature;

use App\Mail\FormularioEmpresaMail;
use App\Mail\FormularioEmpresaRespondidoMail;
use App\Models\CaptacionOrigen;
use App\Models\EmpresaCaptacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * App comercial: formulario de la entrevista por correo (enlace público con token), dictado de
 * pago e ideas de la IA para la entrevista.
 *
 * Usa la BD `testing` de Sail (MySQL: varias migraciones son SQL de MySQL).
 */
class EntrevistaComercialFormularioIaTest extends TestCase
{
    use RefreshDatabase;

    private User $comercial;
    private EmpresaCaptacion $captacion;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('familias')->insertOrIgnore(['nombre' => 'Industrias Alimentarias']); // la crean las migraciones; por si acaso
        $this->comercial = $this->comercial('Carla', 'carla@test.local');
        $this->captacion = new EmpresaCaptacion([
            'nombre_comercial' => 'Panadería Sol', 'cif' => 'B12345678', 'email_general' => 'hola@panaderia.test',
            'sector' => null, 'actividad' => 'Pan y bollería artesanal', 'tamano' => '1-9',
        ]);
        $this->captacion->forceFill([
            'origen_id' => CaptacionOrigen::ENTREVISTA, 'creada_por_user_id' => $this->comercial->id,
            'estado' => EmpresaCaptacion::ESTADO_BORRADOR,
        ])->save();
    }

    private function comercial(string $nombre, string $email): User
    {
        return User::forceCreate([
            'name' => $nombre, 'email' => $email, 'password' => bcrypt('x'),
            'role' => User::ROLE_COMERCIAL, 'email_verified_at' => now(),
        ]);
    }

    /** Envía el formulario como el comercial y devuelve el token del enlace */
    private function enviarFormulario(): string
    {
        Sanctum::actingAs($this->comercial);
        $enlace = $this->postJson("/api/comercial/empresas/{$this->captacion->id}/formulario", ['email' => 'hola@panaderia.test'])
            ->assertOk()->json('enlace');

        return basename($enlace);
    }

    private function respuestasMinimas(): array
    {
        return ['diaANormal' => 'Hacemos pan cada mañana', 'friccionArea' => 'Los pedidos', 'friccionProblema' => 'Llegan por WhatsApp y se pierden'];
    }

    // ── Formulario por correo ────────────────────────────────────────────────

    public function test_el_comercial_envia_el_enlace_y_solo_se_guarda_su_huella(): void
    {
        Mail::fake();
        $token = $this->enviarFormulario();

        $this->assertSame(64, strlen($token));
        $this->captacion->refresh();
        $this->assertSame(hash('sha256', $token), $this->captacion->formulario_token_hash);
        $this->assertTrue($this->captacion->formulario_expira_at->isFuture());
        Mail::assertQueued(FormularioEmpresaMail::class, fn ($m) => $m->hasTo('hola@panaderia.test') && $m->enlace === config('captacion.url_app_comercial') . "/formulario/{$token}");
    }

    public function test_otro_comercial_no_puede_enviar_el_formulario_de_una_captacion_ajena(): void
    {
        Mail::fake();
        Sanctum::actingAs($this->comercial('Otro', 'otro@test.local'));

        $this->postJson("/api/comercial/empresas/{$this->captacion->id}/formulario", ['email' => 'x@y.test'])->assertNotFound();
        Mail::assertNothingQueued();
    }

    public function test_no_se_envia_el_formulario_de_una_entrevista_firmada(): void
    {
        Mail::fake();
        $this->captacion->forceFill(['estado' => EmpresaCaptacion::ESTADO_PENDIENTE_VALIDACION])->save();
        Sanctum::actingAs($this->comercial);

        $this->postJson("/api/comercial/empresas/{$this->captacion->id}/formulario", ['email' => 'x@y.test'])->assertForbidden();
    }

    public function test_el_comercial_crea_la_entrevista_solo_con_el_email(): void
    {
        Mail::fake();
        Sanctum::actingAs($this->comercial);

        $respuesta = $this->postJson('/api/comercial/formularios', ['email' => 'nueva@empresa.test'])->assertCreated();

        $nueva = EmpresaCaptacion::findOrFail($respuesta->json('data.id'));
        $this->assertNull($nueva->nombre_comercial);
        $this->assertSame('nueva@empresa.test', $nueva->email_general);
        $this->assertSame($this->comercial->id, $nueva->creada_por_user_id);
        $this->assertTrue($nueva->formularioAbierto());
        Mail::assertQueued(FormularioEmpresaMail::class, fn ($m) => $m->hasTo('nueva@empresa.test')
            && $m->envelope()->subject === 'Completa la entrevista para colaborar con DuaLab'
            && $m->envelope()->replyTo[0]->address === 'carla@test.local'
            // El nombre del comercial, nunca el usuario serializado
            && str_contains($m->render(), 'Soy Carla, del equipo de DuaLab') && !str_contains($m->render(), '"email"'));

        $this->postJson('/api/comercial/formularios', ['email' => 'no-es-un-email'])->assertUnprocessable();
    }

    public function test_el_enlace_solo_expone_datos_de_su_empresa_y_textos(): void
    {
        Mail::fake();
        $token = $this->enviarFormulario();
        $this->captacion->forceFill(['notas' => 'Nota interna de DuaLab'])->save();
        $this->app['auth']->forgetGuards();

        $datos = $this->getJson("/api/public/formulario-empresa/{$token}")->assertOk()->json('data');

        $this->assertSame(['empresa', 'expira_at', 'privacidad', 'textos_legales'], array_keys($datos));
        $this->assertSame('Panadería Sol', $datos['empresa']['nombre_comercial']);
        $this->assertArrayNotHasKey('notas', $datos['empresa']);
        $this->assertArrayNotHasKey('estado', $datos['empresa']);
        $this->assertStringNotContainsString('Nota interna', json_encode($datos));
        $this->assertStringNotContainsString('Carla', json_encode($datos));
    }

    public function test_la_empresa_completa_y_firma_la_entrevista_desde_el_enlace(): void
    {
        Mail::fake();
        Notification::fake();
        Storage::fake('local');
        $token = $this->enviarFormulario();
        $this->app['auth']->forgetGuards();

        // Sin aceptar la protección de datos no se guarda nada
        $this->putJson("/api/public/formulario-empresa/{$token}/empresa", $this->datosEmpresa())
            ->assertUnprocessable()->assertJsonValidationErrors(['aceptaPrivacidad', 'privacidadVersion']);

        // Firmar antes de rellenarlo: faltan datos
        $this->postJson("/api/public/formulario-empresa/{$token}/firma", $this->firma())->assertUnprocessable();

        $this->putJson("/api/public/formulario-empresa/{$token}/empresa", $this->datosEmpresa() + $this->privacidad())
            ->assertOk()->assertJsonPath('data.empresa.sector', 'Alimentación y agroalimentario');
        $this->putJson("/api/public/formulario-empresa/{$token}", ['diaANormal' => 'Hacemos pan'] + $this->privacidad())->assertOk();
        $this->assertSame('Hacemos pan', $this->captacion->refresh()->dia_a_normal);
        $this->putJson("/api/public/formulario-empresa/{$token}", $this->respuestasMinimas() + $this->privacidad())->assertOk();

        $this->postJson("/api/public/formulario-empresa/{$token}/firma", $this->firma())->assertOk();

        $this->captacion->refresh();
        $this->assertSame(EmpresaCaptacion::ESTADO_PENDIENTE_VALIDACION, $this->captacion->estado);
        $this->assertNotNull($this->captacion->formulario_respondido_at);
        $this->assertSame(config('captacion.formulario.privacidad.version'), $this->captacion->formulario_privacidad_version);
        $firma = $this->captacion->firmas()->firstOrFail();
        $this->assertSame('formulario_correo', $firma->snapshot['canal']);
        $this->assertSame($this->comercial->id, $firma->comercial_user_id);
        Mail::assertQueued(FormularioEmpresaRespondidoMail::class, fn ($m) => $m->hasTo('carla@test.local'));

        // Firmada: el enlace deja de admitir cambios
        $this->getJson("/api/public/formulario-empresa/{$token}")->assertStatus(410)->assertJson(['motivo' => 'respondido']);
        $this->putJson("/api/public/formulario-empresa/{$token}", ['diaANormal' => 'Otra cosa'] + $this->privacidad())->assertStatus(410);
        $this->postJson("/api/public/formulario-empresa/{$token}/firma", $this->firma())->assertStatus(410);
    }

    public function test_enlace_caducado_anulado_o_inexistente(): void
    {
        Mail::fake();
        $token = $this->enviarFormulario();

        $this->captacion->forceFill(['formulario_expira_at' => now()->subMinute()])->save();
        $this->getJson("/api/public/formulario-empresa/{$token}")->assertStatus(410)->assertJson(['motivo' => 'caducado']);

        $this->deleteJson("/api/comercial/empresas/{$this->captacion->id}/formulario")->assertOk();
        $this->getJson("/api/public/formulario-empresa/{$token}")->assertNotFound();

        $this->getJson('/api/public/formulario-empresa/' . str_repeat('a', 64))->assertNotFound();
    }

    private function privacidad(): array
    {
        return ['aceptaPrivacidad' => true, 'privacidadVersion' => config('captacion.formulario.privacidad.version')];
    }

    private function datosEmpresa(): array
    {
        return ['nombreComercial' => 'Panadería Sol', 'sector' => 'Alimentación y agroalimentario', 'familias' => ['Industrias Alimentarias'],
            'interesesFamilia' => ['Industrias Alimentarias' => 'Ayuda en el obrador']];
    }

    /** Firma válida: DNI con su letra, PNG de verdad y las versiones vigentes de los textos */
    private function firma(): array
    {
        $imagen = imagecreatetruecolor(200, 80);
        ob_start();
        imagepng($imagen);
        $png = (string) ob_get_clean();

        return [
            'firmanteNombre'   => 'Lucía Pérez',
            'firmanteDni'      => '12345678Z',
            'aceptaAvisoLegal' => true,
            'aceptaInfoRgpd'   => true,
            'cesiones'         => array_fill_keys(array_keys(config('captacion.cesiones')), false),
            'versiones'        => array_map(fn ($t) => $t['version'], config('captacion.textos') + config('captacion.cesiones')),
            'firma'            => 'data:image/png;base64,' . base64_encode($png),
        ];
    }

    // ── Respuestas a partir de ideas de la IA ────────────────────────────────

    public function test_se_anotan_las_respuestas_con_ia_y_se_quitan_si_la_empresa_las_cambia(): void
    {
        Mail::fake();
        Sanctum::actingAs($this->comercial);
        $this->patchJson("/api/comercial/empresas/{$this->captacion->id}/diagnostico", $this->respuestasMinimas() + [
            'respuestasIa' => ['friccionArea', 'friccionProblema'],
        ])->assertOk()->assertJsonPath('data.respuestas_ia', ['friccionArea', 'friccionProblema']);

        $this->patchJson("/api/comercial/empresas/{$this->captacion->id}/diagnostico", $this->respuestasMinimas() + [
            'respuestasIa' => ['noEsUnaPregunta'],
        ])->assertUnprocessable();

        // La empresa reescribe una de ellas en el formulario: deja de contar como «con IA»
        $token = $this->enviarFormulario();
        $this->app['auth']->forgetGuards();
        $this->putJson("/api/public/formulario-empresa/{$token}", ['friccionArea' => 'Las facturas', 'friccionProblema' => 'Llegan por WhatsApp y se pierden'] + $this->privacidad())->assertOk();
        $this->assertSame(['friccionProblema'], $this->captacion->refresh()->respuestas_ia);
    }

    public function test_las_ideas_de_la_ia_se_limpian_y_se_cachean(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode([
            'friccionArea'  => ['preguntas' => ['¿Los pedidos llegan por varios sitios?', 5], 'ejemplos' => ['<b>Los pedidos</b> por WhatsApp']],
            'consecuencias' => ['preguntas' => [], 'ejemplos' => [], 'opciones' => ['Pérdida de tiempo', 'Inventada']],
            'otraClave'     => ['preguntas' => ['x']],
        ])]]]])]);
        Sanctum::actingAs($this->comercial);

        $datos = $this->postJson("/api/comercial/empresas/{$this->captacion->id}/sugerencias", ['diaANormal' => 'Pan'])
            ->assertOk()->json('data');

        $this->assertSame(['¿Los pedidos llegan por varios sitios?'], $datos['friccionArea']['preguntas']);
        $this->assertSame(['Los pedidos por WhatsApp'], $datos['friccionArea']['ejemplos']);
        $this->assertSame(['Pérdida de tiempo'], $datos['consecuencias']['opciones']);
        $this->assertArrayNotHasKey('otraClave', $datos);
        $this->assertCount(7, $datos);

        // Mismo contexto: no se vuelve a llamar a la IA
        $this->postJson("/api/comercial/empresas/{$this->captacion->id}/sugerencias", ['diaANormal' => 'Pan'])->assertOk();
        Http::assertSentCount(1);
        // Lo que se envía a la IA no lleva el nombre ni el CIF de la empresa
        Http::assertSent(fn ($r) => !str_contains($r->body(), 'Panader') && !str_contains($r->body(), 'B12345678'));
    }

    public function test_ideas_de_la_ia_de_una_captacion_ajena(): void
    {
        Http::fake();
        Sanctum::actingAs($this->comercial('Otro', 'otro@test.local'));

        $this->postJson("/api/comercial/empresas/{$this->captacion->id}/sugerencias", [])->assertNotFound();
        Http::assertNothingSent();
    }

    // ── Dictado de pago ──────────────────────────────────────────────────────

    public function test_el_dictado_de_pago_devuelve_el_texto(): void
    {
        Http::fake(['api.openai.com/v1/audio/transcriptions' => Http::response(['text' => 'Los pedidos se pierden.'])]);
        Sanctum::actingAs($this->comercial);

        $this->post('/api/comercial/transcribir', ['audio' => UploadedFile::fake()->create('dictado.webm', 50, 'audio/webm')], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('data.texto', 'Los pedidos se pierden.');

        $this->post('/api/comercial/transcribir', ['audio' => UploadedFile::fake()->create('nota.pdf', 50, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertUnprocessable();
    }

    public function test_el_dictado_de_pago_no_esta_abierto_a_otros_roles(): void
    {
        Http::fake();
        Sanctum::actingAs(User::forceCreate([
            'name' => 'Doc', 'email' => 'doc@test.local', 'password' => bcrypt('x'),
            'role' => User::ROLE_DOCENTE, 'email_verified_at' => now(),
        ]));

        $this->post('/api/comercial/transcribir', ['audio' => UploadedFile::fake()->create('dictado.webm', 50, 'audio/webm')], ['Accept' => 'application/json'])
            ->assertForbidden();
        Http::assertNothingSent();
    }
}
