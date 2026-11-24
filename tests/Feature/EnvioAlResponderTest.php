<?php

namespace Tests\Feature;

use App\Jobs\EnviarCorreoInfoJob;
use App\Support\EnvioAlResponder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** Correo por info@ sin esperar al cron de la cola; si la web falla, a la cola con reintentos. */
class EnvioAlResponderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Queue::fake();
        config(['frontoffice.api_url' => 'https://web.test/api-backend/public', 'frontoffice.central_secreto' => str_repeat('s', 40)]);
    }

    private function job(): EnviarCorreoInfoJob
    {
        return new EnviarCorreoInfoJob('profe@centro.test', 'Asunto', 'Texto', '<p>Texto</p>', 'Super');
    }

    public function test_si_la_web_lo_acepta_sale_ya_y_no_pasa_por_la_cola(): void
    {
        Http::fake(['web.test/*' => Http::response(['id' => 1, 'estado' => 'pendiente'], 202)]);

        EnvioAlResponder::ejecutar($this->job());

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/correo/enviar') && $r['para'] === ['profe@centro.test']);
        Queue::assertNothingPushed();
    }

    public function test_si_la_web_no_responde_va_a_la_cola(): void
    {
        Http::fake(['web.test/*' => Http::response([], 503)]);

        EnvioAlResponder::ejecutar($this->job());

        Queue::assertPushed(EnviarCorreoInfoJob::class, fn ($j) => $j->para === 'profe@centro.test');
    }

    public function test_si_la_web_lo_rechaza_no_se_reintenta(): void
    {
        Http::fake(['web.test/*' => Http::response(['errors' => ['para' => ['No válida']]], 422)]);

        EnvioAlResponder::ejecutar($this->job());

        Queue::assertNothingPushed();
    }
}
