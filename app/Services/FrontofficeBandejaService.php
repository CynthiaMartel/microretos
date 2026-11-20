<?php

namespace App\Services;

use App\Exceptions\FrontofficeNoDisponible;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente de la API firmada de la web para el espacio «Primer contacto» de DuaLab Central.
 * Firma igual que VerificarFirmaCentral del frontoffice:
 *   HMAC-SHA256(«fecha \n MÉTODO \n /ruta \n query ordenada \n usuario \n cuerpo», secreto)
 * La ruta firmada es la de Laravel (/api/…), sin el prefijo del alojamiento de la URL base.
 */
class FrontofficeBandejaService
{
    private const RUTA = '/api/integraciones/central/formularios';

    public function listar(array $filtros, string $usuario): array
    {
        return $this->pedir('GET', self::RUTA, $usuario, query: $filtros)->json();
    }

    /** null si el formulario no existe (o se borró en Gestión web). */
    public function ver(int $id, string $usuario): ?array
    {
        $r = $this->pedir('GET', self::RUTA."/{$id}", $usuario, admitir404: true);

        return $r->status() === 404 ? null : $r->json();
    }

    public function cambiarEstado(int $id, string $estado, string $usuario): ?array
    {
        $r = $this->pedir('PATCH', self::RUTA."/{$id}", $usuario, cuerpo: ['estado' => $estado], admitir404: true);

        return $r->status() === 404 ? null : $r->json();
    }

    // ── Correo de info@ ──────────────────────────────────────────────────────

    private const RUTA_CORREO = '/api/integraciones/central/correo';

    public function listarCorreos(array $filtros, string $usuario): array
    {
        return $this->pedir('GET', self::RUTA_CORREO, $usuario, query: $filtros)->json();
    }

    public function verCorreo(int $id, string $usuario): ?array
    {
        $r = $this->pedir('GET', self::RUTA_CORREO."/{$id}", $usuario, admitir404: true);

        return $r->status() === 404 ? null : $r->json();
    }

    public function cambiarEstadoCorreo(int $id, string $estado, string $usuario): ?array
    {
        $r = $this->pedir('PATCH', self::RUTA_CORREO."/{$id}", $usuario, cuerpo: ['estado' => $estado], admitir404: true);

        return $r->status() === 404 ? null : $r->json();
    }

    /** Respuesta en crudo (el controlador la reenvía como descarga); 404 si no existe o no es de ese correo. */
    public function descargarAdjunto(int $correo, int $adjunto, string $usuario): Response
    {
        return $this->pedir('GET', self::RUTA_CORREO."/{$correo}/adjuntos/{$adjunto}", $usuario, admitir404: true);
    }

    /** 202 si queda en la bandeja de salida; 422 con los errores de validación de la web. */
    public function enviarCorreo(array $datos, string $usuario): Response
    {
        return $this->pedir('POST', self::RUTA_CORREO.'/enviar', $usuario, cuerpo: $datos, admitir404: true, admitir422: true);
    }

    /** Un envío tal como salió (Historial de Central). null si ya no existe (se guardan 4 meses). */
    public function verEnvio(int $id, string $usuario): ?array
    {
        $r = $this->pedir('GET', self::RUTA_CORREO."/envios/{$id}", $usuario, admitir404: true);

        return $r->status() === 404 ? null : $r->json();
    }

    public function reintentarEnvio(int $id, string $usuario): Response
    {
        return $this->pedir('POST', self::RUTA_CORREO."/envios/{$id}/reintentar", $usuario, admitir404: true, admitir422: true);
    }

    public function descartarEnvio(int $id, string $usuario): Response
    {
        return $this->pedir('DELETE', self::RUTA_CORREO."/envios/{$id}", $usuario, admitir404: true, admitir422: true);
    }

    /** Borra del todo la copia de un correo que ya está en la papelera; null si ya no existe. */
    public function borrarCorreo(int $id, string $usuario): ?Response
    {
        $r = $this->pedir('DELETE', self::RUTA_CORREO."/{$id}", $usuario, admitir404: true, admitir422: true);

        return $r->status() === 404 ? null : $r;
    }

    public function vaciarPapeleraCorreo(string $usuario): int
    {
        return (int) $this->pedir('DELETE', self::RUTA_CORREO.'/papelera', $usuario)->json('eliminados', 0);
    }

    private function pedir(string $metodo, string $ruta, string $usuario, array $query = [], array $cuerpo = [], bool $admitir404 = false, bool $admitir422 = false): Response
    {
        $base    = (string) config('frontoffice.api_url');
        $secreto = (string) config('frontoffice.central_secreto');
        if ($base === '' || strlen($secreto) < 32) {
            throw new FrontofficeNoDisponible('Integración con la web sin configurar.');
        }

        $query = array_filter($query, fn ($v) => $v !== null && $v !== '');
        ksort($query);
        $qs    = http_build_query($query);
        $json  = $cuerpo ? json_encode($cuerpo, JSON_UNESCAPED_UNICODE) : '';
        $fecha = (string) time();
        // Solo texto plano y corto en la cabecera (va al registro de actividad de la web)
        $usuario = mb_substr(preg_replace('/[^\p{L}\p{N} .\'-]/u', '', $usuario) ?: 'superadmin', 0, 80);
        $firma = hash_hmac('sha256', implode("\n", [$fecha, $metodo, $ruta, $qs, $usuario, $json]), $secreto);

        try {
            $peticion = Http::timeout($metodo === 'POST' ? 30 : (int) config('frontoffice.timeout', 8))
                ->acceptJson()
                ->withHeaders(['X-DuaLab-Fecha' => $fecha, 'X-DuaLab-Firma' => $firma, 'X-DuaLab-Usuario' => $usuario]);
            if ($json !== '') {
                $peticion = $peticion->withBody($json, 'application/json');
            }
            $r = $peticion->send($metodo, $base.$ruta.($qs !== '' ? "?{$qs}" : ''));
        } catch (ConnectionException $e) {
            Log::warning('Primer contacto: la API de la web no responde.', ['error' => $e->getMessage()]);
            throw new FrontofficeNoDisponible('La web no responde.', previous: $e);
        }

        if ($r->successful() || ($admitir404 && $r->status() === 404) || ($admitir422 && $r->status() === 422)) {
            return $r;
        }
        Log::warning('Primer contacto: respuesta inesperada de la API de la web.', ['status' => $r->status()]);
        throw new FrontofficeNoDisponible("La web respondió {$r->status()}.");
    }
}
