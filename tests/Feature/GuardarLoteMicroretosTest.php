<?php

namespace Tests\Feature;

use App\Models\Microreto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * POST /guardar-microretos-lote: un docente solo guarda retos de empresas de su centro.
 *
 * Usa la BD `testing` de Sail (MySQL: varias migraciones son SQL de MySQL).
 */
class GuardarLoteMicroretosTest extends TestCase
{
    use RefreshDatabase;

    public function test_docente_solo_guarda_retos_de_empresas_de_su_centro(): void
    {
        $centro     = DB::table('centro_educativo')->insertGetId(['nombre' => 'IES Lote Propio']);
        $otroCentro = DB::table('centro_educativo')->insertGetId(['nombre' => 'IES Lote Ajeno']);
        $propia = DB::table('empresas')->insertGetId(['nombre_comercial' => 'Empresa propia', 'centro_id' => $centro]);
        $ajena  = DB::table('empresas')->insertGetId(['nombre_comercial' => 'Empresa ajena', 'centro_id' => $otroCentro]);

        Sanctum::actingAs(User::forceCreate([
            'name' => 'Docente', 'email' => 'docente-lote@test.local', 'password' => bcrypt('x'),
            'role' => User::ROLE_DOCENTE, 'email_verified_at' => now(), 'centro_educativo_id' => $centro,
        ]));

        // Una sola empresa ajena en el lote basta para rechazarlo entero
        $this->postJson('/api/guardar-microretos-lote', ['microretos' => [
            ['empresa_id' => $propia, 'titulo' => 'Reto A'],
            ['empresa_id' => $ajena,  'titulo' => 'Reto B'],
        ]])->assertForbidden();
        $this->assertSame(0, Microreto::count());

        $this->postJson('/api/guardar-microretos-lote', ['microretos' => [
            ['empresa_id' => $propia, 'titulo' => 'Reto A'],
            ['empresa_id' => $propia, 'titulo' => 'Reto C'],
        ]])->assertCreated();
        $this->assertSame(2, Microreto::where('empresa_id', $propia)->count());
    }
}
