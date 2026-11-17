<?php

namespace Tests\Feature;

use App\Models\Encuentro;
use App\Models\Equipo;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Agenda de prácticas (A1/A2 en TAREAS_PENDIENTES.md): cronogramas de planes con sus hitos fechados
 * y el estado de cada grupo («al día» / «retrasado») según el hito que toca por fecha.
 * El docente ve los suyos; DuaLab (superadmin, Central), todos; un docente de otro centro, ninguno.
 *
 * Usa la BD `testing` de Sail (MySQL: varias migraciones son SQL de MySQL).
 */
class AgendaPracticasTest extends TestCase
{
    use RefreshDatabase;

    private User $ana;
    private User $pedro;
    private User $dualab;

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

        $plan = DB::table('microproyectos')->insertGetId([
            'uuid' => (string) Str::uuid(), 'titulo' => 'Plan de turnos', 'etapa' => 'plan', 'estado' => 'validado',
            'user_id' => $this->ana->id, 'centro_id' => $centroA, 'horas_estimadas' => 120,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $cronograma = Encuentro::create([
            'user_id' => $this->ana->id, 'microproyecto_id' => $plan, 'fecha' => '2027-03-01', 'fecha_fin' => '2027-05-15',
            'centro_educativo_id' => $centroA, 'curso' => '1º', 'grupo' => 'A', 'num_equipos' => 2,
            'planificacion_sesiones' => [
                ['fases' => [0, 1], 'fecha' => '2027-03-01'], ['fases' => [2], 'fecha' => '2027-04-01'], ['fases' => [3, 4], 'fecha' => '2027-05-15'],
            ],
        ]);
        $cronograma->tipo = Encuentro::TIPO_PLAN;
        $cronograma->save();

        // Grupo 1 va por F2 (al día a mediados de abril); grupo 2 sigue en F1 (retrasado)
        Equipo::create(['microproyecto_id' => $plan, 'encuentro_id' => $cronograma->id, 'nombre' => 'G1', 'numero_equipo' => 1, 'fase_actual' => 2])
            ->miembros()->create(['alias' => 'Lucía Panda Azul']);
        Equipo::create(['microproyecto_id' => $plan, 'encuentro_id' => $cronograma->id, 'nombre' => 'G2', 'numero_equipo' => 2, 'fase_actual' => 1])
            ->miembros()->create(['alias' => 'Mario Panda Azul']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_estado_de_cada_grupo_segun_el_hito_que_toca(): void
    {
        Carbon::setTestNow('2027-04-15');
        Sanctum::actingAs($this->ana);

        $this->getJson('/api/agenda-practicas')->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.estado', 'en_curso')
            ->assertJsonPath('0.hito_actual', 2)
            ->assertJsonPath('0.fase_esperada', 2)
            ->assertJsonPath('0.siguiente_hito.fecha', '2027-05-15')
            ->assertJsonPath('0.plan.horas_estimadas', 120)
            ->assertJsonPath('0.grupos.0.estado', 'al_dia')
            ->assertJsonPath('0.grupos.1.estado', 'retrasado');
    }

    public function test_antes_de_empezar_nadie_va_retrasado(): void
    {
        Carbon::setTestNow('2027-02-01');
        Sanctum::actingAs($this->ana);

        $this->getJson('/api/agenda-practicas')->assertOk()
            ->assertJsonPath('0.estado', 'por_empezar')
            ->assertJsonPath('0.grupos.1.estado', 'al_dia');
    }

    public function test_dualab_ve_todos_y_un_docente_de_otro_centro_ninguno(): void
    {
        Sanctum::actingAs($this->dualab);
        $this->getJson('/api/agenda-practicas')->assertOk()->assertJsonCount(1);

        Sanctum::actingAs($this->pedro);
        $this->getJson('/api/agenda-practicas')->assertOk()->assertJsonCount(0);
    }
}
