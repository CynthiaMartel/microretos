<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Las sugerencias de IA del Taller de Ideas validan con FormRequest todo lo que va al
 * prompt (lista blanca + longitud máxima) y no llegan a llamar a la IA si algo no cumple.
 */
class SugerenciasIaValidacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(); // ninguna llamada real a la IA
        Sanctum::actingAs(User::forceCreate([
            'name' => 'Docente', 'email' => 'docente@test.local', 'password' => bcrypt('x'),
            'role' => User::ROLE_DOCENTE, 'email_verified_at' => now(),
        ]));
    }

    public function test_sugerir_ra_ce_rechaza_campos_sin_limite_antes_de_llamar_a_la_ia(): void
    {
        // Antes titulo/descripcion/contexto llegaban al prompt sin validar
        $this->postJson('/api/startup/sugerir-ra-ce', ['modulo_ids' => [999999], 'titulo' => str_repeat('x', 5000)])
            ->assertUnprocessable()->assertJsonValidationErrors(['titulo', 'modulo_ids.0']);
        Http::assertNothingSent();
    }

    public function test_sugerir_kpis_rechaza_objetivos_demasiado_largos(): void
    {
        $this->postJson('/api/startup/sugerir-kpis', ['objetivos' => [str_repeat('x', 301)]])
            ->assertUnprocessable()->assertJsonValidationErrors(['objetivos.0']);
        Http::assertNothingSent();
    }

    public function test_sugerir_objetivos_fundamentacion_y_metodologia_validan_su_texto(): void
    {
        $this->postJson('/api/startup/sugerir-objetivos', ['ra_ce' => str_repeat('x', 30001)])->assertUnprocessable();
        $this->postJson('/api/startup/sugerir-fundamentacion', ['contexto' => str_repeat('x', 2001)])->assertUnprocessable();
        $this->postJson('/api/startup/sugerir-metodologia', ['empresa' => str_repeat('x', 501)])->assertUnprocessable();
        Http::assertNothingSent();
    }
}
