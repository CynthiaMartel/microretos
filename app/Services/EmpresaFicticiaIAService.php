<?php

namespace App\Services;

use App\Models\CentroEducativo;
use App\Models\Empresa;
use App\Models\Familia;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Empresas ficticias generadas con IA: datos de la empresa y diagnóstico (P1–P5).
 * Los dos prompts los usan los endpoints sueltos de MicroretoIAController (modal de
 * empresa y "Información simulada") y la creación en un clic del generador (crearCompleta).
 */
class EmpresaFicticiaIAService
{
    public const TAMANOS = ['Micropyme (1-10)', 'Pequeña (10-50)', 'Mediana (50-250)', 'Grande (+250)'];
    public const LIMITACIONES = ['Presupuesto Cero/Muy Bajo', 'Equipos obsoletos', 'Internet inestable', 'Software cerrado', 'Resistencia al cambio', 'Espacio reducido', 'Falta de tiempo', 'Normativa RGPD'];
    public const CONSECUENCIAS = ['Errores frecuentes', 'Costes innecesarios', 'Pérdida de tiempo', 'Insatisfacción del cliente', 'Riesgos de seguridad', 'Desperdicio de materiales', 'Falta de comunicación interna'];

    // Diagnóstico que el usuario puede retocar antes de guardar la propuesta.
    public const COLUMNAS_DIAGNOSTICO = [
        'dia_a_normal', 'friccion_area', 'friccion_problema', 'consecuencias',
        'restricciones', 'lo_que_no_quieren', 'expectativas_alumno',
    ];

    // Mismos límites que StoreEmpresaRequest: lo que devuelve la IA nunca se guarda sin recortar.
    private const MAX_COLUMNA = [
        'nombre_comercial' => 255, 'razon_social' => 255, 'cif' => 20, 'sector' => 255, 'tamano' => 50,
        'web' => 255, 'actividad' => 500, 'persona_contacto' => 255, 'telefono' => 20, 'email_general' => 255,
        'direccion' => 255, 'municipio' => 255, 'provincia' => 255, 'codigo_postal' => 10,
        'dia_a_normal' => 1000, 'friccion_area' => 400, 'friccion_problema' => 1200, 'consecuencias' => 2000,
        'restricciones' => 600, 'lo_que_no_quieren' => 500, 'expectativas_alumno' => 800,
    ];

    /**
     * Datos de una empresa ficticia (nombre, CIF, contacto, dirección...) coherente con la familia.
     *
     * @return array<string, mixed>|null  null si la IA falla
     */
    public function generarDatosEmpresa(?CentroEducativo $centro, Familia $familia): ?array
    {
        // Sin centro = plantilla del catálogo DuaLab, compartida por todos los centros (T2).
        $colaboraCon = $centro ? "el centro educativo '{$centro->nombre}'" : 'centros educativos de Formación Profesional';
        $tamanosStr = implode('", "', self::TAMANOS);

        $systemPrompt = "Eres un generador de datos ficticios y realistas para uso educativo. Inventas empresas creíbles del sector correspondiente a una familia profesional de Formación Profesional, ubicadas en España. Nunca reutilizas nombres, CIF, direcciones, teléfonos o webs de empresas reales que puedan existir — todo el contenido es inventado desde cero.";

        $userPrompt = "Genera los datos de una empresa ficticia que colabora con {$colaboraCon} en la familia profesional '{$familia->nombre}'.

Responde ÚNICAMENTE con este JSON exacto, sin texto adicional ni comentarios:
{
  \"nombre_comercial\": \"...\",
  \"razon_social\": \"...\",
  \"cif\": \"...\",
  \"sector\": \"...\",
  \"tamano\": \"...\",
  \"web\": \"...\",
  \"actividad\": \"...\",
  \"persona_contacto\": \"...\",
  \"telefono\": \"...\",
  \"email_general\": \"...\",
  \"direccion\": \"...\",
  \"municipio\": \"...\",
  \"provincia\": \"...\",
  \"codigo_postal\": \"...\"
}

Reglas:
- El sector y la actividad deben encajar de forma realista con la familia profesional '{$familia->nombre}'.
- El sector es la actividad económica CONCRETA de la empresa (p. ej. \"Desarrollo de software a medida\", \"Taller de reparación de vehículos\"), NUNCA el nombre de la familia profesional.
- El CIF debe tener formato español válido (una letra + 8 dígitos) pero completamente inventado.
- El teléfono debe tener formato español de 9 dígitos (fijo o móvil), sin prefijo internacional.
- La web y el email_general deben usar SIEMPRE el dominio reservado \".example\" (p. ej. https://www.nombre.example y contacto@nombre.example), nunca .com/.es ni dominios que puedan existir.
- La dirección, municipio, provincia y código postal deben ser coherentes entre sí dentro de España.
- tamano debe ser EXACTAMENTE uno de estos valores: \"{$tamanosStr}\".
- Todo el contenido en español.";

        $datos = $this->pedirJson($systemPrompt, $userPrompt, 0.9, 'generar empresa ficticia');
        return $datos === null ? null : $this->forzarDominioReservado($datos);
    }

    /**
     * Web y email siempre en el dominio reservado .example (RFC 2606), aunque la IA no lo
     * respete: una empresa ficticia nunca debe apuntar a un dominio que pueda ser real.
     *
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    private function forzarDominioReservado(array $datos): array
    {
        $slug = \Illuminate\Support\Str::slug($this->texto($datos['nombre_comercial'] ?? null), '') ?: 'empresa';
        $local = $this->texto($datos['email_general'] ?? null);
        $local = str_contains($local, '@') ? strstr($local, '@', true) : 'contacto';
        $local = preg_replace('/[^a-z0-9._-]/i', '', (string) $local) ?: 'contacto';

        $datos['email_general'] = "{$local}@{$slug}.example";
        $datos['web']           = "https://www.{$slug}.example";
        return $datos;
    }

    /**
     * Diagnóstico simulado (P1–P5) de una empresa, como si respondiera un empleado.
     *
     * @return array<string, mixed>|null  null si la IA falla
     */
    public function simularDiagnostico(string $nombre, string $sector, ?string $tamano = null, ?string $ubicacion = null): ?array
    {
        $contextoEmpresa = "EMPRESA: {$nombre} (Sector: {$sector})";
        if ($tamano)    $contextoEmpresa .= ", Tamaño: {$tamano}";
        if ($ubicacion) $contextoEmpresa .= ", Ubicación: {$ubicacion}";

        $limitacionesStr  = implode('", "', self::LIMITACIONES);
        $consecuenciasStr = implode('", "', self::CONSECUENCIAS);

        $systemPrompt = "Eres un responsable o empleado de la empresa '{$nombre}' del sector '{$sector}'. Conoces perfectamente la operativa diaria, los problemas internos, las limitaciones reales y los objetivos de mejora de tu empresa. Describes situaciones concretas, creíbles y propias del sector, sin inventar soluciones.";

        $userPrompt = "Rellena el siguiente formulario de diagnóstico empresarial como si fueras un representante de {$contextoEmpresa}.

FORMULARIO (responde en español, con detalle y realismo):
- P1 (diaANormal): Describe brevemente el día a día de tu empresa y cómo funciona vuestro proceso o servicio principal (máx. 900 caracteres).
- P2 (friccionArea): Nombra el área o proceso concreto que más trabajo extra genera actualmente (máx. 380 caracteres).
- P2b (friccionProblema): Explica con detalle qué ocurre hoy en ese proceso y por qué genera problemas (máx. 1100 caracteres).
- P3 (restricciones): De esta lista, devuelve SOLO los textos que aplican realmente a tu empresa: [\"{$limitacionesStr}\"]. Devuelve exactamente los textos tal como aparecen. Puede ser un array vacío.
- P3b (otraLimitacion): Si tenéis alguna limitación adicional no incluida en la lista, descríbela brevemente (máx. 500 caracteres, puede estar vacío).
- P3b2 (loQueNoQuieren): Describe qué tipo de soluciones no queréis bajo ningún concepto (máx. 450 caracteres).
- P4 (consecuencias): De esta lista, devuelve SOLO los textos que describen consecuencias reales del problema en tu empresa: [\"{$consecuenciasStr}\"]. Devuelve exactamente los textos tal como aparecen. Puede ser un array vacío.
- P4b (otraConsecuencia): Si hay alguna consecuencia adicional no incluida en la lista, descríbela (máx. 280 caracteres, puede estar vacío).
- P5 (expectativasAlumno): ¿Qué esperáis que investigue o proponga el alumno de FP para ayudaros? (máx. 750 caracteres).

Responde ÚNICAMENTE con este JSON exacto, sin texto adicional:
{
  \"diaANormal\": \"...\",
  \"friccionArea\": \"...\",
  \"friccionProblema\": \"...\",
  \"restricciones\": [],
  \"otraLimitacion\": \"\",
  \"loQueNoQuieren\": \"...\",
  \"consecuencias\": [],
  \"otraConsecuencia\": \"\",
  \"expectativasAlumno\": \"...\"
}";

        return $this->pedirJson($systemPrompt, $userPrompt, 0.8, 'simular diagnóstico');
    }

    // Propuestas pendientes de confirmar: se guardan en el servidor, nunca vuelven del cliente.
    private const PREFIJO_PROPUESTA = 'empresa-ficticia-propuesta:';
    // El borrador se revisa a lo largo de los pasos 1 y 2 del generador: margen holgado.
    public const MINUTOS_PROPUESTA  = 120;

    /**
     * Genera con IA (2 llamadas) una empresa ficticia completa SIN guardarla en BD: la deja
     * en caché ligada al usuario y devuelve el token para confirmarla con guardarPropuesta().
     * Así lo que se guarda es exactamente lo que generó la IA, no lo que mande el cliente.
     *
     * @return array{token: string, columnas: array<string, string|null>}|null  null si la IA falla
     */
    public function generarPropuesta(?CentroEducativo $centro, Familia $familia, int $userId): ?array
    {
        $columnas = $this->generarColumnas($centro, $familia);
        if ($columnas === null) return null;

        $token = (string) \Illuminate\Support\Str::uuid();
        Cache::put(self::PREFIJO_PROPUESTA . $token, [
            'user_id'    => $userId,
            'centro_id'  => $centro?->id,
            'familia_id' => $familia->id,
            'columnas'   => $columnas,
            'caduca_en'  => now()->addMinutes(self::MINUTOS_PROPUESTA)->toIso8601String(),
        ], now()->addMinutes(self::MINUTOS_PROPUESTA));

        return ['token' => $token, 'columnas' => $columnas];
    }

    /**
     * Lee una propuesta pendiente (solo su autor), p. ej. para recuperarla tras recargar.
     *
     * @return array{columnas: array<string, string|null>, centro: ?CentroEducativo, familia: Familia, caduca_en: ?string}|null
     */
    public function leerPropuesta(string $token, int $userId): ?array
    {
        $propuesta = Cache::get(self::PREFIJO_PROPUESTA . $token);
        if (!is_array($propuesta) || ($propuesta['user_id'] ?? null) !== $userId) return null;

        $familia = Familia::find((int) $propuesta['familia_id']);
        $centro  = $propuesta['centro_id'] ? CentroEducativo::find((int) $propuesta['centro_id']) : null;
        if (!$familia) return null;

        return ['columnas' => $propuesta['columnas'], 'centro' => $centro, 'familia' => $familia, 'caduca_en' => $propuesta['caduca_en'] ?? null];
    }

    /**
     * Guarda la propuesta del token (solo su autor). null si no existe, caducó o es de otro.
     * Se consume: un mismo token no puede crear dos empresas.
     */
    /**
     * @param array<string, string|null> $diagnostico  P1–P5 retocados por el usuario antes de
     *        guardar (columnas ya validadas por GuardarEmpresaFicticiaIARequest); sustituyen a
     *        los de la IA. Los datos de ficha siempre son los generados.
     */
    public function guardarPropuesta(string $token, int $userId, array $diagnostico = []): ?Empresa
    {
        $clave = self::PREFIJO_PROPUESTA . $token;
        $propuesta = Cache::get($clave);
        // Primero se comprueba el autor: un intento ajeno no debe consumir la propuesta.
        if (!is_array($propuesta) || ($propuesta['user_id'] ?? null) !== $userId) return null;
        Cache::forget($clave);

        $centro  = $propuesta['centro_id'] ? CentroEducativo::find((int) $propuesta['centro_id']) : null;
        $familia = Familia::find((int) $propuesta['familia_id']);
        if (!$familia || ($propuesta['centro_id'] && !$centro)) return null;

        $columnas = $propuesta['columnas'];
        foreach (array_intersect_key($diagnostico, array_flip(self::COLUMNAS_DIAGNOSTICO)) as $columna => $valor) {
            $columnas[$columna] = $valor === null ? null : mb_substr(trim(strip_tags((string) $valor)), 0, self::MAX_COLUMNA[$columna]);
        }
        // El nombre se comprobó al generar; se revisa otra vez por si entretanto se creó otra igual.
        $columnas['nombre_comercial'] = Empresa::nombreLibreEnCentro($centro?->id, (string) $columnas['nombre_comercial']);

        return $this->guardar($columnas, $centro, $familia);
    }

    /** Descarta una propuesta sin guardarla (solo su autor). */
    public function descartarPropuesta(string $token, int $userId): void
    {
        $propuesta = Cache::get(self::PREFIJO_PROPUESTA . $token);
        if (is_array($propuesta) && ($propuesta['user_id'] ?? null) === $userId) {
            Cache::forget(self::PREFIJO_PROPUESTA . $token);
        }
    }

    /**
     * Genera y guarda directamente, sin confirmación (para comandos/lotes de superadmin).
     * Del centro indicado, o del catálogo DuaLab si $centro es null (T2).
     */
    public function crearCompleta(?CentroEducativo $centro, Familia $familia): ?Empresa
    {
        $columnas = $this->generarColumnas($centro, $familia);
        return $columnas === null ? null : $this->guardar($columnas, $centro, $familia);
    }

    /**
     * Las 2 llamadas a la IA (datos + diagnóstico P1–P5) ya convertidas a columnas.
     *
     * @return array<string, string|null>|null  null si cualquiera de las dos falla
     */
    private function generarColumnas(?CentroEducativo $centro, Familia $familia): ?array
    {
        $datos = $this->generarDatosEmpresa($centro, $familia);
        if (!$datos || empty($datos['nombre_comercial']) || empty($datos['sector'])) return null;

        $ubicacion = implode(', ', array_filter([$this->texto($datos['municipio'] ?? null), $this->texto($datos['provincia'] ?? null)]));
        $diag = $this->simularDiagnostico(
            $this->texto($datos['nombre_comercial']),
            $this->texto($datos['sector']),
            $this->texto($datos['tamano'] ?? null),
            $ubicacion ?: null,
        );
        if (!$diag || empty($diag['diaANormal']) || empty($diag['friccionArea']) || empty($diag['friccionProblema'])) return null;

        return $this->prepararColumnas($centro, $datos, $diag);
    }

    /**
     * Crea la empresa ficticia (del centro, o plantilla del catálogo si $centro es null) y la
     * vincula a la familia.
     *
     * @param array<string, string|null> $columnas
     */
    private function guardar(array $columnas, ?CentroEducativo $centro, Familia $familia): Empresa
    {
        return DB::transaction(function () use ($columnas, $centro, $familia) {
            $empresa = new Empresa($columnas + [
                'es_simulada'      => true,
                'centro_id'        => $centro?->id,
                'centro_educativo' => $centro?->nombre, // legacy
            ]);
            $empresa->es_catalogo = $centro === null; // no fillable: solo lo fija el backend
            $empresa->save();
            // Mismo vínculo que DatosFPController::guardarFamiliaEmpresa (FK + string legacy).
            DB::table('empresa_familia')->insert([
                'empresa_id' => $empresa->id,
                'familia'    => $familia->nombre,
                'familia_id' => $familia->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return $empresa->load('familias:id,nombre');
        });
    }

    /**
     * Respuestas de la IA → columnas de `empresas`: solo campos conocidos, sin HTML, listas
     * filtradas a las opciones del generador y recortadas a los límites de StoreEmpresaRequest.
     *
     * @param array<string, mixed> $datos  respuesta de generarDatosEmpresa()
     * @param array<string, mixed> $diag   respuesta de simularDiagnostico()
     * @return array<string, string|null>
     */
    public function prepararColumnas(?CentroEducativo $centro, array $datos, array $diag): array
    {
        // Mismo formato que guarda el generador: chips de la lista + texto libre, unidos por ", ".
        $restricciones = $this->lista($diag['restricciones'] ?? [], self::LIMITACIONES, $diag['otraLimitacion'] ?? null);
        $consecuencias = $this->lista($diag['consecuencias'] ?? [], self::CONSECUENCIAS, $diag['otraConsecuencia'] ?? null);
        // El paso 1 del generador exige tamaño: si la IA no da uno válido, el más habitual.
        $tamano = in_array($datos['tamano'] ?? null, self::TAMANOS, true) ? $datos['tamano'] : 'Pequeña (10-50)';

        $columnas = [
            'nombre_comercial'    => Empresa::nombreLibreEnCentro($centro?->id, $this->texto($datos['nombre_comercial'])),
            'razon_social'        => $this->texto($datos['razon_social'] ?? null),
            'cif'                 => $this->texto($datos['cif'] ?? null),
            'sector'              => $this->texto($datos['sector']),
            'tamano'              => $tamano,
            'web'                 => $this->texto($datos['web'] ?? null),
            'actividad'           => $this->texto($datos['actividad'] ?? null),
            'persona_contacto'    => $this->texto($datos['persona_contacto'] ?? null),
            'telefono'            => $this->texto($datos['telefono'] ?? null),
            'email_general'       => filter_var($datos['email_general'] ?? null, FILTER_VALIDATE_EMAIL) ?: null,
            'direccion'           => $this->texto($datos['direccion'] ?? null),
            'municipio'           => $this->texto($datos['municipio'] ?? null),
            'provincia'           => $this->texto($datos['provincia'] ?? null),
            'codigo_postal'       => $this->texto($datos['codigo_postal'] ?? null),
            'dia_a_normal'        => $this->texto($diag['diaANormal']),
            'friccion_area'       => $this->texto($diag['friccionArea']),
            'friccion_problema'   => $this->texto($diag['friccionProblema']),
            'restricciones'       => $restricciones,
            'consecuencias'       => $consecuencias,
            'lo_que_no_quieren'   => $this->texto($diag['loQueNoQuieren'] ?? null),
            'expectativas_alumno' => $this->texto($diag['expectativasAlumno'] ?? null),
        ];
        foreach ($columnas as $columna => $valor) {
            if (is_string($valor)) $columnas[$columna] = mb_substr($valor, 0, self::MAX_COLUMNA[$columna]);
        }

        return $columnas;
    }

    /** @return array<string, mixed>|null */
    private function pedirJson(string $systemPrompt, string $userPrompt, float $temperatura, string $operacion): ?array
    {
        $response = Http::withToken(config('services.openai.key'))
            ->timeout(60)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'           => 'gpt-4o',
                'messages'        => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userPrompt],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature'     => $temperatura,
            ]);

        if (!$response->successful()) {
            Log::error("Fallo de OpenAI al {$operacion}.", ['status' => $response->status()]);
            return null;
        }

        $data = json_decode((string) $response->json('choices.0.message.content'), true);
        return is_array($data) ? $data : null;
    }

    private function texto(mixed $valor): string
    {
        return is_string($valor) ? trim(strip_tags($valor)) : '';
    }

    /**
     * @param mixed         $elegidos  lo que devolvió la IA (debería ser un array de textos de $permitidos)
     * @param array<string> $permitidos
     */
    private function lista(mixed $elegidos, array $permitidos, mixed $otro): string
    {
        $items = array_values(array_intersect(is_array($elegidos) ? $elegidos : [], $permitidos));
        $extra = $this->texto($otro);
        if ($extra !== '') $items[] = $extra;
        return implode(', ', $items);
    }
}
