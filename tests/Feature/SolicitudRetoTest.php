<?php

namespace Tests\Feature;

use App\Jobs\EnviarCorreoInfoJob;
use App\Mail\SolicitudRetoMail;
use App\Mail\SolicitudRetoRespondidaMail;
use App\Mail\SolicitudRetoAutorizadosMail;
use App\Mail\SolicitudRetoRetosListosMail;
use App\Models\Microreto;
use App\Models\SolicitudReto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Solicitudes de retos de docentes: DuaLab envía el enlace, el docente rellena sin cuenta, DuaLab
 * genera los retos (que van a la recámara) y avisa a mano al docente.
 *
 * Usa la BD `testing` de Sail (MySQL: varias migraciones son SQL de MySQL).
 */
class SolicitudRetoTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;
    private int $familiaId;
    private int $cicloId;
    private int $moduloId;
    private int $raId;
    private int $ceId;
    private int $centroId;
    private int $empresaId;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        // Nunca contra la web de verdad: las peticiones que no estén simuladas fallan
        Http::preventStrayRequests();

        $this->superadmin = $this->usuario(User::ROLE_SUPERADMIN, 'super@test.local');

        DB::table('familias')->insertOrIgnore(['nombre' => 'Administración y Gestión']);
        $this->familiaId = (int) DB::table('familias')->where('nombre', 'Administración y Gestión')->value('id');
        $this->cicloId   = DB::table('ciclos_formativos')->insertGetId([
            'nombre' => 'Gestión Administrativa (prueba)', 'siglasGrado' => 'GM', 'familia_id' => $this->familiaId,
        ]);
        $this->moduloId = DB::table('modulos')->insertGetId([
            'idcicloformativo' => $this->cicloId, 'nombre' => 'Comunicación empresarial y atención al cliente', 'curso' => 1,
        ]);
        $this->raId = DB::table('resultados_aprendizaje')->insertGetId([
            'idmodulo' => $this->moduloId, 'ra' => 'Transmite información escrita, aplicando las técnicas de estilo.',
        ]);
        $this->ceId = DB::table('criterios_evaluacion')->insertGetId([
            'idresultadoaprendizaje' => $this->raId, 'ce' => 'Se ha registrado la entrada y salida de correspondencia.',
        ]);
        $this->centroId  = DB::table('centro_educativo')->insertGetId(['nombre' => 'IES Prueba Solicitudes']);
        $this->empresaId = DB::table('empresas')->insertGetId(['nombre_comercial' => 'Gestoría Prueba', 'centro_id' => $this->centroId]);
    }

    public function test_dualab_crea_la_solicitud_y_el_docente_recibe_el_enlace(): void
    {
        $token = $this->crearSolicitud();

        // La referencia va en el asunto y las respuestas a info@, para separarlas en Central
        $solicitud = SolicitudReto::firstOrFail();
        Mail::assertQueued(SolicitudRetoMail::class, fn ($m) => $m->hasTo('profe@centro.test')
            && str_ends_with($m->envelope()->subject, "Ref. SR-{$solicitud->id}")
            && $m->hasReplyTo(config('solicitudes_reto.responder_a.email')));
        $this->getJson("/api/public/solicitud-reto/{$token}")
            ->assertOk()
            ->assertJsonPath('data.estado', 'pendiente')
            ->assertJsonPath('data.nombreDocente', 'Marta')
            ->assertJsonMissingPath('data.notas');
    }

    public function test_atencion_al_cliente_puede_enviarla_y_un_docente_no(): void
    {
        Sanctum::actingAs($this->usuario(User::ROLE_RED_DUALAB, 'atencion@test.local'));
        $this->postJson('/api/central/solicitudes-reto', $this->datosNueva())->assertCreated();

        Sanctum::actingAs($this->usuario(User::ROLE_DOCENTE, 'docente@test.local', $this->centroId));
        $this->postJson('/api/central/solicitudes-reto', $this->datosNueva())->assertForbidden();
    }

    public function test_el_docente_guarda_a_medias_y_envia(): void
    {
        $token = $this->crearSolicitud();

        $this->putJson("/api/public/solicitud-reto/{$token}", $this->privacidad() + ['bloques' => [['familiaId' => $this->familiaId]]])->assertOk();
        // Al enviar, el tipo de propuesta de cada bloque es obligatorio
        $this->postJson("/api/public/solicitud-reto/{$token}/enviar", $this->respuestas([], ['tipoPropuesta' => '']))
            ->assertUnprocessable()->assertJsonValidationErrors('bloques.0.tipoPropuesta');

        $this->postJson("/api/public/solicitud-reto/{$token}/enviar", $this->respuestas())->assertOk();

        $solicitud = SolicitudReto::firstOrFail();
        $this->assertSame(SolicitudReto::ESTADO_RESPONDIDA, $solicitud->estado);
        $this->assertSame($this->familiaId, $solicitud->respuestas['bloques'][0]['familiaId']);
        $this->assertSame('2099-11-25', $solicitud->sesion_fecha->format('Y-m-d'));
        Mail::assertQueued(SolicitudRetoRespondidaMail::class, fn ($m) => $m->hasTo('super@test.local'));

        // Ya enviada: no admite cambios
        $this->postJson("/api/public/solicitud-reto/{$token}/enviar", $this->respuestas())->assertStatus(410);
    }

    public function test_rechaza_curriculo_que_no_encaja(): void
    {
        $token = $this->crearSolicitud();
        $otroCiclo = DB::table('ciclos_formativos')->insertGetId(['nombre' => 'Otro ciclo', 'siglasGrado' => 'GS', 'familia_id' => null]);

        $this->postJson("/api/public/solicitud-reto/{$token}/enviar", $this->respuestas([], ['cicloId' => $otroCiclo]))
            ->assertUnprocessable()->assertJsonValidationErrors('bloques.0.cicloId');

        $otroRa = DB::table('resultados_aprendizaje')->insertGetId(['idmodulo' => 999999, 'ra' => 'RA ajeno']);
        $this->postJson("/api/public/solicitud-reto/{$token}/enviar", $this->respuestas([], [
            'seleccionRaCe' => [['ra_id' => $otroRa, 'ce_ids' => [$this->ceId]]],
        ]))->assertUnprocessable()->assertJsonValidationErrors('bloques.0.seleccionRaCe');
    }

    public function test_datos_para_el_generador_solo_para_superadmin(): void
    {
        $solicitud = $this->solicitudRespondida();

        Sanctum::actingAs($this->usuario(User::ROLE_RED_DUALAB, 'atencion@test.local'));
        $this->getJson("/api/central/solicitudes-reto/{$solicitud->id}/generador")->assertForbidden();

        Sanctum::actingAs($this->superadmin);
        $datos = $this->getJson("/api/central/solicitudes-reto/{$solicitud->id}/generador")
            ->assertOk()
            ->assertJsonPath('data.familia', 'Administración y Gestión')
            ->assertJsonPath('data.cicloId', $this->cicloId)
            ->assertJsonPath('data.nivelGrupo', 'Bajo')
            ->assertJsonPath('data.seleccionRaCe.0.modulo_id', $this->moduloId)
            ->json('data');

        $this->assertStringContainsString('primera vez (UT3)', $datos['enfoqueReto']);
        $this->assertStringContainsString('1 h 50 min', $datos['enfoqueReto']);
        // Las tareas en la empresa van en su propio campo del generador, no dentro del enfoque
        $this->assertSame('Registrar correspondencia y paquetería.', $datos['tareasPracticas']);
        $this->assertStringNotContainsString('Registrar correspondencia', $datos['enfoqueReto']);
    }

    public function test_los_retos_de_una_solicitud_van_a_la_recamara(): void
    {
        $solicitud = $this->solicitudRespondida();

        // Un docente no puede guardar retos de una solicitud
        $docente = $this->usuario(User::ROLE_DOCENTE, 'docente@test.local', $this->centroId);
        Sanctum::actingAs($docente);
        $this->postJson('/api/guardar-microretos-lote', $this->lote($solicitud->id))->assertForbidden();

        Sanctum::actingAs($this->superadmin);
        $this->postJson('/api/guardar-microretos-lote', $this->lote($solicitud->id))->assertCreated();
        $reto = Microreto::firstOrFail();
        $this->assertTrue($reto->en_recamara);
        $this->assertSame($solicitud->id, $reto->solicitud_reto_id);

        // Aunque la empresa es de su centro, el docente no lo ve
        Sanctum::actingAs($docente);
        $this->getJson('/api/microretos')->assertOk()->assertJsonCount(0);
        $this->getJson("/api/microretos/{$reto->uuid}")->assertNotFound();
    }

    public function test_aviso_manual_con_los_retos_elegidos(): void
    {
        $solicitud = $this->solicitudRespondida();
        Sanctum::actingAs($this->superadmin);
        $this->postJson('/api/guardar-microretos-lote', $this->lote($solicitud->id, 2))->assertCreated();
        [$elegido, $descartado] = Microreto::orderBy('id')->pluck('uuid')->all();
        $ajeno = Microreto::forceCreate(['titulo' => 'Reto ajeno'])->uuid;

        $this->postJson("/api/central/solicitudes-reto/{$solicitud->id}/avisar", ['retos' => [$ajeno]])
            ->assertUnprocessable()->assertJsonValidationErrors('retos');
        Mail::assertNotQueued(SolicitudRetoRetosListosMail::class);

        $this->postJson("/api/central/solicitudes-reto/{$solicitud->id}/avisar", ['retos' => [$elegido]])
            ->assertOk()->assertJsonPath('data.fase', 'avisada');

        $enlace = null;
        Mail::assertQueued(SolicitudRetoRetosListosMail::class, function ($m) use (&$enlace) {
            $enlace = $m->enlace;
            return $m->hasTo('profe@centro.test') && $m->titulos === ['Reto 1'];
        });

        $token = substr($enlace, -64);
        $this->getJson("/api/public/solicitud-reto/{$token}/retos")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.uuid', $elegido);
        $this->assertNotSame($descartado, $elegido);
    }

    public function test_vista_previa_de_los_retos_como_los_vera_el_docente(): void
    {
        $solicitud = $this->solicitudRespondida();
        Sanctum::actingAs($this->superadmin);
        $this->postJson('/api/guardar-microretos-lote', $this->lote($solicitud->id, 2))->assertCreated();
        [$primero] = Microreto::orderBy('id')->pluck('uuid')->all();
        $ajeno = Microreto::forceCreate(['titulo' => 'Reto ajeno'])->uuid;

        // Solo los de la solicitud, con la ficha de la biblioteca; sin avisar a nadie
        $this->getJson("/api/central/solicitudes-reto/{$solicitud->id}/retos?" . http_build_query(['retos' => [$primero, $ajeno]]))
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $primero)->assertJsonPath('data.0.titulo', 'Reto 1');
        $this->getJson("/api/central/solicitudes-reto/{$solicitud->id}/retos")
            ->assertUnprocessable()->assertJsonValidationErrors('retos');
        Mail::assertNotQueued(SolicitudRetoRetosListosMail::class);
        $this->assertNull($solicitud->fresh()->avisado_at);

        Sanctum::actingAs($this->usuario(User::ROLE_DOCENTE, 'docente@test.local', $this->centroId));
        $this->getJson("/api/central/solicitudes-reto/{$solicitud->id}/retos?" . http_build_query(['retos' => [$primero]]))
            ->assertForbidden();
    }

    public function test_la_propuesta_sugiere_la_sesion_y_el_encuentro_cierra_la_solicitud(): void
    {
        $solicitud = $this->solicitudRespondida();
        Sanctum::actingAs($this->superadmin);
        $this->postJson('/api/guardar-microretos-lote', $this->lote($solicitud->id))->assertCreated();

        // El superadmin crea la propuesta del reto en nombre del docente (aún sin cuenta)
        $propuestaId = DB::table('microproyectos')->insertGetId([
            'uuid' => (string) Str::uuid(), 'titulo' => 'Propuesta beta', 'estado' => 'validado',
            'docente_validado' => true, 'user_id' => $this->superadmin->id, 'microreto_id' => Microreto::value('id'),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $propuesta = collect($this->getJson('/api/startup/proyectos')->assertOk()->json())->firstWhere('id', $propuestaId);
        $this->assertSame(['fecha' => '2099-11-25', 'hora_inicio' => '12:10', 'hora_fin' => '14:00', 'duracion' => '1 h 50 min'], $propuesta['sesion_sugerida']);

        $this->postJson('/api/encuentros', [
            'microproyecto_id' => $propuestaId, 'fecha' => '2099-11-25', 'curso' => '1º', 'grupo' => 'A',
            'num_equipos' => 1, 'alumnados' => [['alias' => 'Lucía Nutria Azul', 'equipo_num' => 1]],
        ])->assertCreated();

        $this->getJson("/api/central/solicitudes-reto/{$solicitud->id}")->assertOk()->assertJsonPath('data.fase', 'con_encuentro');
    }

    public function test_respuestas_de_los_docentes_aparte_y_asociadas_a_su_solicitud(): void
    {
        $solicitud = $this->solicitudRespondida();
        config(['frontoffice.api_url' => 'https://web.test/api-backend/public', 'frontoffice.central_secreto' => str_repeat('s', 40)]);
        $correo = fn (int $id, string $asunto, string $de = 'profe@centro.test') => [
            'id' => $id, 'de_email' => $de, 'de_nombre' => 'Marta', 'asunto' => $asunto,
            'extracto' => 'Hola', 'fecha' => "2026-11-1{$id}T10:00:00Z", 'estado' => 'nuevo', 'cuerpo_html' => '<b>no</b>',
        ];
        Http::fake(['web.test/*' => Http::response([
            'data' => [
                $correo(1, "Re: Tus retos de DuaLab están listos · Ref. SR-{$solicitud->id}"),
                $correo(2, 'Re: Ref. SR-999', 'otra@centro.test'),       // de una solicitud que no existe
                $correo(3, 'Pregunta sin referencia'),                   // del docente, sin referencia
                $correo(4, "Ref. SR-{$solicitud->id}2", 'otra@centro.test'), // otra solicitud (SR-12…)
            ],
            'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 25, 'total' => 4],
        ])]);

        Sanctum::actingAs($this->usuario(User::ROLE_RED_DUALAB, 'atencion@test.local'));
        $lista = $this->getJson('/api/central/solicitudes-reto/correos')->assertOk()->json('data');
        $this->assertSame([1, 2, 3, 4], array_column($lista, 'id'));
        $this->assertSame($solicitud->id, $lista[0]['solicitud']['id']);
        $this->assertNull($lista[1]['solicitud']);
        // Sin referencia: su solicitud, por el email del docente
        $this->assertSame($solicitud->id, $lista[2]['solicitud']['id']);
        $this->assertArrayNotHasKey('cuerpo_html', $lista[0]);
        // La web filtra: la referencia O los docentes con solicitud abierta
        Http::assertSent(fn ($r) => str_contains(urldecode($r->url()), 'incluir_asunto=Ref. SR-')
            && str_contains(urldecode($r->url()), 'incluir_de[0]=profe@centro.test'));

        // El Correo de info@ no los repite
        $this->getJson('/api/central/primer-contacto/correo')->assertOk();
        Http::assertSent(fn ($r) => str_contains(urldecode($r->url()), 'excluir_asunto=Ref. SR-')
            && str_contains(urldecode($r->url()), 'excluir_de[0]=profe@centro.test'));

        // En la ficha: los de su referencia y los que escribió desde su email, sin repetir
        $ficha = $this->getJson("/api/central/solicitudes-reto/{$solicitud->id}/correos")->assertOk()->json('data');
        $this->assertEqualsCanonicalizing([1, 3], array_column($ficha, 'id'));

        Sanctum::actingAs($this->usuario(User::ROLE_DOCENTE, 'docente@test.local', $this->centroId));
        $this->getJson('/api/central/solicitudes-reto/correos')->assertForbidden();
    }

    public function test_con_la_web_configurada_los_correos_al_docente_salen_por_info(): void
    {
        config(['frontoffice.api_url' => 'https://web.test/api-backend/public', 'frontoffice.central_secreto' => str_repeat('s', 40)]);
        Queue::fake();

        $token = $this->crearSolicitud();
        $solicitud = SolicitudReto::firstOrFail();

        Mail::assertNothingQueued();
        Queue::assertPushed(EnviarCorreoInfoJob::class, fn ($j) => $j->para === 'profe@centro.test'
            && str_ends_with($j->asunto, "Ref. SR-{$solicitud->id}")
            && str_contains($j->html, $token) && str_contains($j->texto, $token)
            && $j->usuario === 'Super');

        // El job lo pide a la web como cualquier correo de Central, con su versión maquetada
        Http::fake(['web.test/*' => Http::response(['id' => 1, 'estado' => 'pendiente'], 202)]);
        Queue::pushed(EnviarCorreoInfoJob::class)->first()->handle(app(\App\Services\FrontofficeBandejaService::class));
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/correo/enviar') && $r['tipo'] === 'nuevo'
            && $r['para'] === ['profe@centro.test'] && str_contains($r['html'], 'DUA'));
    }

    public function test_si_el_correo_falla_no_queda_la_solicitud_a_medias(): void
    {
        $this->mock(\App\Services\CorreoInfoService::class)->shouldReceive('enviar')->andThrow(new \RuntimeException('Sin plantilla'));
        Sanctum::actingAs($this->superadmin);

        $this->postJson('/api/central/solicitudes-reto', $this->datosNueva())->assertStatus(500);
        $this->assertSame(0, SolicitudReto::count());
    }

    public function test_importar_una_solicitud_recibida_por_correo(): void
    {
        $ciclo = DB::table('ciclos_formativos')->insertGetId(['nombre' => 'Ciclo importable', 'siglasGrado' => 'GM', 'familia_id' => $this->familiaId]);
        $modulo = DB::table('modulos')->insertGetId(['idcicloformativo' => $ciclo, 'nombre' => 'Módulo importable. Código: 0001', 'curso' => 1]);
        $ra = DB::table('resultados_aprendizaje')->insertGetId(['idmodulo' => $modulo, 'ra' => '3. Transmite información escrita.']);
        $ceB = DB::table('criterios_evaluacion')->insertGetId(['idresultadoaprendizaje' => $ra, 'ce' => 'b) Se han identificado los canales.']);
        DB::table('criterios_evaluacion')->insertGetId(['idresultadoaprendizaje' => $ra, 'ce' => 'c) Se han diferenciado los soportes.']);

        $archivo = tempnam(sys_get_temp_dir(), 'solicitud');
        file_put_contents($archivo, json_encode([
            'bloques' => [[
                'familia' => 'Administración y Gestión', 'ciclo' => 'Ciclo importable', 'curso' => '1',
                'modulos' => ['Módulo importable'],
                'ra_ce' => [['modulo' => 'Módulo importable', 'ra' => 3, 'ce' => ['b']]],
                'tipo_propuesta' => 'Practicar el registro de correspondencia.',
            ]],
            'nivel' => 'Bajo',
            'sesion' => ['fecha' => '2099-11-25', 'hora_inicio' => '12:10', 'hora_fin' => '14:00'],
        ]));
        $opciones = ['archivo' => $archivo, '--nombre' => 'Marta', '--email' => 'profe@centro.test', '--centro' => 'IES Prueba'];

        // Dry-run: no escribe
        $this->artisan('solicitudes-reto:importar', $opciones)->assertSuccessful();
        $this->assertSame(0, SolicitudReto::count());

        $this->artisan('solicitudes-reto:importar', $opciones + ['--commit' => true])->assertSuccessful();
        $s = SolicitudReto::firstOrFail();
        $this->assertSame(SolicitudReto::ESTADO_RESPONDIDA, $s->estado);
        $this->assertEquals([['ra_id' => $ra, 'modulo_id' => $modulo, 'ce_ids' => [$ceB]]], $s->respuestas['bloques'][0]['seleccionRaCe']);
        $this->assertNull($s->token_hash);
        $this->assertNull($s->privacidad_version);

        // Un criterio que no existe se rechaza sin escribir nada
        file_put_contents($archivo, str_replace('"ce":["b"]', '"ce":["z"]', file_get_contents($archivo)));
        $this->artisan('solicitudes-reto:importar', $opciones + ['--commit' => true])->assertFailed();
        $this->assertSame(1, SolicitudReto::count());
        unlink($archivo);
    }

    public function test_borrado_seguro_a_la_papelera_y_restaurar(): void
    {
        $token = $this->crearSolicitud();
        $solicitud = SolicitudReto::firstOrFail();

        // Red DuaLab gestiona la bandeja, pero no borra
        Sanctum::actingAs($this->usuario(User::ROLE_RED_DUALAB, 'atencion@test.local'));
        $this->deleteJson("/api/central/solicitudes-reto/{$solicitud->id}")->assertForbidden();

        Sanctum::actingAs($this->superadmin);
        $this->deleteJson("/api/central/solicitudes-reto/{$solicitud->id}")->assertOk();
        $this->assertSoftDeleted($solicitud);
        $this->getJson('/api/central/solicitudes-reto')->assertOk()->assertJsonCount(0, 'data');
        // El enlace del docente deja de valer
        $this->getJson("/api/public/solicitud-reto/{$token}")->assertNotFound();

        $papelera = $this->getJson('/api/papelera')->assertOk()->json('items.solicitudes');
        $this->assertSame('Solicitud de Marta · IES Prueba Solicitudes', $papelera[0]['nombre']);
        $this->assertArrayNotHasKey('token_hash', $papelera[0]['datos']);

        $this->patchJson("/api/papelera/solicitudes/{$solicitud->id}/restaurar")->assertOk();
        $this->getJson("/api/public/solicitud-reto/{$token}")->assertOk();
    }

    public function test_varias_peticiones_cada_una_al_generador(): void
    {
        $token = $this->crearSolicitud();
        $otroCiclo = DB::table('ciclos_formativos')->insertGetId(['nombre' => 'Administración y Finanzas (prueba)', 'siglasGrado' => 'GS', 'familia_id' => $this->familiaId]);
        $this->postJson("/api/public/solicitud-reto/{$token}/enviar", $this->respuestas(['bloques' => [
            $this->bloque(),
            $this->bloque(['cicloId' => $otroCiclo, 'curso' => '2', 'moduloIds' => [], 'seleccionRaCe' => [], 'tipoPropuesta' => 'Una campaña para captar clientes jóvenes']),
        ]]))->assertOk();
        $solicitud = SolicitudReto::firstOrFail();

        Sanctum::actingAs($this->superadmin);
        $lista = $this->getJson('/api/central/solicitudes-reto/para-generador')->assertOk()->json('data');
        $this->assertCount(2, $lista[0]['bloques']);
        $this->assertSame('Administración y Finanzas (prueba)', $lista[0]['bloques'][1]['ciclo']);

        $this->getJson("/api/central/solicitudes-reto/{$solicitud->id}/generador?bloque=1")->assertOk()
            ->assertJsonPath('data.cicloId', $otroCiclo)
            ->assertJsonPath('data.cursoSeleccionado', '2')
            ->assertJsonPath('data.total_bloques', 2)
            ->assertJsonPath('data.enfoqueReto', fn ($t) => str_starts_with($t, 'Una campaña para captar clientes jóvenes.'));
        $this->getJson("/api/central/solicitudes-reto/{$solicitud->id}/generador?bloque=4")->assertNotFound();

        // Red DuaLab no prepara el generador
        Sanctum::actingAs($this->usuario(User::ROLE_RED_DUALAB, 'atencion@test.local'));
        $this->getJson('/api/central/solicitudes-reto/para-generador')->assertForbidden();
    }

    public function test_las_solicitudes_anteriores_a_los_bloques_se_siguen_leyendo(): void
    {
        $solicitud = new SolicitudReto(['nombre_docente' => 'Marta', 'email_docente' => 'profe@centro.test', 'centro_nombre' => 'IES']);
        $solicitud->respuestas = [
            'familiaId' => $this->familiaId, 'cicloId' => $this->cicloId, 'curso' => '1', 'moduloIds' => [$this->moduloId],
            'seleccionRaCe' => [], 'objetivo' => 'Practicar el registro', 'nivelGrupo' => 'Bajo',
        ];
        $solicitud->forceFill(['estado' => SolicitudReto::ESTADO_RESPONDIDA])->save();

        Sanctum::actingAs($this->superadmin);
        $this->getJson("/api/central/solicitudes-reto/{$solicitud->id}/generador")->assertOk()
            ->assertJsonPath('data.cicloId', $this->cicloId)
            ->assertJsonPath('data.enfoqueReto', fn ($t) => str_starts_with($t, 'Practicar el registro.'));
        $this->getJson("/api/central/solicitudes-reto/{$solicitud->id}")->assertOk()
            ->assertJsonPath('data.bloques.0.ciclo', 'Gestión Administrativa (prueba)');
    }

    public function test_cada_peticion_con_su_empresa_y_el_generador_abre_con_centro_y_familia(): void
    {
        $token = $this->crearSolicitud();
        DB::table('familias')->insertOrIgnore(['nombre' => 'Informática y Comunicaciones']);
        $informatica = (int) DB::table('familias')->where('nombre', 'Informática y Comunicaciones')->value('id');
        $cicloInf = DB::table('ciclos_formativos')->insertGetId(['nombre' => 'Sistemas Microinformáticos y Redes (prueba)', 'siglasGrado' => 'GM', 'familia_id' => $informatica]);
        $this->postJson("/api/public/solicitud-reto/{$token}/enviar", $this->respuestas(['bloques' => [
            $this->bloque(),
            $this->bloque(['familiaId' => $informatica, 'cicloId' => $cicloInf, 'moduloIds' => [], 'seleccionRaCe' => [], 'tipoPropuesta' => 'Montar la red de una oficina']),
        ]]))->assertOk();
        $solicitud = SolicitudReto::firstOrFail();

        // Centro de la beta y una empresa de Informática allí; la de Administración es de otro centro
        $beta = DB::table('centro_educativo')->insertGetId(['nombre' => 'DuaLab']);
        $empInf = DB::table('empresas')->insertGetId(['nombre_comercial' => 'Redes Atlántico', 'centro_id' => $beta, 'centro_educativo' => 'DuaLab']);
        DB::table('empresa_familia')->insert([['empresa_id' => $empInf, 'familia_id' => $informatica], ['empresa_id' => $this->empresaId, 'familia_id' => $this->familiaId]]);

        Sanctum::actingAs($this->superadmin);
        // Para la petición de Informática solo se ofrecen empresas de esa familia
        $this->getJson("/api/central/solicitudes-reto/{$solicitud->id}/empresas?bloque=1")->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.nombre', 'Redes Atlántico')->assertJsonPath('data.0.centro', 'DuaLab')
            ->assertJsonPath('data.0.familias', ['Informática y Comunicaciones']);
        // Con todas=1 (buscador «Todas las familias») también salen las de otras familias
        $this->getJson("/api/central/solicitudes-reto/{$solicitud->id}/empresas?bloque=1&todas=1")->assertOk()
            ->assertJsonCount(2, 'data');

        // Sin empresa: el generador se abre con el centro de la beta y la familia de la petición
        $this->getJson("/api/central/solicitudes-reto/{$solicitud->id}/generador?bloque=1")->assertOk()
            ->assertJsonPath('data.empresa_id', null)
            ->assertJsonPath('data.centro_generador', 'DuaLab')
            ->assertJsonPath('data.familia', 'Informática y Comunicaciones');

        // Cada petición con su empresa (y su centro)
        $this->patchJson("/api/central/solicitudes-reto/{$solicitud->id}", ['bloque' => 1, 'empresa_id' => $empInf])->assertOk()
            ->assertJsonPath('data.bloques.1.empresa.nombre', 'Redes Atlántico')
            ->assertJsonPath('data.bloques.0.empresa', null);
        $this->patchJson("/api/central/solicitudes-reto/{$solicitud->id}", ['bloque' => 0, 'empresa_id' => $this->empresaId])->assertOk();
        $this->getJson("/api/central/solicitudes-reto/{$solicitud->id}/generador?bloque=0")->assertJsonPath('data.empresa_id', $this->empresaId)
            ->assertJsonPath('data.centro_generador', 'IES Prueba Solicitudes');
        $this->getJson("/api/central/solicitudes-reto/{$solicitud->id}/generador?bloque=1")->assertJsonPath('data.empresa_id', $empInf);

        // Quitar la empresa de una petición
        $this->patchJson("/api/central/solicitudes-reto/{$solicitud->id}", ['bloque' => 1, 'empresa_id' => null])->assertOk()
            ->assertJsonPath('data.bloques.1.empresa', null);
    }

    public function test_el_generador_pasa_a_la_ia_las_tareas_y_el_entregable_del_docente(): void
    {
        config(['services.openai.key' => 'clave-de-prueba']);
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => '{"microretos":[]}']]]])]);
        Sanctum::actingAs($this->superadmin);

        $this->postJson('/api/generar-microreto', [
            'empresa_id' => $this->empresaId, 'empresaNombre' => 'Gestoría Prueba', 'empresaSector' => 'Servicios',
            'friccionProblema' => 'Se pierden cartas y paquetes.', 'ciclo_nombre' => 'Gestión Administrativa (prueba)',
            'ciclo_id' => $this->cicloId, 'nivelGrupo' => 'Bajo', 'cursoSeleccionado' => '1', 'cantidad' => 1,
            'familia' => 'Administración y Gestión',
            'enfoqueReto' => 'Un reto guiado de correspondencia',
            'tareasPracticas' => 'Registrar la entrada y salida de paquetería',
            'entregableDocente' => 'Un libro de registro de una mañana',
        ]);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'chat/completions')
            && str_contains(json_encode($r->data(), JSON_UNESCAPED_UNICODE), 'TAREAS QUE HARÁ EL ALUMNADO EN LA EMPRESA DURANTE LAS PRÁCTICAS (lo indica el docente): \\"Registrar la entrada y salida de paquetería')
            && str_contains(json_encode($r->data(), JSON_UNESCAPED_UNICODE), 'ENTREGABLE QUE ESPERA EL DOCENTE: \\"Un libro de registro de una mañana'));
    }

    public function test_admin_central_genera_guarda_y_publica_en_la_biblioteca_con_historial(): void
    {
        $solicitud = $this->solicitudRespondida();
        DB::table('empresas')->where('id', $this->empresaId)->update(['friccion_problema' => 'Se pierden cartas.', 'sector' => 'Servicios']);
        $adminCentral = $this->usuario(User::ROLE_ADMIN_CENTRAL_DUALAB, 'admincentral@test.local');

        // Red DuaLab no gestiona las peticiones
        Sanctum::actingAs($this->usuario(User::ROLE_RED_DUALAB, 'red@test.local'));
        $this->getJson("/api/central/solicitudes-reto/{$solicitud->id}/generacion")->assertForbidden();

        Sanctum::actingAs($adminCentral);
        // Sin empresa elegida: no se puede generar todavía
        $this->getJson("/api/central/solicitudes-reto/{$solicitud->id}/generacion")->assertOk()->assertJsonPath('data.faltan', ['empresa']);
        $this->patchJson("/api/central/solicitudes-reto/{$solicitud->id}", ['bloque' => 0, 'empresa_id' => $this->empresaId])->assertOk();
        $datos = $this->getJson("/api/central/solicitudes-reto/{$solicitud->id}/generacion")->assertOk()
            ->assertJsonPath('data.faltan', [])
            ->assertJsonPath('data.peticion.friccionProblema', 'Se pierden cartas.')
            ->assertJsonPath('data.peticion.modulo_id', [$this->moduloId])
            ->assertJsonPath('data.editables.tareasPracticas', 'Registrar correspondencia y paquetería.')
            ->json('data');

        // Genera con el mismo motor (OpenAI simulado) y guarda en la recámara
        config(['services.openai.key' => 'clave-de-prueba']);
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => '{"microretos":[]}']]]])]);
        $this->postJson('/api/generar-microreto', $datos['peticion'] + $datos['editables'])->assertOk();
        $this->postJson('/api/guardar-microretos-lote', ['solicitud_reto_id' => $solicitud->id, 'microretos' => [
            $datos['contexto'] + ['titulo' => 'Reto de la recámara', 'nivel_grupo' => 'Bajo'],
        ]])->assertCreated();
        // Desde Central solo se guardan retos de solicitudes
        $this->postJson('/api/guardar-microretos-lote', ['microretos' => [['titulo' => 'Otro']]])->assertForbidden();

        $reto = Microreto::firstOrFail();
        $this->assertTrue($reto->en_recamara);
        // El contexto de la petición llega entero al reto guardado
        $this->assertSame($solicitud->id, $reto->solicitud_reto_id);
        $this->assertSame($this->empresaId, $reto->empresa_id);
        $this->assertSame('Bajo', $reto->nivel_grupo);
        $this->assertSame($datos['contexto']['ciclo_id'], $reto->ciclo_id);
        $this->assertSame($datos['contexto']['modulo'], $reto->modulo);

        // En la recámara, el docente del centro no lo ve ni lo puede ajustar
        $docente = $this->usuario(User::ROLE_DOCENTE, 'docente@test.local', $this->centroId);
        Sanctum::actingAs($docente);
        $this->assertNotContains($reto->uuid, collect($this->getJson('/api/microretos')->assertOk()->json())->pluck('uuid'));
        $this->postJson('/api/startup/proyectos', ['titulo' => 'Antes de tiempo', 'microreto_id' => $reto->id])
            ->assertUnprocessable()->assertJsonValidationErrors('microreto_id');

        // Publicar en la biblioteca de un centro: sus docentes lo ven
        Sanctum::actingAs($adminCentral);
        $this->postJson("/api/central/solicitudes-reto/{$solicitud->id}/publicar", ['retos' => [$reto->uuid], 'centro_id' => $this->centroId])->assertOk();
        $this->assertFalse($reto->fresh()->en_recamara);
        Sanctum::actingAs($docente);
        $this->getJson("/api/microretos/{$reto->uuid}")->assertOk();
        $this->assertContains($reto->uuid, collect($this->getJson('/api/microretos')->assertOk()->json())->pluck('uuid'));
        // …y puede trabajarlo: «Trabajar este reto» crea su reto ajustado
        $this->postJson('/api/startup/proyectos', ['titulo' => 'Reto de la recámara', 'microreto_id' => $reto->id])->assertCreated();

        // Todo queda en el historial, con quién lo hizo; lo crítico, marcado
        Sanctum::actingAs($adminCentral);
        $historial = collect($this->getJson('/api/central/historial')->assertOk()->json('data'));
        $this->assertTrue($historial->contains(fn ($h) => $h['accion'] === 'publicar_retos' && $h['critica'] && $h['quien'] === 'Admincentral'));
        $this->assertTrue($historial->contains(fn ($h) => $h['accion'] === 'empresa_peticion'));
        $this->assertTrue($historial->contains(fn ($h) => $h['accion'] === 'guardar_retos'));
        Sanctum::actingAs($this->usuario(User::ROLE_RED_DUALAB, 'red2@test.local'));
        $this->getJson('/api/central/historial')->assertForbidden();
    }

    public function test_autorizar_a_trabajar_solo_si_el_docente_tiene_cuenta(): void
    {
        $solicitud = $this->solicitudRespondida();
        $reto = Microreto::forceCreate(['titulo' => 'Reto listo', 'solicitud_reto_id' => $solicitud->id, 'en_recamara' => true]);
        $adminCentral = $this->usuario(User::ROLE_ADMIN_CENTRAL_DUALAB, 'admincentral@test.local');
        $url = "/api/central/solicitudes-reto/{$solicitud->id}";

        // Red DuaLab avisa, pero no autoriza
        Sanctum::actingAs($this->usuario(User::ROLE_RED_DUALAB, 'red@test.local'));
        $this->postJson("{$url}/autorizar", ['retos' => [$reto->uuid]])->assertForbidden();

        // Sin cuenta con el correo del docente: solo puede leerlos
        Sanctum::actingAs($adminCentral);
        $this->getJson($url)->assertOk()->assertJsonPath('data.cuenta_docente', null);
        $this->postJson("{$url}/autorizar", ['retos' => [$reto->uuid]])->assertUnprocessable()->assertJsonValidationErrors('retos');
        $this->assertTrue($reto->fresh()->en_recamara);
        Mail::assertNotQueued(SolicitudRetoAutorizadosMail::class);

        // Con cuenta (el correo, sin importar mayúsculas): pasa a la biblioteca de su centro y le llega el correo
        $docente = $this->usuario(User::ROLE_DOCENTE, 'Profe@Centro.test', $this->centroId);
        $this->getJson($url)->assertOk()->assertJsonPath('data.cuenta_docente.centro', 'IES Prueba Solicitudes');
        $this->postJson("{$url}/autorizar", ['retos' => [$reto->uuid], 'mensaje' => '¡A por ello!'])->assertOk()
            ->assertJsonPath('data.retos.0.autorizado', true);
        $this->assertFalse($reto->fresh()->en_recamara);
        $this->assertSame($this->centroId, $reto->fresh()->centro_id);
        Mail::assertQueued(SolicitudRetoAutorizadosMail::class, fn ($m) => $m->hasTo('profe@centro.test')
            && str_ends_with($m->retos[0]['enlace'], "/retos/{$reto->uuid}") && $m->mensaje === '¡A por ello!');

        // Ya lo puede trabajar
        Sanctum::actingAs($docente);
        $this->postJson('/api/startup/proyectos', ['titulo' => 'Reto listo', 'microreto_id' => $reto->id])->assertCreated();

        // Uno publicado en otro centro no se mueve
        $otroCentro = DB::table('centro_educativo')->insertGetId(['nombre' => 'IES Otro']);
        $ajeno = Microreto::forceCreate(['titulo' => 'De otro centro', 'solicitud_reto_id' => $solicitud->id, 'en_recamara' => false, 'centro_id' => $otroCentro]);
        Sanctum::actingAs($adminCentral);
        $this->postJson("{$url}/autorizar", ['retos' => [$ajeno->uuid]])->assertUnprocessable();
        $this->assertSame($otroCentro, $ajeno->fresh()->centro_id);
    }

    // ── Ayudantes ────────────────────────────────────────────────────────────

    private function usuario(int $rol, string $email, ?int $centroId = null): User
    {
        return User::forceCreate([
            'name' => ucfirst(strtok($email, '@')), 'email' => $email, 'password' => bcrypt('x'),
            'role' => $rol, 'email_verified_at' => now(), 'centro_educativo_id' => $centroId,
        ]);
    }

    /** @return array<string, mixed> */
    private function datosNueva(): array
    {
        return ['nombre_docente' => 'Marta', 'email_docente' => 'profe@centro.test', 'centro_nombre' => 'IES Prueba Solicitudes'];
    }

    /** Crea la solicitud como superadmin y devuelve el token del enlace */
    private function crearSolicitud(): string
    {
        Sanctum::actingAs($this->superadmin);
        $enlace = $this->postJson('/api/central/solicitudes-reto', $this->datosNueva())->assertCreated()->json('enlace');

        return substr($enlace, -64);
    }

    private function solicitudRespondida(): SolicitudReto
    {
        $token = $this->crearSolicitud();
        $this->postJson("/api/public/solicitud-reto/{$token}/enviar", $this->respuestas())->assertOk();

        return SolicitudReto::firstOrFail();
    }

    /** @return array<string, mixed> */
    private function privacidad(): array
    {
        return ['aceptaPrivacidad' => true, 'privacidadVersion' => config('solicitudes_reto.privacidad.version')];
    }

    /** Lo que contó la profesora del ejemplo: Comunicación, 1.º de grado medio, UT3 */
    private function respuestas(array $cambios = [], array $cambiosBloque = []): array
    {
        return array_merge($this->privacidad(), [
            'nombreDocente' => 'Marta', 'centroNombre' => 'IES Prueba Solicitudes',
            'bloques' => [$this->bloque($cambiosBloque)],
            'nivelGrupo' => 'Bajo', 'puntoTemario' => 'primera_vez', 'unidadTemario' => 'UT3',
            'sesionFecha' => '2099-11-25', 'sesionHoraInicio' => '12:10', 'sesionHoraFin' => '14:00',
            'tareasEmpresa' => 'Registrar correspondencia y paquetería.',
        ], $cambios);
    }

    /** @return array<string, mixed> */
    private function bloque(array $cambios = []): array
    {
        return array_merge([
            'familiaId' => $this->familiaId, 'cicloId' => $this->cicloId, 'curso' => '1',
            'moduloIds' => [$this->moduloId],
            'seleccionRaCe' => [['ra_id' => $this->raId, 'ce_ids' => [$this->ceId]]],
            'tipoPropuesta' => 'Que practiquen el registro y reparto de correspondencia.',
        ], $cambios);
    }

    /** @return array<string, mixed> */
    private function lote(int $solicitudId, int $cuantos = 1): array
    {
        return [
            'solicitud_reto_id' => $solicitudId,
            'microretos' => array_map(fn ($n) => [
                'empresa_id' => $this->empresaId, 'titulo' => "Reto {$n}", 'ciclo_id' => $this->cicloId,
            ], range(1, $cuantos)),
        ];
    }
}
