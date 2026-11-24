<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * PATCH /empresas/{id}/interes: guardar el interés en la empresa es solo de superadmin,
 * también en las ficticias del propio centro (el docente lo usa solo en su reto).
 *
 * Usa la BD `testing` de Sail (MySQL: varias migraciones son SQL de MySQL).
 */
class InteresEmpresaTest extends TestCase
{
    use RefreshDatabase;

    public function test_solo_superadmin_guarda_el_interes_en_la_empresa(): void
    {
        $centro  = DB::table('centro_educativo')->insertGetId(['nombre' => 'IES Interés']);
        $empresa = DB::table('empresas')->insertGetId(['nombre_comercial' => 'Ficticia propia', 'centro_id' => $centro, 'es_simulada' => 1]);
        $cuerpo  = ['familia' => 'Comercio y Marketing', 'interes' => 'Campañas en redes'];

        foreach ([User::ROLE_DOCENTE, User::ROLE_ADMIN] as $i => $rol) {
            Sanctum::actingAs(User::forceCreate([
                'name' => "Usuario {$rol}", 'email' => "interes-{$i}@test.local", 'password' => bcrypt('x'),
                'role' => $rol, 'email_verified_at' => now(), 'centro_educativo_id' => $centro,
            ]));
            $this->patchJson("/api/empresas/{$empresa}/interes", $cuerpo)->assertForbidden();
        }

        // Superadmin pasa la policy (422: la familia no está vinculada a la empresa, no 403)
        Sanctum::actingAs(User::forceCreate([
            'name' => 'DuaLab', 'email' => 'interes-superadmin@test.local', 'password' => bcrypt('x'),
            'role' => User::ROLE_SUPERADMIN, 'email_verified_at' => now(),
        ]));
        $this->patchJson("/api/empresas/{$empresa}/interes", $cuerpo)->assertUnprocessable();
    }
}
