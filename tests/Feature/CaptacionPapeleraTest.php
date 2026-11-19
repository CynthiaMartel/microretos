<?php

namespace Tests\Feature;

use App\Models\CaptacionOrigen;
use App\Models\CentroCaptacion;
use App\Models\EmpresaCaptacion;
use App\Models\MicroretoBorrador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Central → Captaciones (19-nov): las empresas «en curso» (la comercial aún la entrevista) y sus retos se
 * ven; empresas, centros y retos captados van a la papelera (superadmin y Admin Central DuaLab), quedan
 * en el Historial y una empresa captada con firma nunca se borra del todo.
 *
 * Usa la BD `testing` de Sail (MySQL: varias migraciones son SQL de MySQL).
 */
class CaptacionPapeleraTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(int $rol, string $email): User
    {
        return User::forceCreate(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'password' => bcrypt('x'), 'role' => $rol, 'email_verified_at' => now()]);
    }

    private function empresa(string $nombre, string $estado): EmpresaCaptacion
    {
        $captacion = new EmpresaCaptacion(['nombre_comercial' => $nombre]);
        $captacion->forceFill(['origen_id' => CaptacionOrigen::ENTREVISTA, 'estado' => $estado])->save();

        return $captacion;
    }

    private function reto(EmpresaCaptacion $captacion, string $titulo): MicroretoBorrador
    {
        $borrador = new MicroretoBorrador();
        $borrador->forceFill(['empresa_captacion_id' => $captacion->id, 'datos' => ['titulo' => $titulo], 'estado' => MicroretoBorrador::ESTADO_PENDIENTE])->save();

        return $borrador;
    }

    public function test_en_curso_y_a_la_papelera(): void
    {
        $enCurso = $this->empresa('Panadería Sol', EmpresaCaptacion::ESTADO_BORRADOR);
        $pendiente = $this->empresa('Taller Norte', EmpresaCaptacion::ESTADO_PENDIENTE_VALIDACION);
        $retoEnCurso = $this->reto($enCurso, 'Hornear con menos merma');
        $this->reto($pendiente, 'Ordenar el taller');
        $centro = new CentroCaptacion(['centro_nombre' => 'IES Captado', 'contacto_nombre' => 'Ana', 'contacto_email' => 'ana@ies.test']);
        $centro->forceFill(['origen_id' => CaptacionOrigen::FORMULARIO])->save();

        Sanctum::actingAs($this->usuario(User::ROLE_ADMIN_CENTRAL_DUALAB, 'admincentral@test.local'));
        // En curso: la empresa en borrador y su reto, aparte de los pendientes de validar
        $this->getJson('/api/captacion/empresas?estado=borrador')->assertOk()->assertJsonPath('data.0.nombre_comercial', 'Panadería Sol');
        $this->getJson('/api/captacion/borradores?en_curso=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.reto.titulo', 'Hornear con menos merma');
        $this->getJson('/api/captacion/borradores')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.reto.titulo', 'Ordenar el taller');

        // A la papelera, con su registro crítico en el Historial
        $this->deleteJson("/api/captacion/borradores/{$retoEnCurso->uuid}/papelera")->assertOk();
        $this->deleteJson("/api/captacion/empresas/{$pendiente->id}")->assertOk();
        $this->deleteJson("/api/captacion/centros/{$centro->id}")->assertOk();
        $this->assertSoftDeleted($retoEnCurso);
        $this->assertSoftDeleted($pendiente);
        $this->assertSoftDeleted($centro);
        $this->getJson('/api/captacion/borradores')->assertOk()->assertJsonCount(0, 'data');
        $this->assertSame(3, DB::table('actividad_central')->where('seccion', 'captaciones')->where('critica', true)->count());

        // Red DuaLab ve las captaciones pero no borra
        Sanctum::actingAs($this->usuario(User::ROLE_RED_DUALAB, 'red@test.local'));
        $this->getJson('/api/captacion/empresas?estado=borrador')->assertOk();
        $this->deleteJson("/api/captacion/empresas/{$enCurso->id}")->assertForbidden();
    }

    public function test_una_empresa_captada_con_firma_no_se_borra_del_todo(): void
    {
        $captacion = $this->empresa('Hotel Faro', EmpresaCaptacion::ESTADO_PENDIENTE_VALIDACION);
        $fila = ['uuid' => (string) \Illuminate\Support\Str::uuid(), 'empresa_captacion_id' => $captacion->id,
            'firmante_nombre' => 'Luis', 'firmante_dni' => 'cifrado', 'firma_path' => 'firmas/x.png', 'snapshot' => '{}',
            'documento_sha256' => str_repeat('a', 64), 'firmado_at' => now(), 'created_at' => now(), 'updated_at' => now()];
        DB::table('empresa_firmas')->insert($fila);
        $captacion->delete();

        Sanctum::actingAs($this->usuario(User::ROLE_SUPERADMIN, 'super@test.local'));
        $this->deleteJson("/api/papelera/captaciones_empresa/{$captacion->id}")->assertUnprocessable();
        $this->deleteJson('/api/papelera')->assertOk();
        $this->assertSoftDeleted($captacion);
        $this->patchJson("/api/papelera/captaciones_empresa/{$captacion->id}/restaurar")->assertOk();
        $this->assertNotSoftDeleted($captacion);
    }
}
