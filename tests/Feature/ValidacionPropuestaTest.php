<?php

namespace Tests\Feature;

use App\Models\Microproyecto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Validación de la propuesta (Taller de ideas, fase P1 en TAREAS_PENDIENTES.md).
 *
 * - La validan el docente o la empresa: cualquiera de las dos la lleva a 'validado' (y al aula).
 * - Desvalidar una vía no la saca de 'validado' si la otra sigue validándola.
 * - El PUT del asistente no puede saltarse las validaciones enviando estado=validado.
 *
 * Usa la BD `testing` de Sail (MySQL: varias migraciones son SQL de MySQL).
 */
class ValidacionPropuestaTest extends TestCase
{
    use RefreshDatabase;

    private User $ana;
    private Microproyecto $propuesta;

    protected function setUp(): void
    {
        parent::setUp();

        $centro = DB::table('centro_educativo')->insertGetId(['nombre' => 'IES A']);
        $this->ana = User::forceCreate([
            'name' => 'Ana', 'email' => 'ana@test.local', 'password' => bcrypt('x'),
            'role' => User::ROLE_DOCENTE, 'centro_educativo_id' => $centro, 'email_verified_at' => now(),
        ]);

        $id = DB::table('microproyectos')->insertGetId([
            'uuid' => (string) Str::uuid(), 'token_empresa' => Str::random(40), 'titulo' => 'Propuesta de Ana',
            'estado' => 'propuesta', 'user_id' => $this->ana->id, 'centro_id' => $centro,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->propuesta = Microproyecto::findOrFail($id);
    }

    private function respuestaEmpresa(string $decision): array
    {
        return [
            'decision'   => $decision,
            'respuestas' => ['reto_comprensible' => 'Sí', 'objetivos_alineados' => 'Sí', 'equipo_adecuado' => 'Sí', 'viabilidad' => 'Sí'],
        ];
    }

    public function test_la_validacion_de_la_empresa_valida_la_propuesta(): void
    {
        $this->postJson("/api/startup/landing/{$this->propuesta->token_empresa}/validar", $this->respuestaEmpresa('validar'))
            ->assertOk();

        $this->propuesta->refresh();
        $this->assertTrue($this->propuesta->empresa_validado);
        $this->assertSame('validado', $this->propuesta->estado);
    }

    public function test_no_validar_aun_de_la_empresa_no_quita_la_validacion_docente(): void
    {
        $this->propuesta->update(['estado' => 'validado', 'docente_validado' => true]);

        $this->postJson("/api/startup/landing/{$this->propuesta->token_empresa}/validar", $this->respuestaEmpresa('no_validar_aun'))
            ->assertOk();

        $this->propuesta->refresh();
        $this->assertTrue($this->propuesta->empresa_no_valida_aun);
        $this->assertSame('validado', $this->propuesta->estado);
    }

    public function test_la_validacion_docente_valida_y_desvalidar_solo_vuelve_a_propuesta_sin_la_empresa(): void
    {
        Sanctum::actingAs($this->ana);
        $url = "/api/startup/proyectos/{$this->propuesta->uuid}/validar-docente";

        $this->postJson($url, ['decision' => 'validar'])->assertOk();
        $this->assertSame('validado', $this->propuesta->fresh()->estado);

        $this->postJson($url, ['decision' => 'desvalidar'])->assertOk();
        $this->assertSame('propuesta', $this->propuesta->fresh()->estado);

        // Con la empresa validándola, desvalidar como docente no la saca de 'validado'
        $this->propuesta->update(['empresa_validado' => true]);
        $this->postJson($url, ['decision' => 'validar'])->assertOk();
        $this->postJson($url, ['decision' => 'desvalidar'])->assertOk();
        $this->assertSame('validado', $this->propuesta->fresh()->estado);
    }

    public function test_probar_tokens_al_azar_desde_una_ip_acaba_limitado(): void
    {
        // El cupo es por IP: cada token distinto no abre un cupo nuevo
        for ($i = 0; $i < 30; $i++) {
            $this->getJson('/api/startup/landing/' . Str::random(40))->assertNotFound();
        }
        $this->getJson('/api/startup/landing/' . Str::random(40))->assertStatus(429);
    }

    public function test_validar_docente_rechaza_una_decision_desconocida(): void
    {
        Sanctum::actingAs($this->ana);
        $this->postJson("/api/startup/proyectos/{$this->propuesta->uuid}/validar-docente", ['decision' => 'otra'])
            ->assertUnprocessable();
    }

    public function test_el_put_no_puede_poner_validado_sin_validacion(): void
    {
        Sanctum::actingAs($this->ana);
        $this->putJson("/api/startup/proyectos/{$this->propuesta->uuid}", ['estado' => 'validado'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('estado');

        $this->assertSame('propuesta', $this->propuesta->fresh()->estado);
    }

    public function test_el_docente_no_puede_escribir_la_respuesta_de_la_empresa(): void
    {
        Sanctum::actingAs($this->ana);
        $this->putJson("/api/startup/proyectos/{$this->propuesta->uuid}", [
            'validacion_empresa' => ['respuestas' => ['viabilidad' => 'Sí'], 'comentarios' => 'Inventado'],
        ])->assertOk();

        $this->assertNull($this->propuesta->fresh()->validacion_empresa);
    }

    public function test_diseno_descarta_claves_desconocidas_y_conserva_las_antiguas(): void
    {
        Sanctum::actingAs($this->ana);
        $this->putJson("/api/startup/proyectos/{$this->propuesta->uuid}", [
            'diseno_microproyecto' => [
                'fases'       => [['nombre' => '<b>Inicio</b>', 'numero' => 0, 'duracion' => '1 semana', 'duracion_clases' => 1, 'otra' => 'x']],
                'clases'      => [['fases' => [0, 1], 'extra' => true]],
                'metodologia' => 'ABR',
                'intruso'     => 'x',
            ],
        ])->assertOk();

        $diseno = $this->propuesta->fresh()->diseno_microproyecto;
        $this->assertSame(['fases', 'clases', 'metodologia'], array_keys($diseno));
        $this->assertSame(['nombre' => 'Inicio', 'numero' => 0, 'duracion' => '1 semana', 'duracion_clases' => 1], $diseno['fases'][0]);
        $this->assertSame([['fases' => [0, 1]]], $diseno['clases']);
    }

    public function test_diseno_rechaza_fases_fuera_de_rango(): void
    {
        Sanctum::actingAs($this->ana);
        $this->putJson("/api/startup/proyectos/{$this->propuesta->uuid}", [
            'diseno_microproyecto' => ['clases' => [['fases' => [7]]]],
        ])->assertUnprocessable()->assertJsonValidationErrors('diseno_microproyecto.clases.0.fases.0');
    }

    public function test_editar_un_campo_critico_de_una_propuesta_validada_la_devuelve_a_propuesta(): void
    {
        $this->propuesta->update(['estado' => 'validado', 'docente_validado' => true]);
        Sanctum::actingAs($this->ana);

        // El asistente reenvía siempre el estado actual
        $this->putJson("/api/startup/proyectos/{$this->propuesta->uuid}", ['estado' => 'validado', 'titulo' => 'Título nuevo'])
            ->assertOk();

        $this->propuesta->refresh();
        $this->assertSame('propuesta', $this->propuesta->estado);
        $this->assertFalse($this->propuesta->docente_validado);
    }
}
