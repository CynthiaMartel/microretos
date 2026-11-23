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
 * Código de acceso del alumnado (POST /encuentros/{id}/crear-codigo): se puede generar en los
 * encuentros de un reto ajustado validado o ya completado (se reutiliza en otras clases), no
 * en uno en edición o archivado.
 *
 * Usa la BD `testing` de Sail (MySQL: varias migraciones son SQL de MySQL).
 */
class CrearCodigoEncuentroTest extends TestCase
{
    use RefreshDatabase;

    private User $docente;
    private int $centro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centro  = DB::table('centro_educativo')->insertGetId(['nombre' => 'IES A']);
        $this->docente = User::forceCreate([
            'name' => 'Ana', 'email' => 'ana@test.local', 'password' => bcrypt('x'),
            'role' => User::ROLE_DOCENTE, 'centro_educativo_id' => $this->centro, 'email_verified_at' => now(),
        ]);
    }

    private function encuentroConEstado(string $estado): Encuentro
    {
        $proyectoId = DB::table('microproyectos')->insertGetId([
            'uuid' => (string) Str::uuid(), 'titulo' => 'Reto ajustado', 'estado' => $estado,
            'user_id' => $this->docente->id, 'centro_id' => $this->centro,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return Encuentro::create([
            'user_id' => $this->docente->id, 'microproyecto_id' => $proyectoId, 'fecha' => now()->toDateString(),
            'centro_educativo_id' => $this->centro, 'curso' => '1º', 'grupo' => 'A', 'num_equipos' => 2,
            'alumnados' => [['alias' => 'Lucía Panda Azul', 'equipo_num' => 1], ['alias' => 'Hugo Lince Verde', 'equipo_num' => 2]],
        ]);
    }

    public function test_genera_codigo_con_reto_ajustado_validado_o_completado(): void
    {
        Sanctum::actingAs($this->docente);

        foreach (['validado', 'completado'] as $estado) {
            $encuentro = $this->encuentroConEstado($estado);
            $this->postJson("/api/encuentros/{$encuentro->id}/crear-codigo")
                ->assertCreated()
                ->assertJsonCount(2, 'equipos');
            $this->assertNotNull($encuentro->fresh()->codigo_clase);
        }
    }

    public function test_no_genera_codigo_si_el_reto_ajustado_no_esta_listo(): void
    {
        Sanctum::actingAs($this->docente);

        foreach (['en_edicion', 'archivado'] as $estado) {
            $encuentro = $this->encuentroConEstado($estado);
            $this->postJson("/api/encuentros/{$encuentro->id}/crear-codigo")->assertUnprocessable();
            $this->assertNull($encuentro->fresh()->codigo_clase);
        }
    }
}
