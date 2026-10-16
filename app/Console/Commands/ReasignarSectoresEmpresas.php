<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Support\SectoresEmpresa;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pasa los sectores en texto libre de las empresas a la lista cerrada de SectoresEmpresa.
 * Mapeo revisado con Cynthia (2026-10-16) sobre los 59 valores que había en local.
 *
 * Por valor de sector, salvo excepciones por nombre de empresa (el valor engañaba: p. ej.
 * floristerías y viveros guardados como "Industria"). Sin ids: en producción no coinciden.
 * No toca la actividad: las empresas con una actividad concreta como sector ya la tenían
 * rellena. Un valor sin propuesta se deja tal cual y se lista para revisarlo a mano.
 *
 * Dry-run por defecto: solo con --commit escribe.
 */
class ReasignarSectoresEmpresas extends Command
{
    protected $signature = 'empresas:reasignar-sectores
                            {--commit : Aplica los cambios. Sin esta opción es un dry-run.}';

    protected $description = 'Reasigna el sector de las empresas a la lista cerrada (dry-run por defecto).';

    // null = sin sector (no es un sector: forma jurídica o dato de prueba)
    private const POR_VALOR = [
        'Servicios Informáticos'                         => 'Tecnología e informática',
        'Tecnologías de la Información'                  => 'Tecnología e informática',
        'Tecnología de la Información'                   => 'Tecnología e informática',
        'Tecnología de la Información y Comunicaciones'  => 'Tecnología e informática',
        'Informática y Telecomunicaciones'               => 'Tecnología e informática',
        'Informática y Comunicaciones'                   => 'Tecnología e informática',
        'Desarrollo de software'                         => 'Tecnología e informática',
        'Provisión de servicios de telecomunicaciones'   => 'Tecnología e informática',
        'Consultoría Empresarial'                        => 'Consultoría y servicios a empresas',
        'Consultoría y Gestión Empresarial'              => 'Consultoría y servicios a empresas',
        'Consultoría en gestión empresarial'             => 'Consultoría y servicios a empresas',
        'Consultoría de Negocios'                        => 'Consultoría y servicios a empresas',
        'Servicios de Consultoría y Asesoría'            => 'Consultoría y servicios a empresas',
        'Servicios Empresariales'                        => 'Consultoría y servicios a empresas',
        'Recuersos Humanos'                              => 'Consultoría y servicios a empresas',
        'Recursos Humanos'                               => 'Consultoría y servicios a empresas',
        'Comercio y Marketing'                           => 'Marketing, publicidad y diseño',
        'Servicios de marketing digital'                 => 'Marketing, publicidad y diseño',
        'Servicios de impresión y diseño gráfico'        => 'Marketing, publicidad y diseño',
        'Comercio al por mayor'                          => 'Comercio y distribución',
        'Retail'                                         => 'Comercio y distribución',
        'Comercio electrónico'                           => 'Comercio y distribución',
        'Industria'                                      => 'Industria y fabricación',
        'Producción de enzimas industriales'             => 'Industria y fabricación',
        'Fabricación de productos químicos orgánicos'    => 'Industria y fabricación',
        'Fabricación de tejidos técnicos'                => 'Industria y fabricación',
        'Fabricación de cerámica decorativa'             => 'Industria y fabricación',
        'Fabricación de cerámica artesanal'              => 'Industria y fabricación',
        'Fabricación de componentes aeronáuticos'        => 'Industria y fabricación',
        'Fabricación de piezas y componentes metálicos'  => 'Industria y fabricación',
        'Fabricación de muebles de madera'               => 'Industria y fabricación',
        'Extracción de piedra caliza'                    => 'Industria y fabricación',
        'reparación, mantenimiento...'                   => 'Industria y fabricación',
        'Fabricación de productos lácteos'               => 'Alimentación y agroalimentario',
        'Explotación pesquera'                           => 'Agricultura, pesca y jardinería',
        'Producción y distribución de cultivos ecológicos' => 'Agricultura, pesca y jardinería',
        'Jardines'                                       => 'Agricultura, pesca y jardinería',
        'Const. y Act. Inmob.'                           => 'Construcción e inmobiliaria',
        'Construcción'                                   => 'Construcción e inmobiliaria',
        'Construcción de edificios residenciales'        => 'Construcción e inmobiliaria',
        'Instalaciones de energías renovables'           => 'Energía e instalaciones',
        'Instalaciones eléctricas y mantenimientos industriales' => 'Energía e instalaciones',
        'Servicios de mantenimiento industrial'          => 'Energía e instalaciones',
        'Talleres'                                       => 'Automoción',
        'Concesionarios'                                 => 'Automoción',
        'Taller de reparación de vehículos'              => 'Automoción',
        'Transportes'                                    => 'Transporte y logística',
        'Distribución y logística de mercancías'         => 'Transporte y logística',
        'Restauración y Catering'                        => 'Hostelería, restauración y turismo',
        'Clínica de Fisioterapia'                        => 'Salud, estética y bienestar',
        'Centro de estética y belleza'                   => 'Salud, estética y bienestar',
        'Asociaciones'                                   => 'Servicios sociales y tercer sector',
        'Gestión de programas de inclusión social'       => 'Servicios sociales y tercer sector',
        'Organización de eventos deportivos'             => 'Cultura, deporte y ocio',
        'Producción audiovisual'                         => 'Cultura, deporte y ocio',
        'Consultoría en gestión ambiental'               => 'Medio ambiente',
        'Comunidad de Bienes'                            => null,
        'asdasd'                                         => null,
    ];

    // Por nombre comercial (sin distinguir mayúsculas): ganan al mapeo por valor
    private const POR_EMPRESA = [
        'Verde que te quiero Verde'     => 'Agricultura, pesca y jardinería',
        'Agrilor'                       => 'Agricultura, pesca y jardinería',
        'Manuel Lorenzo Floristas'      => 'Agricultura, pesca y jardinería',
        'Floristería Elegancia'         => 'Agricultura, pesca y jardinería',
        'Viveros Godoy'                 => 'Agricultura, pesca y jardinería',
        'Jardin de la Marquesa'         => 'Agricultura, pesca y jardinería',
        'Paisajes Insulares'            => 'Agricultura, pesca y jardinería',
        'FCC'                           => 'Medio ambiente',
        'GARCÍA NARANJO AUTOMOCIÓN'     => 'Automoción',
        'AUTOS LA FRONTERA'             => 'Automoción',
        'Maderas Perestelo'             => 'Industria y fabricación',
        'Carpintería Metálica'          => 'Industria y fabricación',
        'Real Club de Golf de Las Palmas' => 'Cultura, deporte y ocio',
        'Lopacan Electrónica'           => 'Tecnología e informática',
    ];

    public function handle(): int
    {
        $porEmpresa = collect(self::POR_EMPRESA)->mapWithKeys(fn ($s, $n) => [mb_strtolower($n) => $s]);
        $cambios = [];
        $sinPropuesta = [];

        // Lote acotado: el listado completo de empresas ronda las 160 (sin paginar en el dashboard)
        Empresa::query()->orderBy('id')->chunkById(200, function ($empresas) use ($porEmpresa, &$cambios, &$sinPropuesta) {
            foreach ($empresas as $empresa) {
                $actual = trim((string) $empresa->sector);
                if (SectoresEmpresa::esValido($actual)) continue;

                $nombre = mb_strtolower(trim((string) $empresa->nombre_comercial));
                if ($porEmpresa->has($nombre)) {
                    $nuevo = $porEmpresa->get($nombre);
                } elseif (array_key_exists($actual, self::POR_VALOR)) {
                    $nuevo = self::POR_VALOR[$actual];
                } elseif ($actual === '' && trim((string) $empresa->actividad) === 'Industria') {
                    // Importación del metal: sector vacío y "Industria" en la actividad
                    $nuevo = 'Industria y fabricación';
                } else {
                    if ($actual !== '') $sinPropuesta[$actual][] = $empresa->nombre_comercial;
                    continue;
                }

                if ($nuevo === ($actual === '' ? null : $actual)) continue;
                $cambios[] = ['id' => $empresa->id, 'nombre' => $empresa->nombre_comercial, 'de' => $actual ?: '(vacío)', 'a' => $nuevo];
            }
        });

        $this->table(['Sector actual', 'Sector nuevo', 'Empresas'], collect($cambios)
            ->groupBy(fn ($c) => $c['de'] . '→' . ($c['a'] ?? '(sin sector)'))
            ->map(fn ($g) => [$g[0]['de'], $g[0]['a'] ?? '(sin sector)', $g->count()])
            ->sortBy(fn ($fila) => $fila[1])->values()->all());

        if ($sinPropuesta) {
            $this->warn('Sin propuesta (se dejan como están, revisar a mano):');
            foreach ($sinPropuesta as $valor => $nombres) {
                $this->line("  {$valor}: " . implode(', ', $nombres));
            }
        }

        $verbo = $this->option('commit') ? 'cambiadas' : 'se cambiarían';
        $this->info(count($cambios) . " empresas {$verbo}.");

        if (!$this->option('commit')) {
            $this->warn('Dry-run: relanza con --commit para aplicar los cambios.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($cambios) {
            foreach ($cambios as $c) {
                Empresa::whereKey($c['id'])->update(['sector' => $c['a']]);
            }
        });
        $this->info('Hecho.');

        return self::SUCCESS;
    }
}
