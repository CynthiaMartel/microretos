<?php

namespace Tests\Feature;

use App\Mail\ValoracionEmpresaMail;
use App\Models\Encuentro;
use App\Models\EncuentroValoracionEmpresa;
use App\Models\Equipo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Valoración final de la empresa (fase P3 en TAREAS_PENDIENTES.md).
 *
 * - DuaLab, desde Central, genera el enlace (token que caduca) y lo envía a la empresa por info@ o
 *   lo marca enviado; el docente solo ve el estado y la respuesta, sin el enlace.
 * - La empresa ve la propuesta y las soluciones de los grupos, sin datos del alumnado, y
 *   responde una sola vez; el docente recibe un aviso.
 * - Enlace caducado → 410.
 *
 * Usa la BD `testing` de Sail (MySQL: varias migraciones son SQL de MySQL).
 */
class ValoracionEmpresaTest extends TestCase
{
    use RefreshDatabase;

    private User $ana;
    private User $luis;
    private User $red;      // Red DuaLab (Central)
    private Encuentro $encuentro;

    protected function setUp(): void
    {
        parent::setUp();

        $centro = DB::table('centro_educativo')->insertGetId(['nombre' => 'IES A']);
        $crear = fn (string $n) => User::forceCreate([
            'name' => $n, 'email' => Str::slug($n) . '@test.local', 'password' => bcrypt('x'),
            'role' => User::ROLE_DOCENTE, 'centro_educativo_id' => $centro, 'email_verified_at' => now(),
        ]);
        $this->ana  = $crear('Ana');
        $this->red  = User::forceCreate(['name' => 'Red', 'email' => 'red@test.local', 'password' => bcrypt('x'),
            'role' => User::ROLE_RED_DUALAB, 'email_verified_at' => now()]);
        $this->luis = $crear('Luis');

        $propuesta = DB::table('microproyectos')->insertGetId([
            'uuid' => (string) Str::uuid(), 'titulo' => 'Propuesta de Ana', 'estado' => 'validado',
            'user_id' => $this->ana->id, 'centro_id' => $centro,
            'diseno_reto' => json_encode(['pregunta_reto' => '¿Cómo podríamos…?']),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->encuentro = Encuentro::create([
            'user_id' => $this->ana->id, 'microproyecto_id' => $propuesta, 'fecha' => '2026-11-02',
            'centro_educativo_id' => $centro, 'curso' => '1º', 'grupo' => 'A', 'num_equipos' => 2,
        ]);
        // Luis: colaborador de solo lectura
        $this->encuentro->colaboradores()->attach($this->luis->id, ['puede_editar' => false]);

        // Grupo 1 entregó en F3 (con una URL peligrosa que no debe llegar a la empresa); grupo 2 no
        $g1 = Equipo::create(['microproyecto_id' => $propuesta, 'encuentro_id' => $this->encuentro->id, 'nombre' => 'Equipo 1', 'numero_equipo' => 1]);
        $g1->miembros()->create(['alias' => 'Lucía Panda Azul']);
        $g1->fases()->create(['numero_fase' => 3, 'completada' => true,
            'datos' => ['descripcion_entregable' => 'Nuestra app', 'url_entregable' => 'javascript:alert(1)']]);
        $g2 = Equipo::create(['microproyecto_id' => $propuesta, 'encuentro_id' => $this->encuentro->id, 'nombre' => 'Equipo 2', 'numero_equipo' => 2]);
        $g2->miembros()->create(['alias' => 'Mario Panda Azul']);
    }

    // DuaLab genera el enlace desde Central
    private function pedir(): string
    {
        Sanctum::actingAs($this->red);

        return $this->postJson("/api/central/valoraciones-empresa/{$this->encuentro->id}")
            ->assertOk()->assertJsonPath('data.solicitada', true)->json('data.token');
    }

    private function respuesta(): array
    {
        return [
            'valoracion_global' => 4,
            'respuestas' => ['responde_necesidad' => 'Sí', 'aplicable' => 'Parcialmente', 'calidad' => 'Sí', 'interes_plan_practicas' => 'Quizá'],
            'comentarios' => '<b>Buen trabajo</b>',
        ];
    }

    public function test_dualab_genera_el_enlace_y_el_docente_solo_ve_el_estado(): void
    {
        $token = $this->pedir();
        $this->assertNotEmpty($token);

        // El docente ve el progreso y que está pedida, pero no el enlace ni puede generarlo
        Sanctum::actingAs($this->ana);
        $this->getJson("/api/encuentros/{$this->encuentro->id}/valoracion-empresa")
            ->assertOk()->assertJsonPath('grupos_total', 2)->assertJsonPath('grupos_con_entrega', 1)
            ->assertJsonPath('solicitada', true)->assertJsonMissingPath('token');
        $this->postJson("/api/encuentros/{$this->encuentro->id}/valoracion-empresa")->assertStatus(405);
        $this->getJson('/api/central/valoraciones-empresa')->assertForbidden();
        $this->postJson("/api/central/valoraciones-empresa/{$this->encuentro->id}")->assertForbidden();
    }

    public function test_dualab_envia_las_soluciones_a_la_empresa_por_correo(): void
    {
        $empresa = DB::table('empresas')->insertGetId(['nombre_comercial' => 'Gestoría Sur', 'sector' => 'Servicios', 'email_contacto' => 'contacto@gestoria.test']);
        DB::table('microproyectos')->where('id', $this->encuentro->microproyecto_id)->update(['empresa_id' => $empresa]);
        Mail::fake();
        Sanctum::actingAs($this->red);

        $fila = collect($this->getJson('/api/central/valoraciones-empresa')->assertOk()->json('data'))
            ->firstWhere('encuentro_id', $this->encuentro->id);
        $this->assertSame(['Gestoría Sur', 'contacto@gestoria.test', 1, false], [$fila['empresa_nombre'], $fila['empresa_email'], $fila['grupos_con_entrega'], $fila['solicitada']]);

        // Sin enlace todavía: se genera al enviar
        $this->postJson("/api/central/valoraciones-empresa/{$this->encuentro->id}/enviar", ['email' => 'no-es-un-email'])->assertJsonValidationErrors('email');
        $datos = $this->postJson("/api/central/valoraciones-empresa/{$this->encuentro->id}/enviar", ['email' => 'contacto@gestoria.test', 'mensaje' => 'Gracias'])
            ->assertOk()->json('data');
        $this->assertTrue($datos['solicitada']);
        $this->assertNotNull($datos['enviado_en']);
        Mail::assertQueued(ValoracionEmpresaMail::class, fn ($m) => $m->hasTo('contacto@gestoria.test')
            && str_ends_with($m->enlace, '/valoracion/' . $datos['token']) && $m->empresa === 'Gestoría Sur');

        // Queda en el historial de Central
        $this->assertDatabaseHas('actividad_central', ['seccion' => 'empresas', 'accion' => 'enviar_soluciones']);

        // Valorada: ya no se envía
        $this->postJson("/api/valoracion-empresa/{$datos['token']}", $this->respuesta())->assertOk();
        Sanctum::actingAs($this->red);
        $this->postJson("/api/central/valoraciones-empresa/{$this->encuentro->id}/enviar", ['email' => 'contacto@gestoria.test'])->assertUnprocessable();
    }

    public function test_la_empresa_ve_las_soluciones_sin_datos_del_alumnado_ni_urls_peligrosas(): void
    {
        $token = $this->pedir();

        $res = $this->getJson("/api/valoracion-empresa/{$token}")->assertOk()
            ->assertJsonPath('propuesta.titulo', 'Propuesta de Ana')
            ->assertJsonPath('grupos.0.entrega.descripcion', 'Nuestra app')
            ->assertJsonPath('grupos.0.entrega.url', null)
            ->assertJsonPath('grupos.1.entregada', false);

        $json = $res->getContent();
        $this->assertStringNotContainsString('Panda', $json, 'Nunca se exponen alias del alumnado');
        $this->assertStringNotContainsString($token, $json, 'El token no vuelve en la respuesta');
    }

    public function test_la_empresa_responde_una_sola_vez_y_avisa_al_docente(): void
    {
        $token = $this->pedir();

        $this->postJson("/api/valoracion-empresa/{$token}", $this->respuesta())->assertOk();
        $this->postJson("/api/valoracion-empresa/{$token}", $this->respuesta())->assertStatus(409);

        $v = EncuentroValoracionEmpresa::first();
        $this->assertSame(4, $v->valoracion_global);
        $this->assertSame('Buen trabajo', $v->comentarios);
        $this->assertNotNull($v->respondido_en);
        $this->assertSame(1, $this->ana->notifications()->where('data->tipo', 'propuesta_valorada')->count());
    }

    public function test_respuesta_con_claves_u_opciones_fuera_de_lista_se_rechaza(): void
    {
        $token = $this->pedir();
        $mala = $this->respuesta();
        $mala['respuestas']['interes_plan_practicas'] = 'Parcialmente'; // no es opción de esta pregunta
        $mala['respuestas']['inventada'] = 'Sí';

        $this->postJson("/api/valoracion-empresa/{$token}", $mala)->assertUnprocessable();
    }

    public function test_probar_tokens_al_azar_desde_una_ip_acaba_limitado(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->getJson('/api/valoracion-empresa/' . Str::random(48))->assertNotFound();
        }
        $this->getJson('/api/valoracion-empresa/' . Str::random(48))->assertStatus(429);
    }

    public function test_enlace_caducado_da_410_y_pedirla_de_nuevo_renueva_el_token(): void
    {
        $token = $this->pedir();
        EncuentroValoracionEmpresa::query()->update(['token_expira_en' => now()->subDay()]);

        $this->getJson("/api/valoracion-empresa/{$token}")->assertStatus(410);
        $this->postJson("/api/valoracion-empresa/{$token}", $this->respuesta())->assertStatus(410);

        $nuevo = $this->pedir();
        $this->assertNotSame($token, $nuevo);
        $this->getJson("/api/valoracion-empresa/{$nuevo}")->assertOk();
        $this->getJson("/api/valoracion-empresa/{$token}")->assertNotFound();
    }
}
