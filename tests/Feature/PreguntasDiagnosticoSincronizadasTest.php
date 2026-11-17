<?php

namespace Tests\Feature;

use App\Http\Requests\StoreEmpresaRequest;
use App\Http\Requests\UpdateDiagnosticoEmpresaRequest;
use App\Services\CaptacionEmpresaService;
use App\Services\EmpresaFicticiaIAService;
use App\Services\EntrevistaIAService;
use ReflectionClassConstant;
use Tests\TestCase;

/**
 * Las preguntas de la entrevista diagnóstica viven en el frontend
 * (frontend-microretos/src/config/preguntasDiagnostico.json: entrevista del comercial, formulario
 * por correo, Generador de Retos). El backend necesita su copia porque esa carpeta no se sube
 * al servidor. Este test falla si alguien cambia una pregunta, un límite o una opción en un lado
 * y no en el otro.
 */
class PreguntasDiagnosticoSincronizadasTest extends TestCase
{
    private array $json;

    protected function setUp(): void
    {
        parent::setUp();

        $ruta = base_path('frontend-microretos/src/config/preguntasDiagnostico.json');
        if (!is_file($ruta)) $this->markTestSkipped('Sin el frontend no hay con qué comparar.');
        $this->json = json_decode((string) file_get_contents($ruta), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_mismas_preguntas_en_el_mismo_orden(): void
    {
        $campos = array_column($this->json['preguntas'], 'campo');

        $this->assertSame(UpdateDiagnosticoEmpresaRequest::CAMPOS_DIAGNOSTICO, $campos);
        $this->assertSame(array_keys(EntrevistaIAService::PREGUNTAS), $campos);
    }

    public function test_mismos_textos_que_la_copia_del_backend(): void
    {
        foreach ($this->json['preguntas'] as $p) {
            $this->assertSame($p['pregunta'], EntrevistaIAService::PREGUNTAS[$p['campo']][0], "Texto de «{$p['campo']}»");
        }
    }

    public function test_mismos_limites_y_obligatorias_que_la_validacion(): void
    {
        $reglas      = (new StoreEmpresaRequest())->rules();
        $diagnostico = (new UpdateDiagnosticoEmpresaRequest())->rules();

        foreach ($this->json['preguntas'] as $p) {
            $this->assertMatchesRegularExpression("/\\bmax:{$p['max']}\\b/", $reglas[$p['campo']], "Límite de «{$p['campo']}» en StoreEmpresaRequest");
            $this->assertSame($p['max'], EntrevistaIAService::PREGUNTAS[$p['campo']][1], "Límite de «{$p['campo']}» en EntrevistaIAService");
            $this->assertSame($p['obligatorio'], str_starts_with($diagnostico[$p['campo']], 'required|'), "Obligatoria «{$p['campo']}»");
        }
    }

    public function test_mismas_columnas_que_el_servicio_de_captaciones(): void
    {
        $columnas = (new ReflectionClassConstant(CaptacionEmpresaService::class, 'CAMPOS_DIAGNOSTICO'))->getValue();

        $this->assertSame($columnas, array_column($this->json['preguntas'], 'columna', 'campo'));
    }

    public function test_mismas_opciones(): void
    {
        $this->assertSame(EmpresaFicticiaIAService::CONSECUENCIAS, $this->json['opciones']['consecuencias']);
        $this->assertSame(EmpresaFicticiaIAService::LIMITACIONES, $this->json['opciones']['restricciones']);
    }
}
