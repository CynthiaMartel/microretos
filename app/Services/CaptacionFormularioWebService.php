<?php

namespace App\Services;

use App\Models\CaptacionOrigen;
use App\Models\EmpresaCaptacion;
use App\Models\Familia;
use App\Models\User;
use App\Notifications\EmpresaCaptadaPendienteValidacion;
use App\Support\MunicipiosCanarias;
use App\Support\SectoresEmpresa;
use Illuminate\Support\Facades\Notification;

/**
 * Formularios de dualab.es/contacto (frontoffice) que llegan por su webhook: el de centros pasa
 * a centro_captaciones y el de empresas a empresa_captaciones, los dos con origen «formulario
 * web» y pendientes de validar. Los de administraciones y el formulario rápido se quedan solo en
 * la bandeja del frontoffice (no son una empresa ni un centro).
 * El id del envío en el frontoffice (origen_ref) evita duplicados si el webhook reintenta.
 */
class CaptacionFormularioWebService
{
    // Sectores del formulario web → lista de microretos (SectoresEmpresa). Lo que no encaja se
    // guarda en las notas tal cual lo eligió la empresa.
    private const SECTORES = [
        'Tecnología'              => 'Tecnología e informática',
        'Industria'               => 'Industria y fabricación',
        'Comercio'                => 'Comercio y distribución',
        'Hostelería y turismo'    => 'Hostelería, restauración y turismo',
        'Sanidad'                 => 'Salud, estética y bienestar',
        'Construcción'            => 'Construcción e inmobiliaria',
        'Transporte y logística'  => 'Transporte y logística',
        'Agroalimentario'         => 'Alimentación y agroalimentario',
        'Energía'                 => 'Energía e instalaciones',
        'Servicios profesionales' => 'Consultoría y servicios a empresas',
    ];

    // Mismos valores que src/config/tamanosEmpresa.js
    private const TAMANOS = [
        'micro'   => 'Micropyme (1-10)',
        'pequena' => 'Pequeña (10-50)',
        'mediana' => 'Mediana (50-250)',
        'grande'  => 'Grande (+250)',
    ];

    private const COLABORA = ['si' => 'Sí', 'no' => 'No', 'valorando' => 'Lo está valorando'];

    private const INTERESES = [
        'plantear_retos'      => 'plantear retos',
        'talento_practicas'   => 'talento y prácticas',
        'colaborar_centros'   => 'colaborar con centros',
        'casos_exito'         => 'casos de éxito',
        'info_funcionamiento' => 'saber cómo funciona',
        'otro'                => 'otro',
    ];

    private const ACCIONES = ['registro' => 'Registrarse', 'info' => 'Más información', 'demo' => 'Solicitar demo'];

    public function __construct(private CaptacionCentroService $centros) {}

    /**
     * @param array<string, mixed> $v  RecibirFormularioWebRequest::validated()
     * @return array{tipo: string, id: int|null}
     */
    public function procesar(array $v): array
    {
        $ref = (string) $v['id'];
        return match ($v['formulario']) {
            'centro'  => ['tipo' => 'centro', 'id' => $this->centro($ref, $v)],
            'empresa' => ['tipo' => 'empresa', 'id' => $this->empresa($ref, $v)],
            default   => ['tipo' => 'ignorado', 'id' => null],
        };
    }

    /** @param array<string, mixed> $v */
    private function centro(string $ref, array $v): ?int
    {
        $d = $v['datos'];
        if (empty($d['centro_nombre'])) return null;

        $captacion = $this->centros->crearDesdeFormulario($ref, [
            'accion'             => $v['accion'] ?? null,
            'centro_nombre'      => $d['centro_nombre'],
            'direccion'          => $d['direccion'] ?? null,
            'provincia'          => in_array($d['provincia'] ?? null, MunicipiosCanarias::PROVINCIAS, true) ? $d['provincia'] : null,
            'municipio'          => $d['municipio'] ?? null,
            // Solo familias que existen en DuaLab (el formulario web usa las mismas 26)
            'familias'           => Familia::whereIn('nombre', (array) ($d['familias'] ?? []))->pluck('nombre')->values()->all(),
            'contacto_nombre'    => $v['contacto']['nombre'],
            'contacto_apellidos' => $v['contacto']['apellidos'] ?? null,
            'contacto_cargo'     => $v['contacto']['cargo'] ?? null,
            'contacto_email'     => $v['contacto']['email'],
            'contacto_telefono'  => $v['contacto']['telefono'] ?? null,
            'comentarios'        => $d['comentarios'] ?? null,
        ]);
        return $captacion->id;
    }

    /** @param array<string, mixed> $v */
    private function empresa(string $ref, array $v): ?int
    {
        $d = $v['datos'];
        if (empty($d['empresa_nombre'])) return null;

        $existente = EmpresaCaptacion::where('origen_id', CaptacionOrigen::FORMULARIO)->where('origen_ref', $ref)->first();
        if ($existente) return $existente->id; // reintento del webhook

        $sectorWeb = $d['sector'] ?? null;
        $sector    = self::SECTORES[$sectorWeb] ?? (in_array($sectorWeb, SectoresEmpresa::LISTA, true) ? $sectorWeb : null);
        $contacto  = $v['contacto'];
        $persona   = trim(($contacto['nombre'] ?? '') . ' ' . ($contacto['apellidos'] ?? ''));

        $notas = array_filter([
            isset($v['accion']) ? 'Pide: ' . (self::ACCIONES[$v['accion']] ?? $v['accion']) . '.' : null,
            $sectorWeb && !$sector ? "Sector indicado en la web: {$sectorWeb}." : null,
            isset($d['colabora']) ? '¿Colabora ya con centros educativos? ' . (self::COLABORA[$d['colabora']] ?? $d['colabora']) . '.' : null,
            !empty($d['intereses']) ? 'Le interesa: ' . implode(', ', array_map(fn ($i) => self::INTERESES[$i] ?? $i, $d['intereses'])) . '.' : null,
            !empty($d['mensaje']) ? 'Mensaje: ' . $d['mensaje'] : null,
        ]);

        $captacion = new EmpresaCaptacion([
            'nombre_comercial' => $d['empresa_nombre'],
            'cif'              => $d['cif'] ?? null,
            'sector'           => $sector,
            'tamano'           => self::TAMANOS[$d['tamano'] ?? ''] ?? null,
            'persona_contacto' => trim($persona . (!empty($contacto['cargo']) ? " ({$contacto['cargo']})" : '')),
            'telefono'         => $contacto['telefono'] ?? null,
            'email_general'    => $contacto['email'],
            'direccion'        => $d['direccion'] ?? null,
            'municipio'        => $d['municipio'] ?? null,
            'provincia'        => $d['provincia'] ?? null,
            'familias'         => [],
            'notas'            => $notas ? implode("\n", $notas) : null,
        ]);
        $captacion->forceFill([
            'origen_id'  => CaptacionOrigen::FORMULARIO,
            'origen_ref' => $ref,
            'estado'     => EmpresaCaptacion::ESTADO_PENDIENTE_VALIDACION,
        ])->save();

        Notification::send(
            User::where('role', User::ROLE_SUPERADMIN)->where('is_blocked', false)->get(),
            new EmpresaCaptadaPendienteValidacion($captacion, null)
        );
        return $captacion->id;
    }
}
