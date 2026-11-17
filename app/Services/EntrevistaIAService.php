<?php

namespace App\Services;

use App\Models\EmpresaCaptacion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * IA de la entrevista de la app comercial (OpenAI):
 *  - transcribir(): dictado de pago. El audio se pasa a texto y se descarta (no se guarda).
 *  - sugerencias(): ideas para el comercial por si la empresa se queda en blanco: preguntas
 *    para tirar del hilo, situaciones típicas de su sector y, en las preguntas con opciones,
 *    las más probables. Son ayuda, no respuestas: el comercial solo las inserta si la empresa
 *    le confirma que es así (y queda anotado en empresa_captaciones.respuestas_ia).
 * Las dos se cachean por el hash de lo que se envía: un reintento no vuelve a pagar la llamada.
 */
class EntrevistaIAService
{
    // Textos de las preguntas y límite de cada respuesta: copia de la fuente del frontend
    // (frontend-microretos/src/config/preguntasDiagnostico.json, que no se sube al servidor).
    // PreguntasDiagnosticoSincronizadasTest falla si no coinciden: cambiar las dos a la vez.
    public const PREGUNTAS = [
        'diaANormal'         => ['¿Qué ofrece su empresa y qué hace en su día a día?', 1000],
        'friccionArea'       => ['¿Qué tarea da más trabajo del que debería?', 400],
        'friccionProblema'   => ['¿Por qué? Cuéntanos qué ocurre hoy', 1200],
        'consecuencias'      => ['Si pudieran mejorar algo YA mismo, ¿qué sería?', 2000],
        'restricciones'      => ['¿Han probado a solucionarlo? ¿Qué limitaciones tienen?', 600],
        'loQueNoQuieren'     => ['¿Qué NO quieren bajo ningún concepto?', 500],
        'expectativasAlumno' => ['Si tuvieras a un alumno aquí, ¿qué esperas que realice?', 800],
    ];

    // Preguntas con opciones fijas (chips): la IA solo puede proponer de estas listas
    private const OPCIONES = [
        'consecuencias' => EmpresaFicticiaIAService::CONSECUENCIAS,
        'restricciones' => EmpresaFicticiaIAService::LIMITACIONES,
    ];

    private const MAX_IDEAS = 3;
    private const MAX_EJEMPLO = 300;
    private const MAX_PREGUNTA_APOYO = 200;

    // Formatos que acepta la API de transcripción (por la extensión del nombre del archivo)
    private const EXTENSIONES_AUDIO = ['webm', 'ogg', 'oga', 'mp4', 'm4a', 'mp3', 'mpga', 'mpeg', 'wav'];

    /** Texto del audio, o null si la IA no responde */
    public function transcribir(UploadedFile $audio): ?string
    {
        $contenido = (string) $audio->get();
        $cacheKey  = 'entrevista_transcripcion_' . hash('sha256', $contenido);
        if (($cacheado = Cache::get($cacheKey)) !== null) return $cacheado;

        $extension = strtolower((string) $audio->guessExtension());
        $nombre    = 'audio.' . (in_array($extension, self::EXTENSIONES_AUDIO, true) ? $extension : 'webm');

        $response = Http::withToken(config('services.openai.key'))
            ->timeout(60)
            ->attach('file', $contenido, $nombre)
            ->post('https://api.openai.com/v1/audio/transcriptions', [
                'model'           => config('captacion.ia.modelo_transcripcion'),
                'language'        => 'es',
                'response_format' => 'json',
            ]);

        if (!$response->successful()) {
            // Sin el texto: puede ser lo que contó la empresa
            Log::error('Fallo de OpenAI al transcribir el dictado de la entrevista.', ['status' => $response->status()]);
            return null;
        }

        $texto = trim(strip_tags((string) $response->json('text')));
        // Poco tiempo: solo para que un reintento no se cobre dos veces
        Cache::put($cacheKey, $texto, now()->addMinutes(10));

        return $texto;
    }

    /**
     * Ideas para las siete preguntas de una vez, con los datos de la empresa y lo que ya ha
     * contado ($respuestas, SugerirRespuestasComercialRequest).
     *
     * @param array<string, mixed> $respuestas
     * @return array<string, array{preguntas: list<string>, ejemplos: list<string>, opciones?: list<string>}>|null
     */
    public function sugerencias(EmpresaCaptacion $captacion, array $respuestas): ?array
    {
        $contexto = $this->contexto($captacion, $respuestas);
        $cacheKey = 'entrevista_sugerencias_' . md5(json_encode($contexto, JSON_UNESCAPED_UNICODE));

        if (is_array($cacheado = Cache::get($cacheKey))) return $cacheado;

        $data = $this->pedirJson($this->promptSistema(), json_encode($contexto, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        if ($data === null) return null;

        $sugerencias = $this->limpiar($data);
        Cache::put($cacheKey, $sugerencias, now()->addMinutes(60));

        return $sugerencias;
    }

    /**
     * Lo que se envía a la IA: datos de la empresa (sin nombre, CIF ni contacto) y respuestas.
     *
     * @param array<string, mixed> $respuestas
     * @return array<string, mixed>
     */
    private function contexto(EmpresaCaptacion $captacion, array $respuestas): array
    {
        $yaContado = [];
        foreach (self::PREGUNTAS as $campo => [$texto]) {
            $valor = trim((string) ($respuestas[$campo] ?? ''));
            if ($valor !== '') $yaContado[$texto] = $valor;
        }

        return [
            'empresa' => array_filter([
                'sector'               => $captacion->sector,
                'actividad'            => $captacion->actividad,
                'tamano'               => $captacion->tamano,
                'nivel_digitalizacion' => $captacion->nivel_digitalizacion,
                'ubicacion'            => collect([$captacion->municipio, $captacion->provincia])->filter()->implode(', ') ?: null,
                'alumnado_que_le_interesa' => collect($captacion->familias ?? [])
                    ->map(fn ($f) => trim(($f['nombre'] ?? '') . (filled($f['interes'] ?? null) ? ': ' . $f['interes'] : '')))
                    ->filter()->values()->all() ?: null,
            ], fn ($v) => filled($v)),
            'lo_que_ya_ha_contado' => $yaContado ?: null,
            'preguntas' => collect(self::PREGUNTAS)
                ->map(fn ($p, $campo) => array_filter([
                    'pregunta' => $p[0],
                    'opciones_permitidas' => self::OPCIONES[$campo] ?? null,
                ]))->all(),
        ];
    }

    private function promptSistema(): string
    {
        return <<<'PROMPT'
Ayudas a un comercial de DuaLab durante una entrevista en persona con una empresa pequeña o mediana. DuaLab conecta empresas con alumnado de Formación Profesional: con las respuestas se diseñan retos reales para el alumnado.

Recibes los datos de la empresa, lo que ya ha contado y las siete preguntas de la entrevista (con su clave). Para CADA pregunta, por si la persona de la empresa se queda en blanco, devuelve:
- "preguntas": 2 o 3 preguntas cortas y concretas que el comercial puede hacer para tirar del hilo (por ejemplo: "¿Os pasa que los pedidos por WhatsApp se pierden?"). Tono cercano y respetuoso.
- "ejemplos": 2 o 3 respuestas posibles, típicas de una empresa de su sector, tamaño y nivel de digitalización, redactadas como las diría la propia empresa (primera persona del plural, 1 o 2 frases, máximo 250 caracteres). Coherentes con lo que ya ha contado y sin repetirlo.
- Solo si la pregunta trae "opciones_permitidas": "opciones" con las 2 o 3 más probables, copiadas literalmente de esa lista.

Reglas:
- Son ideas para que la empresa confirme o corrija, no hechos: no inventes cifras, nombres de personas, clientes, marcas ni herramientas concretas que la empresa no haya mencionado.
- Nada de datos personales.
- Español de España.

Responde solo con un objeto JSON cuyas claves son las claves de las preguntas, por ejemplo:
{"diaANormal": {"preguntas": ["..."], "ejemplos": ["..."]}, "consecuencias": {"preguntas": ["..."], "ejemplos": ["..."], "opciones": ["..."]}}
PROMPT;
    }

    /** @return array<string, mixed>|null */
    private function pedirJson(string $systemPrompt, string $userPrompt): ?array
    {
        $response = Http::withToken(config('services.openai.key'))
            ->timeout(45)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'           => config('captacion.ia.modelo_sugerencias'),
                'messages'        => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userPrompt],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature'     => 0.7,
                'max_tokens'      => 3000,
            ]);

        if (!$response->successful()) {
            Log::error('Fallo de OpenAI al sugerir respuestas de la entrevista.', ['status' => $response->status()]);
            return null;
        }

        $data = json_decode((string) $response->json('choices.0.message.content'), true);
        return is_array($data) ? $data : null;
    }

    /**
     * Solo las preguntas conocidas, textos planos y recortados, y opciones de la lista.
     *
     * @param array<string, mixed> $data
     * @return array<string, array{preguntas: list<string>, ejemplos: list<string>, opciones?: list<string>}>
     */
    private function limpiar(array $data): array
    {
        $textos = fn (mixed $lista, int $max) => collect(is_array($lista) ? $lista : [])
            ->filter(fn ($t) => is_string($t))
            ->map(fn ($t) => mb_substr(trim(strip_tags($t)), 0, $max))
            ->filter()->unique()->take(self::MAX_IDEAS)->values()->all();

        $limpio = [];
        foreach (self::PREGUNTAS as $campo => [, $maxRespuesta]) {
            $ideas = is_array($data[$campo] ?? null) ? $data[$campo] : [];
            $limpio[$campo] = [
                'preguntas' => $textos($ideas['preguntas'] ?? [], self::MAX_PREGUNTA_APOYO),
                'ejemplos'  => $textos($ideas['ejemplos'] ?? [], min(self::MAX_EJEMPLO, $maxRespuesta)),
            ];
            if (isset(self::OPCIONES[$campo])) {
                $limpio[$campo]['opciones'] = array_values(array_intersect(
                    self::OPCIONES[$campo],
                    is_array($ideas['opciones'] ?? null) ? $ideas['opciones'] : [],
                ));
            }
        }

        return $limpio;
    }
}
