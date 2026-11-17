<?php

namespace Tests\Feature;

use App\Models\Encuentro;
use App\Models\Equipo;
use App\Models\EquipoMiembro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Registro de horas de prácticas (A3 en TAREAS_PENDIENTES.md).
 *
 * - Las anotan el docente del plan y DuaLab (superadmin); un colaborador de solo lectura, no.
 * - Solo para alumnado de ese cronograma, sin días futuros ni más de 24 h.
 * - La agenda suma las horas por alumno/a y el alumnado las ve en su workspace.
 *
 * Usa la BD `testing` de Sail (MySQL: varias migraciones son SQL de MySQL).
 */
class RegistroHorasTest extends TestCase
{
    use RefreshDatabase;

    private User $ana;
    private User $luis;
    private User $dualab;
    private Encuentro $cronograma;
    private Equipo $equipo;
    private EquipoMiembro $lucia;
    private EquipoMiembro $ajeno;

    protected function setUp(): void
    {
        parent::setUp();

        $centro = DB::table('centro_educativo')->insertGetId(['nombre' => 'IES A']);
        $crear = fn (string $n, int $rol, ?int $c) => User::forceCreate([
            'name' => $n, 'email' => Str::slug($n) . '@test.local', 'password' => bcrypt('x'),
            'role' => $rol, 'centro_educativo_id' => $c, 'email_verified_at' => now(),
        ]);
        $this->ana    = $crear('Ana', User::ROLE_DOCENTE, $centro);
        $this->luis   = $crear('Luis', User::ROLE_DOCENTE, $centro);
        $this->dualab = $crear('DuaLab', User::ROLE_SUPERADMIN, null);

        $plan = DB::table('microproyectos')->insertGetId([
            'uuid' => (string) Str::uuid(), 'titulo' => 'Plan', 'etapa' => 'plan', 'estado' => 'validado',
            'user_id' => $this->ana->id, 'centro_id' => $centro, 'horas_estimadas' => 100, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->cronograma = new Encuentro([
            'microproyecto_id' => $plan, 'fecha' => now()->subMonth()->toDateString(), 'fecha_fin' => now()->addMonth()->toDateString(),
            'centro_educativo_id' => $centro, 'curso' => '1º', 'grupo' => 'A', 'num_equipos' => 1,
        ]);
        $this->cronograma->user_id = $this->ana->id;
        $this->cronograma->tipo = Encuentro::TIPO_PLAN;
        $this->cronograma->save();
        $this->cronograma->colaboradores()->attach($this->luis->id, ['puede_editar' => false]);

        $this->equipo = Equipo::create(['microproyecto_id' => $plan, 'encuentro_id' => $this->cronograma->id, 'nombre' => 'G1', 'numero_equipo' => 1]);
        $this->lucia = $this->equipo->miembros()->create(['alias' => 'Lucía Panda Azul']);

        // Alumno de otro encuentro (no de este plan)
        $otro = Encuentro::create(['user_id' => $this->ana->id, 'microproyecto_id' => $plan, 'fecha' => now()->toDateString(),
            'centro_educativo_id' => $centro, 'curso' => '1º', 'grupo' => 'B', 'num_equipos' => 1]);
        $this->ajeno = Equipo::create(['microproyecto_id' => $plan, 'encuentro_id' => $otro->id, 'nombre' => 'G9', 'numero_equipo' => 1])
            ->miembros()->create(['alias' => 'Mario Panda Azul']);
    }

    private function anotar(array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/api/agenda-practicas/{$this->cronograma->id}/horas", [
            'miembro_id' => $this->lucia->id, 'fecha' => now()->subDay()->toDateString(), 'horas' => 5, 'descripcion' => '<b>Montaje</b>', ...$extra,
        ]);
    }

    public function test_el_docente_y_dualab_anotan_y_la_agenda_suma(): void
    {
        Sanctum::actingAs($this->ana);
        $this->anotar()->assertCreated()->assertJsonPath('horas', 5)->assertJsonPath('descripcion', 'Montaje');

        Sanctum::actingAs($this->dualab);
        $this->anotar(['horas' => 2.5])->assertCreated()->assertJsonPath('registrado_por', 'DuaLab');

        Sanctum::actingAs($this->ana);
        $this->getJson('/api/agenda-practicas')->assertOk()
            ->assertJsonPath('0.horas_total', 7.5)
            ->assertJsonPath('0.grupos.0.miembros.0.horas', 7.5);
        $this->getJson("/api/agenda-practicas/{$this->cronograma->id}/horas")->assertOk()->assertJsonCount(2);
    }

    public function test_un_colaborador_de_solo_lectura_ve_pero_no_anota_ni_borra(): void
    {
        Sanctum::actingAs($this->ana);
        $id = $this->anotar()->json('id');

        Sanctum::actingAs($this->luis);
        $this->getJson("/api/agenda-practicas/{$this->cronograma->id}/horas")->assertOk()->assertJsonCount(1);
        $this->anotar()->assertNotFound();
        $this->deleteJson("/api/agenda-practicas/horas/{$id}")->assertNotFound();
    }

    public function test_validaciones(): void
    {
        Sanctum::actingAs($this->ana);
        $this->anotar(['miembro_id' => $this->ajeno->id])->assertUnprocessable()->assertJsonValidationErrors('miembro_id');
        $this->anotar(['fecha' => now()->addDay()->toDateString()])->assertUnprocessable()->assertJsonValidationErrors('fecha');
        $this->anotar(['horas' => 25])->assertUnprocessable()->assertJsonValidationErrors('horas');
        $this->anotar(['horas' => 0])->assertUnprocessable()->assertJsonValidationErrors('horas');
    }

    public function test_el_docente_borra_un_registro(): void
    {
        Sanctum::actingAs($this->ana);
        $id = $this->anotar()->json('id');
        $this->deleteJson("/api/agenda-practicas/horas/{$id}")->assertNoContent();
        $this->getJson("/api/agenda-practicas/{$this->cronograma->id}/horas")->assertOk()->assertJsonCount(0);
    }

    public function test_el_alumnado_ve_sus_horas_en_el_workspace(): void
    {
        Sanctum::actingAs($this->ana);
        $this->anotar(['horas' => 4]);

        $this->app['auth']->forgetGuards();
        $this->getJson("/api/equipo/{$this->equipo->token}")->assertOk()
            ->assertJsonPath('etapa.horas.0.alias', 'Lucía Panda Azul')
            ->assertJsonPath('etapa.horas.0.total', 4)
            ->assertJsonPath('etapa.horas.0.registros.0.horas', 4);
    }
}
