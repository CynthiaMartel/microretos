<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Webhook de los formularios de la web (Gestión web → Mailing y webhook) → captaciones de origen 2. */
class FormularioWebWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRETO = 'secreto-del-webhook-de-prueba-123';

    protected function setUp(): void
    {
        parent::setUp();
        config(['captacion.frontoffice_webhook_secreto' => self::SECRETO]);
    }

    private function firmado(array $cuerpo, string $secreto = self::SECRETO)
    {
        $json = json_encode($cuerpo, JSON_UNESCAPED_UNICODE);

        return $this->call('POST', '/api/integraciones/frontoffice/formularios', [], [], [], $this->transformHeadersToServerVars([
            'Content-Type' => 'application/json', 'Accept' => 'application/json',
            'X-DuaLab-Firma' => hash_hmac('sha256', $json, $secreto),
        ]), $json);
    }

    public function test_probar_conexion_se_confirma_sin_crear_captaciones(): void
    {
        $prueba = ['prueba' => true, 'evento' => 'formulario.recibido', 'id' => null, 'formulario' => 'empresa',
            'contacto' => ['nombre' => 'Prueba', 'apellidos' => 'DuaLab', 'email' => 'prueba@ejemplo.com'], 'datos' => []];

        $this->firmado($prueba)->assertOk()->assertJson(['recibido' => true, 'prueba' => true]);
        $this->assertDatabaseCount('empresa_captaciones', 0);
        $this->assertDatabaseCount('centro_captaciones', 0);

        // La prueba también comprueba la clave: con otra, se rechaza
        $this->firmado($prueba, 'otra-clave-que-no-es-la-buena')->assertStatus(401);
    }
}
