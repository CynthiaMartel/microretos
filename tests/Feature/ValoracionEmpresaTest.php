<?php

namespace Tests\Feature;

use App\Models\Encuentro;
use App\Models\EncuentroValoracionEmpresa;
use App\Models\Equipo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Valoración final de la empresa (fase P3 en TAREAS_PENDIENTES.md).
 *
 * - El docente con edición pide la valoración (enlace con token que caduca) y la marca enviada.
 * - La empresa ve la propuesta y las soluciones de los grupos, sin datos del alumnado, y
 *   responde una sola vez; el docente recibe un aviso.
 * - Enlace caducado → 410; un colaborador de solo lectura no ve el token.
 *
 * Usa la BD `testing` de Sail (MySQL: varias migraciones son SQL de MySQL).
 */
class ValoracionEmpresaTest extends TestCase
{
    use RefreshDatabase;

    private User $ana;
    private User $luis;
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

    private function pedir(): string
    {
        Sanctum::actingAs($this->ana);

        return $this->postJson("/api/encuentros/{$this->encuentro->id}/valoracion-empresa")
            ->assertOk()->assertJsonPath('solicitada', true)->json('token');
    }

    private function respuesta(): array
    {
        return [
            'valoracion_global' => 4,
            'respuestas' => ['responde_necesidad' => 'Sí', 'aplicable' => 'Parcialmente', 'calidad' => 'Sí', 'interes_plan_practicas' => 'Quizá'],
            'comentarios' => '<b>Buen trabajo</b>',
        ];
    }

    public function test_el_docente_pide_la_valoracion_y_ve_el_progreso_de_los_grupos(): void
    {
        $token = $this->pedir();
        $this->assertNotEmpty($token);

        $this->getJson("/api/encuentros/{$this->encuentro->id}/valoracion-empresa")
            ->assertOk()
            ->assertJsonPath('grupos_total', 2)
            ->assertJsonPath('grupos_con_entrega', 1)
            ->assertJsonPath('token', $token);

        $this->patchJson("/api/encuentros/{$this->encuentro->id}/valoracion-empresa/enviada")
            ->assertOk()->assertJsonPath('enviado_en', fn ($v) => $v !== null);
    }

    public function test_un_colaborador_de_solo_lectura_no_ve_el_token_ni_puede_pedirla(): void
    {
        $this->pedir();

        Sanctum::actingAs($this->luis);
        $this->getJson("/api/encuentros/{$this->encuentro->id}/valoracion-empresa")
            ->assertOk()->assertJsonPath('token', null)->assertJsonPath('solicitada', true);
        $this->postJson("/api/encuentros/{$this->encuentro->id}/valoracion-empresa")->assertNotFound();
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
