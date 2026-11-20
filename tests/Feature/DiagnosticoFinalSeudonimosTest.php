<?php

namespace Tests\Feature;

use App\Models\Equipo;
use App\Models\EquipoFase;
use App\Models\EquipoMiembro;
use App\Models\EquipoReflexion;
use App\Services\DiagnosticoFinalService;
use Tests\TestCase;

/**
 * El alias del alumnado lleva su nombre de pila real: al diagnóstico final de la IA (OpenAI)
 * no le llega ninguno, solo «Integrante N», y al guardar la respuesta vuelven los alias.
 * Sin BD: el equipo y sus relaciones se montan en memoria.
 */
class DiagnosticoFinalSeudonimosTest extends TestCase
{
    private function equipo(): Equipo
    {
        $equipo = new Equipo(['nombre' => 'Los del fondo']);
        $equipo->setRelation('encuentro', null);
        $equipo->setRelation('microproyecto', null);
        $equipo->setRelation('miembros', collect([
            new EquipoMiembro(['alias' => 'Daniel Lince Amarillo', 'rol' => 'portavoz']),
            new EquipoMiembro(['alias' => 'Lucía Nutria Azul']),
            new EquipoMiembro(['alias' => 'Lucía Panda Coral']),
        ]));

        $f0 = new EquipoFase(['numero_fase' => 0, 'datos' => ['miembros' => [['alias' => 'Daniel Lince Amarillo', 'rol' => 'portavoz']]]]);
        $f0->completada = true;
        $f1 = new EquipoFase(['numero_fase' => 1, 'datos' => ['hallazgos' => ['Daniel habló con Lucía sobre el reto']]]);
        $f1->completada = true;
        $equipo->setRelation('fases', collect([$f0, $f1]));

        $reflexion = new EquipoReflexion(['tipo' => 'final']);
        $reflexion->respuestas = [['pregunta' => '¿Qué aprendiste?', 'respuesta' => 'Que Daniel organiza bien']];
        $equipo->setRelation('reflexiones', collect([$reflexion]));

        return $equipo;
    }

    public function test_el_contexto_para_la_ia_no_lleva_alias_ni_nombres_de_pila(): void
    {
        $ctx = DiagnosticoFinalService::contexto($this->equipo());

        $this->assertStringContainsString('Participantes: Integrante 1 (portavoz), Integrante 2, Integrante 3', $ctx);
        $this->assertStringContainsString('Integrante 1 habló con un integrante sobre el reto', $ctx);
        $this->assertStringContainsString('Que Integrante 1 organiza bien', $ctx);
        foreach (['Daniel', 'Lucía', 'Lince', 'Nutria', 'Panda'] as $dato) {
            $this->assertStringNotContainsString($dato, $ctx);
        }
    }

    public function test_la_respuesta_de_la_ia_recupera_los_alias(): void
    {
        $diagnostico = DiagnosticoFinalService::restaurarAlias([
            'resumen'    => 'El integrante 1 lideró y apoyó al integrante 2.',
            'fortalezas' => ['Integrante 3 documentó', 'Integrante 9 no existe'],
        ], $this->equipo());

        $this->assertSame('Daniel Lince Amarillo lideró y apoyó a Lucía Nutria Azul.', $diagnostico['resumen']);
        $this->assertSame(['Lucía Panda Coral documentó', 'Integrante 9 no existe'], $diagnostico['fortalezas']);
    }
}
