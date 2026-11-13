<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * La app DuaLab, la app comercial y DuaLab Central usan el mismo backend pero cada una
 * tiene su cookie de sesión y su XSRF-TOKEN (SesionPorHerramienta): entrar con otra
 * cuenta en una herramienta no cambia la sesión de las demás. Sin base de datos: la
 * sesión de los tests es 'array' (phpunit.xml).
 */
class SesionPorHerramientaTest extends TestCase
{
    private function nombresDeCookies(array $cabeceras): array
    {
        $respuesta = $this->withHeaders($cabeceras)->get('/sanctum/csrf-cookie');
        $respuesta->assertNoContent();

        return array_map(fn ($cookie) => $cookie->getName(), $respuesta->headers->getCookies());
    }

    public function test_central_usa_sus_propias_cookies(): void
    {
        $base = config('session.cookie');

        $nombres = $this->nombresDeCookies(['Referer' => 'http://localhost:5179/usuarios']);

        $this->assertContains('XSRF-TOKEN-central', $nombres);
        $this->assertContains($base.'-central', $nombres);
        $this->assertNotContains('XSRF-TOKEN', $nombres);
    }

    public function test_la_app_comercial_usa_sus_propias_cookies(): void
    {
        $base = config('session.cookie');

        $nombres = $this->nombresDeCookies(['Origin' => 'https://comercial.dualab.es']);

        $this->assertContains('XSRF-TOKEN-comercial', $nombres);
        $this->assertContains($base.'-comercial', $nombres);
    }

    public function test_la_app_dualab_sigue_con_las_cookies_de_siempre(): void
    {
        $base = config('session.cookie');

        $nombres = $this->nombresDeCookies(['Referer' => 'https://dualab.es/login']);

        $this->assertContains('XSRF-TOKEN', $nombres);
        $this->assertContains($base, $nombres);
    }

    public function test_un_subdominio_parecido_no_cuenta_como_central(): void
    {
        $nombres = $this->nombresDeCookies(['Origin' => 'https://central.dualab.es.otro.com']);

        $this->assertContains('XSRF-TOKEN', $nombres);
        $this->assertNotContains('XSRF-TOKEN-central', $nombres);
    }
}
