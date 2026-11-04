<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Services\EmpresaFicticiaIAService;
use App\Support\BibliotecaRetos;
use Illuminate\Console\Command;

/**
 * Deduce con IA el diagnóstico de madurez de la biblioteca de DuaLab (área + 4 dimensiones
 * de 0 a 4, ver BibliotecaRetos) y lo guarda en empresas.diagnostico_madurez. Solo añade ese
 * dato: no cambia ningún otro campo de la empresa.
 *
 * Solo empresas ficticias con alguna familia de la biblioteca. Idempotente: las que ya tienen
 * diagnóstico se saltan. Sin --preview ni --commit: dry-run sin coste, solo lista el plan.
 */
class DiagnosticarMadurezEmpresas extends Command
{
    protected $signature = 'empresas:diagnosticar-madurez
                            {--centro= : Empresas de este centro (id).}
                            {--catalogo : Plantillas del catálogo DuaLab.}
                            {--limit=0 : Tope de empresas a procesar en esta ejecución (0 = todas las pendientes).}
                            {--preview : Llama a la IA y muestra el resultado, sin escribir en BD.}
                            {--commit : Llama a la IA y guarda el diagnóstico.}';

    protected $description = 'Deduce con IA el diagnóstico de madurez (biblioteca DuaLab) de empresas ficticias (dry-run por defecto).';

    public function __construct(private EmpresaFicticiaIAService $ia)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $centroId = (int) $this->option('centro');
        if (!$centroId && !$this->option('catalogo')) {
            $this->error('Indica --centro=<id> o --catalogo.');
            return self::FAILURE;
        }
        $escribir = (bool) $this->option('commit');

        $empresas = Empresa::with('familias')
            ->where('es_simulada', true)
            ->whereNull('diagnostico_madurez')
            ->whereHas('familias', fn ($q) => $q->whereIn('nombre', BibliotecaRetos::familias()))
            ->where(fn ($q) => $q->when($centroId, fn ($c) => $c->orWhere('centro_id', $centroId))
                ->when($this->option('catalogo'), fn ($c) => $c->orWhere('es_catalogo', true)))
            ->orderBy('id')
            ->when((int) $this->option('limit') > 0, fn ($q) => $q->take((int) $this->option('limit')))
            ->get();

        $this->info($empresas->count() . ' empresas sin diagnóstico de madurez.');
        if (!$escribir && !$this->option('preview')) {
            $this->table(['Id', 'Empresa', 'Área de fricción'], $empresas->map(fn ($e) => [$e->id, $e->nombre_comercial, mb_substr((string) $e->friccion_area, 0, 60)])->all());
            $this->warn('Dry-run: --preview para ver lo que deduce la IA sin guardar, --commit para guardar.');
            return self::SUCCESS;
        }

        $filas = [];
        $fallidas = [];
        foreach ($empresas as $empresa) {
            $diag = $this->ia->diagnosticarMadurez($empresa);
            if ($diag === null) {
                $fallidas[] = $empresa->id;
                continue;
            }
            if ($escribir) {
                // No fillable: solo lo fija este comando
                $empresa->diagnostico_madurez = $diag;
                $empresa->save();
            }
            $total = $diag['procedimiento'] + $diag['herramientas'] + $diag['medicion'] + $diag['responsables'];
            $filas[] = [$empresa->id, mb_substr($empresa->nombre_comercial, 0, 28), mb_substr((string) $empresa->friccion_area, 0, 40), $diag['area'],
                "{$diag['procedimiento']}/{$diag['herramientas']}/{$diag['medicion']}/{$diag['responsables']}", BibliotecaRetos::nivel($total)];
        }

        $this->table(['Id', 'Empresa', 'Área de fricción', 'Área biblioteca', 'Proc/Herr/Med/Resp', 'Nivel'], $filas);
        if ($fallidas) $this->warn(count($fallidas) . ' fallidas (relanza para reintentarlas): ' . implode(', ', $fallidas));
        $this->info($escribir ? count($filas) . ' diagnósticos guardados.' : 'Preview: no se ha escrito nada en BD.');

        return self::SUCCESS;
    }
}
