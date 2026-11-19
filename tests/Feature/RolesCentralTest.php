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
