<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Roles de DuaLab Central (19-nov): red_dualab abre Red DuaLab, gestion_practicas abre Seguimiento de
 * prácticas, central_dualab abre los dos; Base de datos, Usuarios y Papelera son solo del superadmin,
 * y solo un superadmin puede crear otro superadmin (nunca ascendiendo a alguien al editarlo).
 *
 * Usa la BD `testing` de Sail (MySQL: varias migraciones son SQL de MySQL).
 */
class RolesCentralTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'Clave-Segura-2026!';

    private function usuario(int $rol, string $email, ?int $centro = null): User
    {
        return User::forceCreate([
            'name' => ucfirst(strtok($email, '@')), 'email' => $email, 'password' => bcrypt('x'),
            'role' => $rol, 'email_verified_at' => now(), 'centro_educativo_id' => $centro,
        ]);
    }

    public function test_que_abre_cada_rol_de_central(): void
    {
        $esperado = [
            // rol => [Red DuaLab, Seguimiento de prácticas, Papelera (Datos)]
            User::ROLE_RED_DUALAB        => [200, 403, 403],
            User::ROLE_GESTION_PRACTICAS => [403, 200, 403],
            User::ROLE_ADMIN_CENTRAL_DUALAB    => [200, 200, 403],
            User::ROLE_SUPERADMIN        => [200, 200, 200],
        ];
        foreach ($esperado as $rol => [$red, $practicas, $papelera]) {
            Sanctum::actingAs($this->usuario($rol, "rol{$rol}@test.local"));
            $this->getJson('/api/central/solicitudes-reto')->assertStatus($red);
            $this->getJson('/api/central/destacados')->assertStatus($practicas);
            $this->getJson('/api/papelera')->assertStatus($papelera);
        }
    }

    public function test_red_dualab_consulta_empresas_y_centros_sin_editar(): void
    {
        $centro = DB::table('centro_educativo')->insertGetId(['nombre' => 'IES Consulta', 'municipio' => 'Las Palmas']);
        $otro = DB::table('centro_educativo')->insertGetId(['nombre' => 'IES Otro']);
        $propia = DB::table('empresas')->insertGetId(['nombre_comercial' => 'Hotel Faro', 'sector' => 'Hostelería', 'centro_id' => $centro, 'centro_educativo' => 'IES Consulta']);
        $captada = DB::table('empresas')->insertGetId(['nombre_comercial' => 'Taller Norte', 'sector' => 'Automoción', 'centro_id' => $otro]);
        DB::table('centro_empresa')->insert(['centro_id' => $centro, 'empresa_id' => $captada]);
        $this->usuario(User::ROLE_DOCENTE, 'profe@test.local', $centro);

        Sanctum::actingAs($this->usuario(User::ROLE_RED_DUALAB, 'red@test.local'));
        // Las del centro: la suya y la captada que se colocó allí, con sus centros
        $this->getJson("/api/central/empresas?centro_id={$centro}")->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.nombre', 'Taller Norte')
            ->assertJsonPath('data.1.centros', ['IES Otro', 'IES Consulta']);
        $this->getJson('/api/central/empresas?buscar=hostel')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/central/centros?buscar=consulta')->assertOk()
            ->assertJsonPath('data.0.empresas', 2)->assertJsonPath('data.0.docentes', 1)
            ->assertJsonPath('data.0.municipio', 'Las Palmas');
        // Solo lectura: ni editar ni borrar
        $this->putJson("/api/empresas/{$propia}", ['nombre_comercial' => 'X'])->assertForbidden();
        $this->deleteJson("/api/empresas/{$propia}")->assertForbidden();

        // Seguimiento de prácticas no entra
        Sanctum::actingAs($this->usuario(User::ROLE_GESTION_PRACTICAS, 'practicas@test.local'));
        $this->getJson('/api/central/empresas')->assertForbidden();
        $this->getJson('/api/central/centros')->assertForbidden();
    }

    public function test_lecturas_compartidas_solo_para_los_roles_de_la_ruta(): void
    {
        $esperado = [
            // rol => [GET /empresas, GET /microretos]
            User::ROLE_DOCENTE              => [200, 200],
            User::ROLE_EMPRESA              => [200, 200],
            User::ROLE_SUPERADMIN           => [200, 200],
            User::ROLE_ADMIN_CENTRAL_DUALAB => [200, 403], // Usuarios de Central: asignar empresa a una cuenta
            User::ROLE_RED_DUALAB           => [403, 403],
            User::ROLE_GESTION_PRACTICAS    => [403, 403],
            User::ROLE_COMERCIAL            => [403, 403],
        ];
        foreach ($esperado as $rol => [$empresas, $microretos]) {
            Sanctum::actingAs($this->usuario($rol, "lectura{$rol}@test.local"));
            $this->getJson('/api/empresas')->assertStatus($empresas);
            $this->getJson('/api/microretos')->assertStatus($microretos);
        }
    }

    public function test_admin_central_gestiona_usuarios_pero_no_superadmins(): void
    {
        $super = $this->usuario(User::ROLE_SUPERADMIN, 'super@test.local');
        Sanctum::actingAs($this->usuario(User::ROLE_ADMIN_CENTRAL_DUALAB, 'admincentral@test.local'));
        $alta = fn (string $email, int $rol) => ['name' => 'Nueva', 'email' => $email, 'role' => $rol,
            'password' => self::CLAVE, 'password_confirmation' => self::CLAVE];

        $this->getJson('/api/admin/usuarios')->assertOk();
        $this->postJson('/api/admin/usuarios', $alta('red@test.local', User::ROLE_RED_DUALAB))->assertCreated();
        // Ni crear superadmins ni tocar sus cuentas
        $this->postJson('/api/admin/usuarios', $alta('otro-super@test.local', User::ROLE_SUPERADMIN))
            ->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->patchJson("/api/admin/usuarios/{$super->id}", ['name' => 'X', 'email' => $super->email, 'role' => User::ROLE_DOCENTE])->assertForbidden();
        $this->patchJson("/api/admin/usuarios/{$super->id}/bloquear")->assertForbidden();
    }

    public function test_solo_un_superadmin_crea_superadmins(): void
    {
        $nuevo = fn (string $email) => [
            'name' => 'Nueva', 'email' => $email, 'role' => User::ROLE_SUPERADMIN,
            'password' => self::CLAVE, 'password_confirmation' => self::CLAVE,
        ];

        // Superadmin: la crea ya activa y sin centro
        Sanctum::actingAs($this->usuario(User::ROLE_SUPERADMIN, 'super@test.local'));
        $this->postJson('/api/admin/usuarios', $nuevo('nueva-super@test.local'))->assertCreated();
        $creada = User::where('email', 'nueva-super@test.local')->firstOrFail();
        $this->assertTrue($creada->isSuperAdmin());
        $this->assertNotNull($creada->email_verified_at);
        $this->assertNull($creada->centro_educativo_id);

        // Admin Central DuaLab también se puede crear
        $this->postJson('/api/admin/usuarios', ['role' => User::ROLE_ADMIN_CENTRAL_DUALAB] + $nuevo('central@test.local'))->assertCreated();
        $this->assertSame(User::ROLE_ADMIN_CENTRAL_DUALAB, User::where('email', 'central@test.local')->value('role'));

        // Admin de centro: su alta siempre es de docente, aunque pida superadmin
        $centro = DB::table('centro_educativo')->insertGetId(['nombre' => 'IES Roles']);
        Sanctum::actingAs($this->usuario(User::ROLE_ADMIN, 'admin@test.local', $centro));
        $this->postJson('/api/admin/usuarios', $nuevo('intento@test.local'))->assertCreated();
        $this->assertSame(User::ROLE_DOCENTE, User::where('email', 'intento@test.local')->value('role'));

        // Nadie asciende a superadmin editando una cuenta
        Sanctum::actingAs(User::where('email', 'super@test.local')->first());
        $docente = User::where('email', 'intento@test.local')->first();
        $this->patchJson("/api/admin/usuarios/{$docente->id}", ['name' => 'X', 'email' => $docente->email, 'role' => User::ROLE_SUPERADMIN])
            ->assertUnprocessable()->assertJsonValidationErrors('role');
    }
}
