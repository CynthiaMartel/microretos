<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * DuaLab Central → Empresas → «Enviar soluciones a las empresas»: un encuentro creativo con el
 * estado de la valoración de su empresa. Es DuaLab quien la envía, así que lleva el enlace (token)
 * y el correo de contacto de la empresa (Central ya consulta los contactos: ConsultaCentralController).
 * Recurso = Encuentro con 'valoracionEmpresa', 'equipos.fases', 'docente' y 'microproyecto.empresa'.
 */
class ValoracionEmpresaCentralResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $v = $this->valoracionEmpresa;
        $p = $this->microproyecto;
        $empresa = $p?->empresa;
        $equipos = $this->equipos;

        return [
            'encuentro_id'       => $this->id,
            'fecha'              => $this->fecha?->format('Y-m-d'),
            'curso'              => $this->curso,
            'grupo'              => $this->grupo,
            'centro'             => $this->centro_educativo,
            'docente'            => $this->docente?->name,
            'proyecto_titulo'    => $p?->titulo,
            'proyecto_estado'    => $p?->estado,
            'empresa_id'         => $empresa?->id,
            'empresa_nombre'     => $empresa?->nombre_comercial ?? $p?->microreto?->empresa_nombre,
            'empresa_sector'     => $empresa?->sector,
            'empresa_email'      => $empresa?->email_contacto ?: $empresa?->email_general,
            'grupos_total'       => $equipos->count(),
            'grupos_con_entrega' => $equipos->filter(fn ($e) => (bool) $e->fases->firstWhere('numero_fase', 3)?->completada)->count(),
            'solicitada'         => (bool) $v,
            'token'              => $v && !$v->caducado() ? $v->token : null,
            'token_expira_en'    => $v?->token_expira_en?->toIso8601String(),
            'caducado'           => $v ? $v->caducado() : false,
            'enviado_en'         => $v?->enviado_en?->toIso8601String(),
            'respondido_en'      => $v?->respondido_en?->toIso8601String(),
            'valoracion_global'  => $v?->valoracion_global,
            'respuestas'         => $v?->respuestas,
            'comentarios'        => $v?->comentarios,
        ];
    }
}
