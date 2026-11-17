<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /comercial/transcribir — dictado de pago de la app comercial: un audio corto (máx. 2
 * minutos, lo corta el navegador) que se pasa a texto y se descarta. Formatos de MediaRecorder:
 * webm/ogg (Chrome, Firefox) y mp4 (Safari); webm a veces se detecta como video/webm.
 */
class TranscribirAudioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'audio' => 'required|file|max:10240|mimetypes:audio/webm,video/webm,audio/ogg,audio/mp4,video/mp4,audio/x-m4a,audio/mpeg,audio/wav,audio/x-wav',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'audio.max'       => 'La grabación es demasiado larga.',
            'audio.mimetypes' => 'Formato de audio no admitido.',
        ];
    }
}
