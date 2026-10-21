<?php

namespace Tests\Feature;

use App\Models\Encuentro;
use App\Models\Equipo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Quién ve el alumnado de un encuentro.
 *
 * - Un docente ve sus encuentros y aquellos en los que el propietario le ha dado permiso
 *   como colaborador. Solo se puede invitar a compañeros del mismo centro que compartan
 *   familia y módulo con el proyecto (los admins del centro, siempre).
 * - Un colaborador de solo lectura ve el alumnado y el progreso, pero nunca los códigos ni
 *   tokens de acceso al workspace del alumnado, ni puede mutar nada.
 * - El admin ve todo su centro; nadie ve encuentros de otro centro.
 *
 * Usa la BD `testing` de Sail (MySQL: varias migraciones son SQL de MySQL).
 */
class VisibilidadAlumnadoTest extends TestCase
{
    use RefreshDatabase;

    private User $ana;      // propietaria del encuentro (centro A, módulo M1)
    private User $luis;     // compañero de centro A con M1 (comparte familia y módulo)
    private User $marta;    // compañera de centro A con M3 (otra familia)
    private User $pedro;    // docente de centro B con M1
    private User $adminA;
    private int $centroA;
    private int $centroB;
    private int $m1;
    private int $m3;
    private int $mFueraOferta;
    private Encuentro $encuentro;
    private Equipo $equipo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centroA = DB::table('centro_educativo')->insertGetId(['nombre' => 'IES A']);
        $this->centroB = DB::table('centro_educativo')->insertGetId(['nombre' => 'IES B']);
        $informatica   = DB::table('familias')->insertGetId(['nombre' => 'Informática']);
        $administracion = DB::table('familias')->insertGetId(['nombre' => 'Administración']);

        $c1 = DB::table('ciclos_formativos')->insertGetId(['nombre' => 'DAW', 'siglasGrado' => 'GS', 'familia_id' => $informatica]);
        $c2 = DB::table('ciclos_formativos')->insertGetId(['nombre' => 'Gestión Administrativa', 'siglasGrado' => 'GM', 'familia_id' => $administracion]);
        $c3 = DB::table('ciclos_formativos')->insertGetId(['nombre' => 'Ciclo sin oferta', 'siglasGrado' => 'GS', 'familia_id' => $informatica]);

        $this->m1 = DB::table('modulos')->insertGetId(['idcicloformativo' => $c1, 'nombre' => 'Programación', 'curso' => 1]);
        DB::table('modulos')->insertGetId(['idcicloformativo' => $c1, 'nombre' => 'Bases de datos', 'curso' => 1]);
        $this->m3 = DB::table('modulos')->insertGetId(['idcicloformativo' => $c2, 'nombre' => 'Contabilidad', 'curso' => 1]);
        $this->mFueraOferta = DB::table('modulos')->insertGetId(['idcicloformativo' => $c3, 'nombre' => 'Fuera de oferta', 'curso' => 1]);

        DB::table('centro_ciclo')->insert([
            ['centro_id' => $this->centroA, 'centro_educativo' => 'IES A', 'ciclo_id' => $c1],
            ['centro_id' => $this->centroA, 'centro_educativo' => 'IES A', 'ciclo_id' => $c2],
            ['centro_id' => $this->centroB, 'centro_educativo' => 'IES B', 'ciclo_id' => $c1],
        ]);

        $this->ana    = $this->usuario('Ana', User::ROLE_DOCENTE, $this->centroA, [$this->m1]);
        $this->luis   = $this->usuario('Luis', User::ROLE_DOCENTE, $this->centroA, [$this->m1]);
        $this->marta  = $this->usuario('Marta', User::ROLE_DOCENTE, $this->centroA, [$this->m3]);
        $this->pedro  = $this->usuario('Pedro', User::ROLE_DOCENTE, $this->centroB, [$this->m1]);
        $this->adminA = $this->usuario('Admin A', User::ROLE_ADMIN, $this->centroA);

        $proyectoId = DB::table('microproyectos')->insertGetId([
            'uuid' => (string) Str::uuid(), 'titulo' => 'Proyecto de Ana', 'estado' => 'validado',
            'user_id' => $this->ana->id, 'centro_id' => $this->centroA, 'familia_id' => $informatica, 'ciclo_id' => $c1,
            'modulos_seleccionados' => json_encode([['id' => $this->m1, 'nombre' => 'Programación']]),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->encuentro = Encuentro::create([
            'user_id' => $this->ana->id, 'microproyecto_id' => $proyectoId, 'fecha' => now()->toDateString(),
            'centro_educativo_id' => $this->centroA, 'curso' => '1º', 'grupo' => 'A', 'num_equipos' => 1,
            'codigo_clase' => 'ABC-123',
        ]);
        $this->equipo = Equipo::create(['microproyecto_id' => $proyectoId, 'encuentro_id' => $this->encuentro->id, 'nombre' => 'Equipo 1', 'numero_equipo' => 1]);
        $this->equipo->miembros()->create(['alias' => 'Lucía Panda Azul']);
    }

    /** @param array<int, int> $modulos */
    private function usuario(string $nombre, int $rol, int $centro, array $modulos = []): User
    {
        $user = User::forceCreate([
            'name' => $nombre, 'email' => Str::slug($nombre) . '@test.local', 'password' => bcrypt('x'),
            'role' => $rol, 'centro_educativo_id' => $centro, 'email_verified_at' => now(),
        ]);
        $user->modulos()->sync($modulos);

        return $user;
    }

    private function invitar(User $colaborador, bool $puedeEditar): void
    {
        Sanctum::actingAs($this->ana);
        $this->postJson("/api/encuentros/{$this->encuentro->id}/colaboradores", [
            'user_id' => $colaborador->id, 'puede_editar' => $puedeEditar,
        ])->assertCreated();
    }

    public function test_candidatos_solo_compañeros_del_centro_que_comparten_familia_y_modulo(): void
    {
        Sanctum::actingAs($this->ana);
        $ids = collect($this->getJson("/api/encuentros/{$this->encuentro->id}/colaboradores/candidatos")->assertOk()->json())->pluck('id');

        $this->assertTrue($ids->contains($this->luis->id));
        $this->assertTrue($ids->contains($this->adminA->id));
        $this->assertFalse($ids->contains($this->marta->id), 'Otra familia: no es candidata');
        $this->assertFalse($ids->contains($this->pedro->id), 'Otro centro: no es candidato');
    }

    public function test_no_se_puede_invitar_a_quien_no_comparte_familia_y_modulo_ni_a_otro_centro(): void
    {
        Sanctum::actingAs($this->ana);
        $this->postJson("/api/encuentros/{$this->encuentro->id}/colaboradores", ['user_id' => $this->marta->id])->assertUnprocessable();
        $this->postJson("/api/encuentros/{$this->encuentro->id}/colaboradores", ['user_id' => $this->pedro->id])->assertUnprocessable();
    }

    public function test_sin_permiso_un_compañero_no_ve_el_encuentro_ni_su_alumnado(): void
    {
        Sanctum::actingAs($this->luis);
        $this->assertEmpty($this->getJson('/api/encuentros')->assertOk()->json());
        $this->assertEmpty($this->getJson('/api/alumnado')->assertOk()->json());
        $this->getJson("/api/encuentros/{$this->encuentro->id}/workspace")->assertNotFound();
    }

    public function test_colaborador_de_solo_lectura_ve_el_alumnado_pero_no_los_codigos_ni_puede_mutar(): void
    {
        $this->invitar($this->luis, false);
        Sanctum::actingAs($this->luis);

        $encuentro = collect($this->getJson('/api/encuentros')->assertOk()->json())->firstWhere('id', $this->encuentro->id);
        $this->assertNotNull($encuentro);
        $this->assertFalse($encuentro['puede_editar']);
        $this->assertNull($encuentro['codigo_clase']);

        $workspace = $this->getJson("/api/encuentros/{$this->encuentro->id}/workspace")->assertOk()->json();
        $this->assertSame('Lucía Panda Azul', $workspace['equipos'][0]['miembros'][0]['alias']);
        $this->assertNull($workspace['equipos'][0]['token']);
        $this->assertNull($workspace['equipos'][0]['codigo_acceso']);

        $misGrupos = $this->getJson('/api/encuentros/mis-grupos')->assertOk()->json();
        $this->assertNull($misGrupos[0]['equipos'][0]['token']);
        $this->assertNull($misGrupos[0]['encuentro']['codigo_clase']);

        $alias = collect($this->getJson('/api/alumnado')->assertOk()->json())->pluck('alias');
        $this->assertTrue($alias->contains('Lucía Panda Azul'));

        $uuid = DB::table('microproyectos')->where('id', $this->encuentro->microproyecto_id)->value('uuid');
        $this->getJson("/api/startup/proyectos/{$uuid}/pantalla-acceso")->assertNotFound();
        $this->getJson("/api/startup/proyectos/{$uuid}/equipos")->assertOk()->assertJsonPath('equipos.0.token', null);
        $this->postJson("/api/encuentros/{$this->encuentro->id}/crear-codigo")->assertNotFound();
        $this->deleteJson("/api/encuentros/{$this->encuentro->id}")->assertNotFound();
    }

    public function test_colaborador_con_edicion_si_recibe_los_codigos(): void
    {
        $this->invitar($this->luis, true);
        Sanctum::actingAs($this->luis);

        $uuid = DB::table('microproyectos')->where('id', $this->encuentro->microproyecto_id)->value('uuid');
        $this->getJson("/api/startup/proyectos/{$uuid}/pantalla-acceso")->assertOk()
            ->assertJsonPath('equipos.0.token', $this->equipo->token);
        $this->getJson("/api/encuentros/{$this->encuentro->id}/workspace")->assertOk()
            ->assertJsonPath('equipos.0.token', $this->equipo->token);
    }

    public function test_un_docente_de_otro_centro_nunca_ve_el_encuentro(): void
    {
        Sanctum::actingAs($this->pedro);
        $this->getJson("/api/encuentros/{$this->encuentro->id}/workspace")->assertNotFound();
        $this->assertEmpty($this->getJson('/api/alumnado')->assertOk()->json());
    }

    public function test_el_admin_ve_todo_su_centro_con_codigos(): void
    {
        Sanctum::actingAs($this->adminA);
        $encuentro = collect($this->getJson('/api/encuentros')->assertOk()->json())->firstWhere('id', $this->encuentro->id);
        $this->assertNotNull($encuentro);
        $this->assertSame('ABC-123', $encuentro['codigo_clase']);
    }

    public function test_el_docente_solo_puede_asignarse_modulos_de_la_oferta_de_su_centro(): void
    {
        Sanctum::actingAs($this->luis);
        $this->putJson('/api/perfil/modulos', ['modulo_ids' => [$this->m1, $this->m3]])->assertOk()
            ->assertJsonPath('asignados', fn ($ids) => count($ids) === 2);
        $this->putJson('/api/perfil/modulos', ['modulo_ids' => [$this->mFueraOferta]])->assertUnprocessable();

        Sanctum::actingAs($this->pedro); // el centro B no oferta el ciclo de M3
        $this->putJson('/api/perfil/modulos', ['modulo_ids' => [$this->m3]])->assertUnprocessable();
    }

    public function test_un_modulo_que_sale_de_la_oferta_no_bloquea_guardar(): void
    {
        // Luis tiene M1; el centro A deja de ofertar su ciclo
        DB::table('centro_ciclo')->where('centro_id', $this->centroA)
            ->where('ciclo_id', DB::table('modulos')->where('id', $this->m1)->value('idcicloformativo'))->delete();

        Sanctum::actingAs($this->luis);
        $this->getJson('/api/perfil/modulos')->assertOk()->assertJsonPath('asignados', []);
        // El frontend reenvía lo que tenía (M1) más el nuevo (M3): M1 se descarta, no da error
        $this->putJson('/api/perfil/modulos', ['modulo_ids' => [$this->m1, $this->m3]])->assertOk()
            ->assertJsonPath('asignados', [$this->m3]);
        $this->assertSame([$this->m3], $this->luis->modulos()->pluck('modulos.id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_el_admin_solo_gestiona_los_modulos_de_docentes_de_su_centro(): void
    {
        Sanctum::actingAs($this->adminA);
        $this->putJson("/api/admin/usuarios/{$this->luis->id}/modulos", ['modulo_ids' => [$this->m3]])->assertOk();
        $this->getJson("/api/admin/usuarios/{$this->pedro->id}/modulos")->assertForbidden();
        $this->putJson("/api/admin/usuarios/{$this->pedro->id}/modulos", ['modulo_ids' => [$this->m1]])->assertForbidden();
    }
}
