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
 * Rol «Gestión de prácticas» (7): DuaLab Central → Seguimiento de prácticas.
 * - Ve encuentros y planes de todos los centros, marca destacados, crea y valida planes, les crea el
 *   cronograma y anota horas.
 * - No valida propuestas del taller, no entra en el resto de rutas de docente ni en Red DuaLab.
 */
class GestionPracticasTest extends TestCase
{
    use RefreshDatabase;

    private User $gestor;
    private User $ana;
    private Microproyecto $propuesta;
    private EquipoMiembro $lucia;

    protected function setUp(): void
    {
        parent::setUp();
        $centro = DB::table('centro_educativo')->insertGetId(['nombre' => 'IES A']);
        $crear = fn (string $n, int $rol, ?int $c) => User::forceCreate([
            'name' => $n, 'email' => Str::slug($n) . '@test.local', 'password' => bcrypt('x'),
            'role' => $rol, 'centro_educativo_id' => $c, 'email_verified_at' => now(),
        ]);
        $this->ana    = $crear('Ana', User::ROLE_DOCENTE, $centro);
        $this->gestor = $crear('Gestora', User::ROLE_GESTION_PRACTICAS, null);

        $this->propuesta = Microproyecto::findOrFail(DB::table('microproyectos')->insertGetId([
            'uuid' => (string) Str::uuid(), 'titulo' => 'App de turnos', 'estado' => 'completado',
            'user_id' => $this->ana->id, 'centro_id' => $centro, 'created_at' => now(), 'updated_at' => now(),
        ]));
        $e = Encuentro::create([
            'user_id' => $this->ana->id, 'microproyecto_id' => $this->propuesta->id, 'fecha' => '2026-10-01',
            'centro_educativo_id' => $centro, 'curso' => '1º', 'grupo' => 'A', 'num_equipos' => 1,
        ]);
        $eq = Equipo::create(['microproyecto_id' => $this->propuesta->id, 'encuentro_id' => $e->id, 'nombre' => 'Equipo 1', 'numero_equipo' => 1]);
        $this->lucia = $eq->miembros()->create(['alias' => 'Lucía Panda Azul']);
    }

    public function test_crea_valida_y_fecha_un_plan_de_otro_centro_y_anota_horas(): void
    {
        Sanctum::actingAs($this->gestor);
        // Ve los encuentros creativos de todos los centros (para destacar y elegir alumnado)
        $this->getJson('/api/central/destacados')->assertOk()->assertJsonCount(1);
        $this->getJson('/api/encuentros')->assertOk();

        // Crea el plan: queda a nombre de la docente de la propuesta
        $uuid = $this->postJson('/api/planes', ['origen_uuid' => $this->propuesta->uuid])->assertCreated()->json('uuid');
        $plan = Microproyecto::where('uuid', $uuid)->first();
        $this->assertSame($this->ana->id, $plan->user_id);

        // Lo valida y le crea el cronograma
        $plan->update(['estado' => 'propuesta']);
        $this->postJson("/api/startup/proyectos/{$uuid}/validar-docente", ['decision' => 'validar'])->assertOk();
        $cron = $this->postJson("/api/planes/{$uuid}/cronograma", [
            'fecha' => '2027-03-01', 'num_equipos' => 1, 'miembros' => [['id' => $this->lucia->id, 'equipo_num' => 1]],
            'planificacion_sesiones' => [['fases' => [0, 1], 'fecha' => '2027-03-01'], ['fases' => [2, 3, 4], 'fecha' => '2027-04-01']],
        ])->assertCreated()->json('id');

        $this->getJson('/api/startup/proyectos?etapa=plan')->assertOk()->assertJsonPath('0.uuid', $uuid);
        $this->getJson('/api/agenda-practicas')->assertOk()->assertJsonPath('0.id', $cron)->assertJsonPath('0.puede_editar', true);
    }

    public function test_no_valida_propuestas_del_taller_ni_las_lista(): void
    {
        Sanctum::actingAs($this->gestor);
        $this->propuesta->update(['estado' => 'propuesta']);
        $this->postJson("/api/startup/proyectos/{$this->propuesta->uuid}/validar-docente", ['decision' => 'validar'])->assertForbidden();
        $this->getJson('/api/startup/proyectos')->assertOk()->assertJsonCount(0);
    }

    public function test_fuera_de_las_rutas_de_practicas_no_entra(): void
    {
        Sanctum::actingAs($this->gestor);
        $this->postJson('/api/startup/proyectos', ['titulo' => 'x'])->assertForbidden();       // crear propuesta
        $this->putJson("/api/startup/proyectos/{$this->propuesta->uuid}", [])->assertForbidden();
        $this->postJson('/api/encuentros', [])->assertForbidden();
        $this->getJson('/api/captacion/resumen')->assertForbidden();                          // Red DuaLab
        $this->getJson('/api/central/primer-contacto/formularios')->assertForbidden();
        $this->getJson('/api/admin/usuarios')->assertForbidden();
    }

    public function test_atencion_al_cliente_entra_en_captaciones_pero_no_en_practicas_ni_usuarios(): void
    {
        $atencion = User::forceCreate(['name' => 'Laura', 'email' => 'laura@test.local', 'password' => bcrypt('x'),
            'role' => User::ROLE_RED_DUALAB, 'email_verified_at' => now()]);
        Sanctum::actingAs($atencion);
        $this->getJson('/api/captacion/resumen')->assertOk();
        $this->getJson('/api/central/destacados')->assertForbidden();
        $this->getJson('/api/agenda-practicas')->assertForbidden();
        $this->getJson('/api/admin/usuarios')->assertForbidden();
    }
}
