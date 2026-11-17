<?php

namespace Tests\Feature;

use App\Models\Encuentro;
use App\Models\EquipoMiembro;
use App\Models\Equipo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Alumnado destacado para prácticas (fase P3b en TAREAS_PENDIENTES.md).
 *
 * - Solo el superadmin (DuaLab Central) lista los encuentros y marca alias como destacados.
 * - El docente del encuentro ve la marca; el workspace del alumnado no la expone.
 *
 * Usa la BD `testing` de Sail (MySQL: varias migraciones son SQL de MySQL).
 */
class DestacadosCentralTest extends TestCase
{
    use RefreshDatabase;

    private User $dualab;
    private User $ana;
    private Equipo $equipo;
    private EquipoMiembro $lucia;
    private Encuentro $encuentro;

    protected function setUp(): void
    {
        parent::setUp();

        $centro = DB::table('centro_educativo')->insertGetId(['nombre' => 'IES A']);
        $crear = fn (string $n, int $rol) => User::forceCreate([
            'name' => $n, 'email' => Str::slug($n) . '@test.local', 'password' => bcrypt('x'),
            'role' => $rol, 'centro_educativo_id' => $rol === User::ROLE_SUPERADMIN ? null : $centro, 'email_verified_at' => now(),
        ]);
        $this->dualab = $crear('DuaLab', User::ROLE_SUPERADMIN);
        $this->ana    = $crear('Ana', User::ROLE_DOCENTE);

        $propuesta = DB::table('microproyectos')->insertGetId([
            'uuid' => (string) Str::uuid(), 'titulo' => 'Propuesta de Ana', 'estado' => 'validado',
            'user_id' => $this->ana->id, 'centro_id' => $centro, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->encuentro = Encuentro::create([
            'user_id' => $this->ana->id, 'microproyecto_id' => $propuesta, 'fecha' => '2026-11-02',
            'centro_educativo_id' => $centro, 'curso' => '1º', 'grupo' => 'A', 'num_equipos' => 1,
        ]);
        $this->equipo = Equipo::create(['microproyecto_id' => $propuesta, 'encuentro_id' => $this->encuentro->id, 'nombre' => 'Equipo 1', 'numero_equipo' => 1]);
        $this->lucia = $this->equipo->miembros()->create(['alias' => 'Lucía Panda Azul']);
    }

    public function test_solo_el_superadmin_lista_y_marca(): void
    {
        Sanctum::actingAs($this->ana);
        $this->getJson('/api/central/destacados')->assertForbidden();
        $this->putJson("/api/central/destacados/miembros/{$this->lucia->id}", ['destacado' => true])->assertForbidden();
        $this->assertFalse($this->lucia->fresh()->esDestacado());
    }

    public function test_el_superadmin_marca_y_desmarca_un_alias(): void
    {
        Sanctum::actingAs($this->dualab);

        $this->getJson('/api/central/destacados')->assertOk()
            ->assertJsonPath('0.equipos.0.miembros.0.alias', 'Lucía Panda Azul')
            ->assertJsonPath('0.equipos.0.miembros.0.destacado', false);

        $this->putJson("/api/central/destacados/miembros/{$this->lucia->id}", ['destacado' => true])
            ->assertOk()->assertJsonPath('destacado', true);
        $this->assertSame($this->dualab->id, $this->lucia->fresh()->destacado_por_id);

        $this->getJson('/api/central/destacados?solo_destacados=1')->assertOk()->assertJsonCount(1);

        $this->putJson("/api/central/destacados/miembros/{$this->lucia->id}", ['destacado' => false])
            ->assertOk()->assertJsonPath('destacado', false);
        $this->assertNull($this->lucia->fresh()->destacado_por_id);
    }

    public function test_el_docente_ve_la_marca_y_el_alumnado_no(): void
    {
        Sanctum::actingAs($this->dualab);
        $this->putJson("/api/central/destacados/miembros/{$this->lucia->id}", ['destacado' => true])->assertOk();

        Sanctum::actingAs($this->ana);
        // Biblioteca de encuentros (el detalle usa el listado, que trae los equipos) y Detalle de grupos
        $this->getJson('/api/encuentros')->assertOk()
            ->assertJsonPath('0.equipos.0.miembros.0.destacado', true);
        $this->getJson("/api/encuentros/{$this->encuentro->id}/workspace")->assertOk()
            ->assertJsonPath('equipos.0.miembros.0.destacado', true);
        // Listado de alumnado
        $this->assertTrue(collect($this->getJson('/api/alumnado')->assertOk()->json())
            ->firstWhere('alias', 'Lucía Panda Azul')['destacado']);

        // Workspace del alumnado (público, por token del equipo): sin la marca
        $this->app['auth']->forgetGuards();
        $json = $this->getJson("/api/equipo/{$this->equipo->token}")->assertOk()->getContent();
        $this->assertStringNotContainsString('destacado', $json);
    }

    public function test_marcar_exige_un_booleano(): void
    {
        Sanctum::actingAs($this->dualab);
        $this->putJson("/api/central/destacados/miembros/{$this->lucia->id}", ['destacado' => 'quizá'])
            ->assertUnprocessable()->assertJsonValidationErrors('destacado');
    }
}
