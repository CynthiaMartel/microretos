<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Correo nuevo, respuesta o reenvío desde Central (multipart, con adjuntos). Mismos límites que la web
 * (config/correo.php del frontoffice), que vuelve a validarlo todo antes de encolarlo: este es el
 * primer filtro, para no mandar por la API archivos que la web va a rechazar.
 */
class EnviarCorreoWebRequest extends FormRequest
{
    public const EXTENSIONES = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp',
        'txt', 'csv', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'zip'];
    private const MAX_DESTINATARIOS = 20;
    private const MAX_ADJUNTOS = 10;
    private const MAX_ADJUNTO_MB = 10;
    private const MAX_TOTAL_MB = 20;

    public function authorize(): bool
    {
        return true; // acceso por el middleware de la ruta
    }

    public function rules(): array
    {
        return [
            'tipo'                => ['required', Rule::in(['nuevo', 'responder', 'reenviar'])],
            'correo_id'           => ['nullable', 'required_unless:tipo,nuevo', 'integer', 'min:1'],
            'contacto_id'         => ['nullable', 'integer', 'min:1'],
            'para'                => ['required', 'array', 'min:1'],
            'para.*'              => ['required', 'email:rfc', 'max:255'],
            'cc'                  => ['nullable', 'array'],
            'cc.*'                => ['email:rfc', 'max:255'],
            'cco'                 => ['nullable', 'array'],
            'cco.*'               => ['email:rfc', 'max:255'],
            'asunto'              => ['required', 'string', 'max:255'],
            'texto'               => ['required', 'string', 'max:50000'],
            'adjuntos'            => ['nullable', 'array', 'max:' . self::MAX_ADJUNTOS],
            'adjuntos.*'          => ['file', 'max:' . (self::MAX_ADJUNTO_MB * 1024),
                'extensions:' . implode(',', self::EXTENSIONES), 'mimes:' . implode(',', self::EXTENSIONES)],
            'reenviar_adjuntos'   => ['nullable', 'array', 'max:' . self::MAX_ADJUNTOS],
            'reenviar_adjuntos.*' => ['integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $destinatarios = count($this->input('para', [])) + count($this->input('cc', [])) + count($this->input('cco', []));
            if ($destinatarios > self::MAX_DESTINATARIOS) {
                $v->errors()->add('para', 'Como mucho ' . self::MAX_DESTINATARIOS . ' destinatarios por correo.');
            }
            if (collect($this->file('adjuntos', []))->sum(fn ($f) => $f->getSize()) > self::MAX_TOTAL_MB * 1024 * 1024) {
                $v->errors()->add('adjuntos', 'Los adjuntos no pueden sumar más de ' . self::MAX_TOTAL_MB . ' MB.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'para.required'         => 'Añade al menos un destinatario.',
            'para.*.email'          => 'Hay una dirección de correo que no es válida.',
            'cc.*.email'            => 'Hay una dirección en CC que no es válida.',
            'cco.*.email'           => 'Hay una dirección en CCO que no es válida.',
            'adjuntos.*.max'        => 'Cada adjunto puede ocupar como mucho ' . self::MAX_ADJUNTO_MB . ' MB.',
            'adjuntos.*.extensions' => 'Ese tipo de archivo no se puede adjuntar.',
            'adjuntos.*.mimes'      => 'Ese tipo de archivo no se puede adjuntar.',
        ];
    }
}
