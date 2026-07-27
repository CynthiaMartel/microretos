<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEquipoPrototipoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxMb = (int) config('services.cloudinary.upload_max_mb', 20);

        return [
            'file' => [
                'required', 'file', "max:{$maxMb}000",
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,png,jpg,jpeg,gif,webp,mp4,mov,avi,mkv,webm,zip',
            ],
            'label'    => 'nullable|string|max:200',
            'contexto' => 'sometimes|in:prototipo,entregable',
        ];
    }

    public function messages(): array
    {
        $maxMb = (int) config('services.cloudinary.upload_max_mb', 20);

        return [
            'file.mimes' => 'Tipo no permitido. Sube PDF, documentos Office, imágenes, vídeos o ZIP.',
            'file.max'   => "El archivo supera el límite de {$maxMb} MB.",
        ];
    }
}
