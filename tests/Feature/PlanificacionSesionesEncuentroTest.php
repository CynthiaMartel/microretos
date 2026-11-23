<?php

namespace Tests\Feature;

use App\Models\Encuentro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Planificación de sesiones del encuentro (fase P2 en TAREAS_PENDIENTES.md).
 *
 * - Cada encuentro guarda sus sesiones (planificacion_sesiones) y de ahí sale la fecha fin.
 * - Si no llega planificación, parte del calendario de la propuesta.
 * - Los encuentros nuevos son del tipo creativo (etapa 1).
 *
 * Usa la BD `testing` de Sail (MySQL: varias migraciones son SQL de MySQL).
 */
class PlanificacionSesionesEncuentroTest extends TestCase
{
    use RefreshDatabase;

    private User $ana;
    private int $propuestaId;

    protected function setUp(): void
    {
        parent::setUp();

        $centro = DB::table('centro_educativo')->insertGetId(['nombre' => 'IES A']);
        $this->ana = User::forceCreate([
            'name' => 'Ana', 'email' => 'ana@test.local', 'password' => bcrypt('x'),
            'role' => User::ROLE_DOCENTE, 'centro_educativo_id' => $centro, 'email_verified_at' => now(),
        ]);

        // Propuesta validada con un calendario de 3 sesiones
        $this->propuestaId = DB::table('microproyectos')->insertGetId([
            'uuid' => (string) Str::uuid(), 'titulo' => 'Propuesta de Ana', 'estado' => 'validado',
            'docente_validado' => true, 'user_id' => $this->ana->id, 'centro_id' => $centro,
            'diseno_microproyecto' => json_encode(['clases' => [['fases' => [0, 1]], ['fases' => [2, 3]], ['fases' => [4]]]]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $extra */
    private function crear(array $extra = []): \Illuminate\Testing\TestResponse
    {
        Sanctum::actingAs($this->ana);

        return $this->postJson('/api/encuentros', [
            'microproyecto_id' => $this->propuestaId,
            'fecha'            => '2026-11-02',
            'curso'            => '1º',
            'grupo'            => 'A',
            'num_equipos'      => 1,
            'alumnados'        => [['alias' => 'Lucía Panda Azul', 'equipo_num' => 1]],
            ...$extra,
        ]);
    }

    public function test_sin_planificacion_parte_del_calendario_de_la_propuesta(): void
    {
        $res = $this->crear()->assertCreated();

        $res->assertJsonPath('tipo', Encuentro::TIPO_CREATIVO);
        $res->assertJsonPath('planificacion_sesiones', [['fases' => [0, 1]], ['fases' => [2, 3]], ['fases' => [4]]]);
        // 3 sesiones × 1 semana desde el 2 de noviembre
        $res->assertJsonPath('fecha_fin', '2026-11-23');
    }

    public function test_guarda_su_propia_planificacion_y_calcula_la_fecha_fin_con_ella(): void
    {
        $res = $this->crear(['planificacion_sesiones' => [
            ['fases' => [0, 1], 'intrusa' => 'x'], ['fases' => [2]], ['fases' => [2]], ['fases' => [3]], ['fases' => [4]],
        ]])->assertCreated();

        $res->assertJsonPath('planificacion_sesiones.0', ['fases' => [0, 1]]);
        $res->assertJsonCount(5, 'planificacion_sesiones');
        $res->assertJsonPath('fecha_fin', '2026-12-07');
    }

    public function test_la_fecha_fin_elegida_se_guarda_si_no_es_anterior_a_la_sugerida(): void
    {
        // Sugerida: 3 sesiones desde el 2 de noviembre → 23 de noviembre
        $this->crear(['fecha_fin' => '2026-12-14'])->assertCreated()->assertJsonPath('fecha_fin', '2026-12-14');

        $this->crear(['fecha_fin' => '2026-11-16'])
            ->assertUnprocessable()->assertJsonValidationErrors('fecha_fin');
    }

    public function test_el_ciclo_del_encuentro_es_el_del_reto_ajustado(): void
    {
        $ciclo = DB::table('ciclos_formativos')->insertGetId(['nombre' => 'CFGM Gestión Administrativa', 'siglasGrado' => 'GM', 'familia_id' => null]);
        DB::table('microproyectos')->where('id', $this->propuestaId)->update(['ciclo_id' => $ciclo]);

        $this->crear(['ciclo_formativo' => 'Otro ciclo cualquiera'])
            ->assertCreated()->assertJsonPath('ciclo_formativo', 'CFGM Gestión Administrativa');
    }

    public function test_rechaza_sesiones_sin_fase_o_con_fase_fuera_de_rango(): void
    {
        $this->crear(['planificacion_sesiones' => [['fases' => []]]])
            ->assertUnprocessable()->assertJsonValidationErrors('planificacion_sesiones.0.fases');

        $this->crear(['planificacion_sesiones' => [['fases' => [9]]]])
            ->assertUnprocessable()->assertJsonValidationErrors('planificacion_sesiones.0.fases.0');
    }

    public function test_editar_la_planificacion_y_la_fecha_fin_minima_usa_la_nueva(): void
    {
        $id = $this->crear()->assertCreated()->json('id');

        // 5 sesiones → la fecha fin no puede ser anterior al 7 de diciembre
        $this->putJson("/api/encuentros/{$id}", [
            'planificacion_sesiones' => [['fases' => [0]], ['fases' => [1]], ['fases' => [2]], ['fases' => [3]], ['fases' => [4]]],
            'fecha_fin'              => '2026-11-23',
        ])->assertUnprocessable()->assertJsonValidationErrors('fecha_fin');

        $this->putJson("/api/encuentros/{$id}", [
            'planificacion_sesiones' => [['fases' => [0]], ['fases' => [1]], ['fases' => [2]], ['fases' => [3]], ['fases' => [4]]],
        ])->assertOk()->assertJsonCount(5, 'planificacion_sesiones');
    }
}
