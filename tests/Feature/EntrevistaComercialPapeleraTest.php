<?php

namespace Tests\Feature;

use App\Models\CaptacionOrigen;
use App\Models\EmpresaCaptacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * App comercial: papelera de entrevistas en curso. Mover a la papelera pide la contraseña de
 * quien borra (la comprueba el backend) y solo vale para las entrevistas sin firmar.
 *
 * Usa la BD `testing` de Sail (MySQL: varias migraciones son SQL de MySQL).
 */
class EntrevistaComercialPapeleraTest extends TestCase
{
    use RefreshDatabase;

    private User $comercial;
    private EmpresaCaptacion $captacion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comercial = $this->comercial('Carla', 'carla@test.local');
        $this->captacion = $this->captacion($this->comercial);
    }

    private function comercial(string $nombre, string $email): User
    {
        return User::forceCreate([
            'name' => $nombre, 'email' => $email, 'password' => bcrypt('Secreta#1'),
            'role' => User::ROLE_COMERCIAL, 'email_verified_at' => now(),
        ]);
    }

    private function captacion(User $comercial, string $estado = EmpresaCaptacion::ESTADO_BORRADOR): EmpresaCaptacion
    {
        $captacion = new EmpresaCaptacion(['nombre_comercial' => 'Panadería Sol']);
        $captacion->forceFill([
            'origen_id' => CaptacionOrigen::ENTREVISTA, 'creada_por_user_id' => $comercial->id, 'estado' => $estado,
        ])->save();

        return $captacion;
    }

    public function test_mueve_una_entrevista_en_curso_a_la_papelera_y_la_restaura(): void
    {
        Sanctum::actingAs($this->comercial);

        $this->deleteJson("/api/comercial/empresas/{$this->captacion->id}", ['password' => 'Secreta#1'])->assertOk();
        $this->assertSoftDeleted($this->captacion);
        $this->getJson('/api/comercial/empresas')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/comercial/empresas/{$this->captacion->id}")->assertNotFound();
        $this->getJson('/api/comercial/papelera')->assertOk()
            ->assertJsonPath('data.0.id', $this->captacion->id)
            ->assertJsonPath('data.0.nombre_comercial', 'Panadería Sol');
        $this->assertNotNull($this->getJson('/api/comercial/papelera')->json('data.0.eliminada_at'));

        $this->patchJson("/api/comercial/papelera/{$this->captacion->id}/restaurar")->assertOk()
            ->assertJsonPath('data.eliminada_at', null);
        $this->assertNotSoftDeleted($this->captacion);
        $this->getJson('/api/comercial/papelera')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_sin_contrasena_o_con_una_incorrecta_no_se_borra(): void
    {
        Sanctum::actingAs($this->comercial);

        $this->deleteJson("/api/comercial/empresas/{$this->captacion->id}")
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->deleteJson("/api/comercial/empresas/{$this->captacion->id}", ['password' => 'otra'])
            ->assertUnprocessable()->assertJsonPath('errors.password.0', 'La contraseña no es correcta.');
        $this->assertNotSoftDeleted($this->captacion);
    }

    public function test_una_entrevista_firmada_no_se_puede_borrar(): void
    {
        $firmada = $this->captacion($this->comercial, EmpresaCaptacion::ESTADO_PENDIENTE_VALIDACION);
        Sanctum::actingAs($this->comercial);

        $this->deleteJson("/api/comercial/empresas/{$firmada->id}", ['password' => 'Secreta#1'])->assertForbidden();
        $this->assertNotSoftDeleted($firmada);
    }

    public function test_otro_comercial_ni_borra_ni_ve_ni_restaura_lo_ajeno(): void
    {
        $otro = $this->comercial('Otro', 'otro@test.local');
        Sanctum::actingAs($otro);

        $this->deleteJson("/api/comercial/empresas/{$this->captacion->id}", ['password' => 'Secreta#1'])->assertNotFound();
        $this->assertNotSoftDeleted($this->captacion);

        $this->captacion->delete();
        $this->getJson('/api/comercial/papelera')->assertOk()->assertJsonCount(0, 'data');
        $this->patchJson("/api/comercial/papelera/{$this->captacion->id}/restaurar")->assertNotFound();
        $this->assertSoftDeleted($this->captacion);
    }

    public function test_el_enlace_del_correo_no_funciona_mientras_esta_en_la_papelera(): void
    {
        $token = str_repeat('a', 64);
        $this->captacion->forceFill([
            'formulario_token_hash' => hash('sha256', $token), 'formulario_expira_at' => now()->addDays(7),
            'formulario_enviado_at' => now(),
        ])->save();
        $this->getJson("/api/public/formulario-empresa/{$token}")->assertOk();

        $this->captacion->delete();
        $this->getJson("/api/public/formulario-empresa/{$token}")->assertNotFound();
    }
}
