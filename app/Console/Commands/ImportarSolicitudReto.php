<?php

namespace App\Console\Commands;

use App\Http\Requests\EnviarSolicitudRetoRequest;
use App\Models\CicloFormativo;
use App\Models\CriterioEvaluacion;
use App\Models\Familia;
use App\Models\Modulo;
use App\Models\ResultadoAprendizaje;
use App\Models\SolicitudReto;
use App\Models\User;
use App\Services\SolicitudRetoService;
use Illuminate\Console\Command;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Carga una solicitud de retos que el docente ha mandado por correo o por mensaje, sin pasar por el
 * formulario: queda «respondida» en Central, lista para abrir en el generador. El archivo JSON
 * (p. ej. database/solicitudes-reto/comunicacion-ut3.json) lleva uno o varios bloques —cada uno con
 * el currículo por nombres (familia, ciclo, módulo, número de RA y letras de los CE) y el tipo de
 * propuesta— y lo común que cuenta el docente; los datos personales van
 * como opciones, nunca en el archivo (el repo es público). Pasa las mismas validaciones que el
 * formulario (EnviarSolicitudRetoRequest).
 *
 * Dry-run por defecto; --commit guarda.
 */
class ImportarSolicitudReto extends Command
{
    protected $signature = 'solicitudes-reto:importar
                            {archivo : JSON con la solicitud (ver database/solicitudes-reto)}
                            {--nombre= : Nombre del docente}
                            {--email= : Email del docente}
                            {--centro= : Centro educativo del docente}
                            {--creada-por= : Email del usuario de DuaLab que figura como autor (opcional)}
                            {--commit : Guarda la solicitud. Sin esta opción es un dry-run.}';

    protected $description = 'Crea una solicitud de retos ya respondida a partir de un JSON (información recibida por correo).';

    public function handle(SolicitudRetoService $solicitudes): int
    {
        try {
            $datos = $this->leer((string) $this->argument('archivo'));
            $docente = $this->docente();
            $payload = $this->payload($datos, $docente);
            $validado = $this->validar($payload);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (ValidationException $e) {
            foreach ($e->errors() as $campo => $mensajes) {
                $this->error("{$campo}: {$mensajes[0]}");
            }

            return self::FAILURE;
        }

        $autor = $this->option('creada-por') ? User::where('email', $this->option('creada-por'))->first() : null;
        if ($this->option('creada-por') && !$autor) {
            $this->error('No hay ningún usuario con el email de --creada-por.');

            return self::FAILURE;
        }

        $solicitud = new SolicitudReto([
            'nombre_docente'     => $docente['nombre'],
            'email_docente'      => $docente['email'],
            'centro_nombre'      => $docente['centro'],
            'respuestas'         => array_intersect_key($validado, array_flip(SolicitudRetoService::CAMPOS_RESPUESTA)),
            'sesion_fecha'       => $validado['sesionFecha'] ?? null,
            'sesion_hora_inicio' => $validado['sesionHoraInicio'] ?? null,
            'sesion_hora_fin'    => $validado['sesionHoraFin'] ?? null,
            'notas'              => 'Importada con solicitudes-reto:importar: la información llegó por correo, no por el formulario.',
        ]);

        $this->resumen($solicitud, $solicitudes);
        if (SolicitudReto::where('email_docente', $docente['email'])->where('estado', '!=', SolicitudReto::ESTADO_DESCARTADA)->exists()) {
            $this->warn('Ese docente ya tiene una solicitud abierta en Central: revisa que no sea la misma.');
        }

        if (!$this->option('commit')) {
            $this->warn('Dry-run: no se ha escrito nada. Ejecuta con --commit para guardarla.');

            return self::SUCCESS;
        }

        // Sin enlace ni privacidad aceptada en el formulario: los datos llegaron por correo
        $solicitud->forceFill([
            'estado'             => SolicitudReto::ESTADO_RESPONDIDA,
            'respondido_at'      => now(),
            'creada_por_user_id' => $autor?->id,
        ])->save();
        $this->info("Hecho: solicitud {$solicitud->referencia()} creada. Está en Central → Primer contacto → Solicitudes de retos.");

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function leer(string $ruta): array
    {
        if (!is_file($ruta)) {
            $ruta = base_path($ruta);
        }
        $datos = is_file($ruta) ? json_decode((string) file_get_contents($ruta), true) : null;
        if (!is_array($datos)) {
            throw new RuntimeException('No se puede leer el archivo o no es un JSON válido.');
        }

        return $datos;
    }

    /** @return array{nombre: string, email: string, centro: string} */
    private function docente(): array
    {
        $docente = ['nombre' => trim((string) $this->option('nombre')), 'email' => trim((string) $this->option('email')), 'centro' => trim((string) $this->option('centro'))];
        $v = Validator::make($docente, [
            'nombre' => 'required|string|max:150',
            'email'  => 'required|email:rfc|max:255',
            'centro' => 'required|string|max:255',
        ], [
            'nombre.required' => 'Falta --nombre.', 'email.required' => 'Falta --email.', 'centro.required' => 'Falta --centro.',
            // Mensajes propios: en el servidor no hay traducciones de validación (saldría «validation.email»)
            'email.email' => '--email no es un correo válido: pon el correo real del docente (p. ej. --email="nombre@centro.es").',
            'nombre.max'  => '--nombre es demasiado largo (máximo 150 caracteres).',
            'email.max'   => '--email es demasiado largo (máximo 255 caracteres).',
            'centro.max'  => '--centro es demasiado largo (máximo 255 caracteres).',
        ]);
        if ($v->fails()) {
            throw new RuntimeException(implode(' ', $v->errors()->all()));
        }

        return $docente;
    }

    /**
     * Del JSON (nombres) a lo que manda el formulario (ids).
     *
     * @param array<string, mixed> $d
     * @param array{nombre: string, email: string, centro: string} $docente
     * @return array<string, mixed>
     */
    private function payload(array $d, array $docente): array
    {
        $bloques = collect($d['bloques'] ?? [])->values()->map(fn ($b, $i) => $this->bloque((array) $b, $i + 1));
        if ($bloques->isEmpty()) {
            throw new RuntimeException('El archivo no trae ningún bloque («bloques»).');
        }

        return [
            'nombreDocente' => $docente['nombre'], 'centroNombre' => $docente['centro'],
            'bloques' => $bloques->all(),
            'nivelGrupo' => $d['nivel'] ?? null, 'puntoTemario' => $d['punto_temario'] ?? null,
            'unidadTemario' => $d['unidad'] ?? null,
            'sesionFecha' => $d['sesion']['fecha'] ?? null, 'sesionHoraInicio' => $d['sesion']['hora_inicio'] ?? null,
            'sesionHoraFin' => $d['sesion']['hora_fin'] ?? null,
            'tareasEmpresa' => $d['tareas_empresa'] ?? null,
            'entregable' => $d['entregable'] ?? null, 'observaciones' => $d['observaciones'] ?? null,
            // Requisito del formulario; aquí no hay aceptación (se guarda sin versión de privacidad)
            'aceptaPrivacidad' => true, 'privacidadVersion' => config('solicitudes_reto.privacidad.version'),
        ];
    }

    /**
     * Un bloque del JSON (nombres) a lo que manda el formulario (ids).
     *
     * @param array<string, mixed> $b
     * @return array<string, mixed>
     */
    private function bloque(array $b, int $n): array
    {
        $familia = Familia::where('nombre', $b['familia'] ?? '')->first()
            ?? throw new RuntimeException("Bloque {$n}: no existe la familia «" . ($b['familia'] ?? '') . "».");
        $ciclo = CicloFormativo::where('familia_id', $familia->id)->where('nombre', $b['ciclo'] ?? '')->first()
            ?? throw new RuntimeException("Bloque {$n}: no existe el ciclo «" . ($b['ciclo'] ?? '') . "» en {$familia->nombre}.");

        // Los nombres de módulo del BOE pueden llevar el código pegado: basta con el principio
        $modulo = fn (string $nombre) => Modulo::where('idcicloformativo', $ciclo->id)->where('nombre', 'like', addcslashes($nombre, '%_\\') . '%')->first()
            ?? throw new RuntimeException("Bloque {$n}: no existe el módulo «{$nombre}» en {$ciclo->nombre}.");
        $modulos = collect($b['modulos'] ?? [])->mapWithKeys(fn ($nombre) => [$nombre => $modulo($nombre)]);

        $seleccion = collect($b['ra_ce'] ?? [])->map(function ($item) use ($modulos, $modulo, $n) {
            $m = $modulos[$item['modulo']] ?? $modulo($item['modulo']);
            // En el catálogo, cada RA empieza por su número («3. Transmite…») y cada CE por su letra («b) …»)
            $ra = ResultadoAprendizaje::where('idmodulo', $m->id)->where('ra', 'like', ((int) $item['ra']) . '. %')->first()
                ?? throw new RuntimeException("Bloque {$n}: el módulo {$m->nombre} no tiene RA{$item['ra']}.");
            $ces = collect($item['ce'] ?? [])->map(fn ($letra) => CriterioEvaluacion::where('idresultadoaprendizaje', $ra->id)
                ->where('ce', 'like', strtolower($letra) . ') %')->value('id')
                ?? throw new RuntimeException("Bloque {$n}: el RA{$item['ra']} no tiene el criterio {$letra})."));

            return ['ra_id' => $ra->id, 'modulo_id' => $m->id, 'ce_ids' => $ces->all()];
        });

        return [
            'familiaId' => $familia->id, 'cicloId' => $ciclo->id, 'curso' => (string) ($b['curso'] ?? ''),
            'moduloIds' => $modulos->pluck('id')->values()->all(),
            'seleccionRaCe' => $seleccion->values()->all(),
            'tipoPropuesta' => $b['tipo_propuesta'] ?? null,
        ];
    }

    /**
     * Mismas reglas y comprobaciones del currículo que el formulario.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function validar(array $payload): array
    {
        $request = EnviarSolicitudRetoRequest::create('/', 'POST', $payload);
        $request->setContainer(app())->setRedirector(app(Redirector::class));
        $request->validateResolved();

        return $request->validated();
    }

    private function resumen(SolicitudReto $s, SolicitudRetoService $solicitudes): void
    {
        $sesion = $solicitudes->sesion($s);
        $this->table(['Campo', 'Valor'], [
            ['Docente', "{$s->nombre_docente} · {$s->email_docente}"],
            ['Centro', $s->centro_nombre],
            ['Nivel', $s->respuestas['nivelGrupo'] ?? 'que lo decida DuaLab'],
            ['Sesión', $sesion['fecha'] ? "{$sesion['fecha']} {$sesion['hora_inicio']}–{$sesion['hora_fin']} ({$sesion['duracion']})" : '—'],
        ]);
        foreach ($solicitudes->curriculo($s) as $b) {
            $this->line('<info>Bloque ' . ($b['indice'] + 1) . ":</info> {$b['familia']} · {$b['ciclo']} · curso {$b['curso']}");
            $this->line('  Módulos: ' . ($b['modulos']->pluck('nombre')->join(' · ') ?: 'los elige DuaLab'));
            foreach ($b['ra_ce'] as $r) $this->line('  ' . mb_strimwidth((string) $r['ra'], 0, 70, '…') . ' → ' . count($r['ce']) . ' CE');
            $this->line('  <comment>Enfoque para el generador:</comment> ' . $solicitudes->borradorEnfoque($s, $b['indice']));
        }
    }
}
