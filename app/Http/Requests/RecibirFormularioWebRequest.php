<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /integraciones/frontoffice/formularios — un formulario de dualab.es/contacto reenviado por
 * el webhook del frontoffice (payload de EnviarFormularioAWebhook::payload). La firma ya la ha
 * comprobado el middleware VerificarFirmaFrontoffice; aquí solo la forma y los límites (los
 * mismos que ContactoRequest del frontoffice). Qué campos de 'datos' se usan lo decide
 * CaptacionFormularioWebService.
 */
class RecibirFormularioWebRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'evento'             => 'required|string|in:formulario.recibido',
            'id'                 => 'required|integer|min:1',
            'formulario'         => 'required|string|max:30',
            'accion'             => 'nullable|string|in:registro,info,demo',
            'contacto'           => 'required|array',
            'contacto.nombre'    => 'required|string|max:120',
            'contacto.apellidos' => 'nullable|string|max:120',
            'contacto.email'     => 'required|email|max:255',
            'contacto.telefono'  => 'nullable|string|max:20',
            'contacto.cargo'     => 'nullable|string|max:120',
            'datos'              => 'present|array',
            'datos.centro_nombre' => 'nullable|string|max:160',
            'datos.empresa_nombre' => 'nullable|string|max:160',
            'datos.cif'          => 'nullable|string|max:15',
            'datos.sector'       => 'nullable|string|max:60',
            'datos.tamano'       => 'nullable|string|max:20',
            'datos.colabora'     => 'nullable|string|max:20',
            'datos.direccion'    => 'nullable|string|max:200',
            'datos.municipio'    => 'nullable|string|max:120',
            'datos.provincia'    => 'nullable|string|max:60',
            'datos.familias'     => 'nullable|array|max:4',
            'datos.familias.*'   => 'string|max:120',
            'datos.intereses'    => 'nullable|array|max:10',
            'datos.intereses.*'  => 'string|max:40',
            'datos.comentarios'  => 'nullable|string|max:2000',
            'datos.mensaje'      => 'nullable|string|max:2000',
        ];
    }
}
