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
 * Filtros «Empresa» y «Sector» de las bibliotecas (encuentros, retos ajustados y planes): los
 * listados traen el nombre de la empresa y su sector, y nunca sus datos de contacto.
 */
class FiltroEmpresaSectorTest extends TestCase
{
    use RefreshDatabase;

    private User $ana;
    private int $proyectoId;

    protected function setUp(): void
    {
        parent::setUp();

        $centro = DB::table('centro_educativo')->insertGetId(['nombre' => 'IES A']);
        $this->ana = User::forceCreate([
            'name' => 'Ana', 'email' => 'ana@test.local', 'password' => bcrypt('x'),
            'role' => User::ROLE_DOCENTE, 'centro_educativo_id' => $centro, 'email_verified_at' => now(),
        ]);
        $empresa = DB::table('empresas')->insertGetId([
            'nombre_comercial' => 'Hotel Faro', 'sector' => 'Hostelería', 'centro_id' => $centro,
            'email_general' => 'contacto@hotelfaro.test', 'telefono' => '600000000',
        ]);
        $this->proyectoId = DB::table('microproyectos')->insertGetId([
            'uuid' => (string) Str::uuid(), 'titulo' => 'Reservas sin papel', 'estado' => 'validado',
            'user_id' => $this->ana->id, 'centro_id' => $centro, 'empresa_id' => $empresa,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Encuentro::create([
            'user_id' => $this->ana->id, 'microproyecto_id' => $this->proyectoId, 'fecha' => '2026-10-01',
            'centro_educativo_id' => $centro, 'curso' => '1º', 'grupo' => 'A', 'num_equipos' => 1,
        ]);
    }

    public function test_el_listado_de_encuentros_trae_empresa_y_sector_sin_datos_de_contacto(): void
    {
        Sanctum::actingAs($this->ana);

        $res = $this->getJson('/api/encuentros')->assertOk()
            ->assertJsonPath('0.empresa_nombre', 'Hotel Faro')
            ->assertJsonPath('0.empresa_sector', 'Hostelería');

        $this->assertStringNotContainsString('hotelfaro.test', $res->getContent());
        $this->assertStringNotContainsString('600000000', $res->getContent());
    }

    public function test_el_listado_de_retos_ajustados_trae_el_sector_de_la_empresa(): void
    {
        Sanctum::actingAs($this->ana);

        $this->getJson('/api/startup/proyectos')->assertOk()
            ->assertJsonPath('0.empresa_nombre', 'Hotel Faro')
            ->assertJsonPath('0.empresa_sector', 'Hostelería');
    }
}
