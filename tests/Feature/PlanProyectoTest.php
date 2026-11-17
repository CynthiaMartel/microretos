<?php

namespace Tests\Feature;

use App\Models\Encuentro;
use App\Models\Equipo;
use App\Models\EquipoMiembro;
use App\Models\Microproyecto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Plan de proyecto (etapa 2, fases P4a/P4b en TAREAS_PENDIENTES.md).
 *
 * - El docente o DuaLab crean un plan a partir de una propuesta validada (se copia lo curricular)
 *   o de un reto; si lo crea DuaLab, queda a nombre del docente de la propuesta.
 * - Los planes no se mezclan con las propuestas en la biblioteca (?etapa=plan).
 * - El cronograma es un encuentro de tipo plan con alumnado (alias) de uno o varios encuentros.
 *
 * Usa la BD `testing` de Sail (MySQL: varias migraciones son SQL de MySQL).
 */
class PlanProyectoTest extends TestCase
{
    use RefreshDatabase;

    private User $ana;
    private User $pedro;   // docente de otro centro
    private User $dualab;
    private Microproyecto $propuesta;
    private EquipoMiembro $lucia;
    private EquipoMiembro $mario;

    protected function setUp(): void
    {
        parent::setUp();

        $centroA = DB::table('centro_educativo')->insertGetId(['nombre' => 'IES A']);
        $centroB = DB::table('centro_educativo')->insertGetId(['nombre' => 'IES B']);
        $crear = fn (string $n, int $rol, ?int $c) => User::forceCreate([
            'name' => $n, 'email' => Str::slug($n) . '@test.local', 'password' => bcrypt('x'),
            'role' => $rol, 'centro_educativo_id' => $c, 'email_verified_at' => now(),
        ]);
        $this->ana    = $crear('Ana', User::ROLE_DOCENTE, $centroA);
        $this->pedro  = $crear('Pedro', User::ROLE_DOCENTE, $centroB);
        $this->dualab = $crear('DuaLab', User::ROLE_SUPERADMIN, null);

        $id = DB::table('microproyectos')->insertGetId([
            'uuid' => (string) Str::uuid(), 'titulo' => 'App de turnos', 'estado' => 'completado',
            'user_id' => $this->ana->id, 'centro_id' => $centroA,
            'objetivos' => json_encode(['lista' => ['Reducir retrasos']]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->propuesta = Microproyecto::findOrFail($id);

        // Dos encuentros creativos de Ana con un alias repetido entre ellos
        $crearEncuentro = function (string $grupo, string $alias) use ($centroA) {
            $e = Encuentro::create([
                'user_id' => $this->ana->id, 'microproyecto_id' => $this->propuesta->id, 'fecha' => '2026-10-01',
                'centro_educativo_id' => $centroA, 'curso' => '1º', 'grupo' => $grupo, 'num_equipos' => 1,
            ]);
            $eq = Equipo::create(['microproyecto_id' => $this->propuesta->id, 'encuentro_id' => $e->id, 'nombre' => 'Equipo 1', 'numero_equipo' => 1]);

            return $eq->miembros()->create(['alias' => $alias]);
        };
        $this->lucia = $crearEncuentro('A', 'Lucía Panda Azul');
        $this->mario = $crearEncuentro('B', 'Lucía Panda Azul'); // mismo alias en otra clase
    }

    private function crearPlan(User $quien, array $extra = []): \Illuminate\Testing\TestResponse
    {
        Sanctum::actingAs($quien);

        return $this->postJson('/api/planes', ['origen_uuid' => $this->propuesta->uuid, 'horas_estimadas' => 120, ...$extra]);
    }

    public function test_el_docente_crea_un_plan_desde_su_propuesta_y_hereda_lo_curricular(): void
    {
        $uuid = $this->crearPlan($this->ana)->assertCreated()->assertJsonPath('etapa', 'plan')->json('uuid');

        $plan = Microproyecto::where('uuid', $uuid)->first();
        $this->assertSame($this->propuesta->id, $plan->origen_id);
        $this->assertSame('en_edicion', $plan->estado);
        $this->assertSame(['lista' => ['Reducir retrasos']], $plan->objetivos);
        $this->assertSame(120, $plan->horas_estimadas);
        $this->assertSame($this->ana->id, $plan->user_id);
    }

    public function test_dualab_crea_el_plan_a_nombre_del_docente_de_la_propuesta(): void
    {
        $uuid = $this->crearPlan($this->dualab)->assertCreated()->json('uuid');
        $this->assertSame($this->ana->id, Microproyecto::where('uuid', $uuid)->value('user_id'));
    }

    public function test_un_docente_de_otro_centro_no_puede_ampliar_la_propuesta(): void
    {
        $this->crearPlan($this->pedro)->assertForbidden();
    }

    public function test_no_se_crea_plan_desde_una_propuesta_sin_validar(): void
    {
        $this->propuesta->update(['estado' => 'propuesta']);
        $this->crearPlan($this->ana)->assertUnprocessable()->assertJsonValidationErrors('origen_uuid');
    }

    public function test_planes_y_propuestas_no_se_mezclan_en_la_biblioteca(): void
    {
        $uuid = $this->crearPlan($this->ana)->json('uuid');

        $propuestas = collect($this->getJson('/api/startup/proyectos')->assertOk()->json())->pluck('uuid');
        $planes = collect($this->getJson('/api/startup/proyectos?etapa=plan')->assertOk()->json());

        $this->assertFalse($propuestas->contains($uuid));
        $this->assertTrue($propuestas->contains($this->propuesta->uuid));
        $this->assertSame([$uuid], $planes->pluck('uuid')->all());
        $this->assertSame('App de turnos', $planes->first()['origen']['titulo']);
    }

    public function test_cronograma_con_alumnado_de_varios_encuentros_y_sin_alias_repetidos(): void
    {
        $uuid = $this->crearPlan($this->ana)->json('uuid');
        Microproyecto::where('uuid', $uuid)->update(['estado' => 'validado', 'docente_validado' => true]);

        $res = $this->postJson("/api/planes/{$uuid}/cronograma", [
            'fecha' => '2027-03-01', 'num_equipos' => 1,
            'miembros' => [['id' => $this->lucia->id, 'equipo_num' => 1], ['id' => $this->mario->id, 'equipo_num' => 1]],
            'planificacion_sesiones' => [
                ['fases' => [0, 1], 'fecha' => '2027-03-01'], ['fases' => [2], 'fecha' => '2027-04-01'], ['fases' => [3, 4], 'fecha' => '2027-05-15'],
            ],
        ])->assertCreated();

        // Fecha fin = la del último hito (fechas reales, no estimadas)
        $res->assertJsonPath('tipo', Encuentro::TIPO_PLAN)->assertJsonPath('fecha_fin', '2027-05-15');
        $alias = collect($res->json('alumnados'))->pluck('alias');
        $this->assertCount(2, $alias->unique(), 'Dos alumnos con el mismo alias de clases distintas no pueden chocar');

        // El cronograma no aparece en la biblioteca de encuentros creativos, sí al pedir los de tipo plan
        $this->assertCount(2, $this->getJson('/api/encuentros')->json());
        $this->assertCount(1, $this->getJson('/api/encuentros?tipo=plan_practicas_etapa2')->json());
    }

    public function test_los_hitos_del_cronograma_llevan_fecha_real_y_en_orden(): void
    {
        $uuid = $this->crearPlan($this->ana)->json('uuid');
        Microproyecto::where('uuid', $uuid)->update(['estado' => 'validado']);
        $base = ['fecha' => '2027-03-01', 'num_equipos' => 1, 'miembros' => [['id' => $this->lucia->id, 'equipo_num' => 1]]];

        $this->postJson("/api/planes/{$uuid}/cronograma", $base + ['planificacion_sesiones' => [['fases' => [0]]]])
            ->assertUnprocessable()->assertJsonValidationErrors('planificacion_sesiones.0.fecha');
        $this->postJson("/api/planes/{$uuid}/cronograma", $base + ['planificacion_sesiones' => [
            ['fases' => [0], 'fecha' => '2027-04-01'], ['fases' => [1], 'fecha' => '2027-03-15'],
        ]])->assertUnprocessable()->assertJsonValidationErrors('planificacion_sesiones.1.fecha');
        $this->postJson("/api/planes/{$uuid}/cronograma", $base + ['planificacion_sesiones' => [['fases' => [0], 'fecha' => '2027-02-01']]])
            ->assertUnprocessable()->assertJsonValidationErrors('planificacion_sesiones.0.fecha');
    }

    public function test_editar_los_hitos_del_cronograma_exige_fecha_y_orden(): void
    {
        $uuid = $this->crearPlan($this->ana)->json('uuid');
        Microproyecto::where('uuid', $uuid)->update(['estado' => 'validado']);
        $id = $this->postJson("/api/planes/{$uuid}/cronograma", [
            'fecha' => '2027-03-01', 'num_equipos' => 1, 'miembros' => [['id' => $this->lucia->id, 'equipo_num' => 1]],
            'planificacion_sesiones' => [['fases' => [0], 'fecha' => '2027-03-01']],
        ])->assertCreated()->json('id');

        $this->putJson("/api/encuentros/{$id}", ['planificacion_sesiones' => [['fases' => [0]]]])
            ->assertUnprocessable()->assertJsonValidationErrors('planificacion_sesiones.0.fecha');
        $this->putJson("/api/encuentros/{$id}", ['planificacion_sesiones' => [
            ['fases' => [0], 'fecha' => '2027-04-01'], ['fases' => [1], 'fecha' => '2027-03-10'],
        ]])->assertUnprocessable()->assertJsonValidationErrors('planificacion_sesiones.1.fecha');

        $this->putJson("/api/encuentros/{$id}", [
            'planificacion_sesiones' => [['fases' => [0, 1], 'fecha' => '2027-03-01'], ['fases' => [2, 3, 4], 'fecha' => '2027-04-20']],
            'fecha_fin' => '2027-04-20',
        ])->assertOk()->assertJsonPath('fecha_fin', '2027-04-20')->assertJsonCount(2, 'planificacion_sesiones');
    }

    public function test_el_cronograma_exige_plan_validado_y_alumnado_visible(): void
    {
        $uuid = $this->crearPlan($this->ana)->json('uuid');
        $payload = ['fecha' => '2027-03-01', 'num_equipos' => 1, 'miembros' => [['id' => $this->lucia->id, 'equipo_num' => 1]],
                    'planificacion_sesiones' => [['fases' => [0], 'fecha' => '2027-03-01']]];

        $this->postJson("/api/planes/{$uuid}/cronograma", $payload)->assertUnprocessable();

        Microproyecto::where('uuid', $uuid)->update(['estado' => 'validado']);
        Sanctum::actingAs($this->pedro);
        $this->postJson("/api/planes/{$uuid}/cronograma", $payload)->assertForbidden();
    }
}
