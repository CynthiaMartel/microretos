<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Microproyecto;
use App\Models\MicroproyectoRecurso;

class UploadController extends Controller
{
    /**
     * Sube un archivo a Cloudinary y guarda sus metadatos en microproyecto_recursos.
     * El archivo nunca se almacena en el servidor ni en la BD — la BD solo guarda
     * la URL y metadatos ligeros; el fichero físico vive en Cloudinary.
     *
     * Variables .env requeridas:
     *   CLOUDINARY_CLOUD_NAME
     *   CLOUDINARY_API_KEY
     *   CLOUDINARY_API_SECRET
     *   CLOUDINARY_FOLDER   (opcional, default: "dualab/recursos")
     *   UPLOAD_MAX_SIZE_MB  (opcional, default: 20)
     */
    public function recurso(Request $request): JsonResponse
    {
        $maxMb = (int) config('services.cloudinary.upload_max_mb', 20);

        $request->validate([
            'file'               => [
                'required', 'file', "max:{$maxMb}000",
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,png,jpg,jpeg,gif,webp,mp4,mov,avi,mkv,webm,zip',
            ],
            'microproyecto_uuid' => 'required|string|max:100',
            'tipo'               => 'required|in:video,documento,imagen',
            'label'              => 'nullable|string|max:200',
        ], [
            'file.mimes' => 'Tipo no permitido. Sube PDF, documentos Office, imágenes, vídeos o ZIP.',
            'file.max'   => "El archivo supera el límite de {$maxMb} MB.",
        ]);

        $proyecto = Microproyecto::where('uuid', $request->input('microproyecto_uuid'))->firstOrFail();

        if (!$proyecto->esEditablePara($request->user())) {
            abort(403, 'No autorizado: no tienes acceso de edición a este microproyecto.');
        }

        $cloudName = config('services.cloudinary.cloud_name');
        $apiKey    = config('services.cloudinary.api_key');
        $apiSecret = config('services.cloudinary.api_secret');
        $folder    = config('services.cloudinary.folder');

        if (!$cloudName || !$apiKey || !$apiSecret) {
            return response()->json([
                'message' => 'Cloudinary no está configurado. Añade CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY y CLOUDINARY_API_SECRET al .env.',
            ], 503);
        }

        $file      = $request->file('file');
        $timestamp = time();
        $mime      = $file->getMimeType();

        // Cloudinary requiere resource_type explícito:
        // video/* → video | image/* → image | resto (pdf, docx, zip…) → raw
        $resourceType = match (true) {
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'image/') => 'image',
            default                          => 'raw',
        };

        // Firma HMAC-SHA1 — parámetros en orden alfabético
        $paramsToSign = "folder={$folder}&timestamp={$timestamp}";
        $signature    = hash('sha1', $paramsToSign . $apiSecret);

        $response = Http::timeout(60)
            ->attach('file', fopen($file->getRealPath(), 'r'), $file->getClientOriginalName())
            ->post("https://api.cloudinary.com/v1_1/{$cloudName}/{$resourceType}/upload", [
                'api_key'   => $apiKey,
                'timestamp' => $timestamp,
                'signature' => $signature,
                'folder'    => $folder,
            ]);

        if ($response->failed()) {
            return response()->json([
                'message' => 'Error al subir el archivo a Cloudinary.',
                'detail'  => $response->json('error.message', 'Respuesta inesperada'),
            ], 502);
        }

        $data  = $response->json();
        $label = trim($request->input('label', ''));

        // Guardar metadatos en BD — el fichero físico ya está en Cloudinary
        $recurso = MicroproyectoRecurso::create([
            'microproyecto_id' => $proyecto->id,
            'tipo'             => $request->input('tipo'),
            'label'            => $label ?: null,
            'filename'         => $file->getClientOriginalName(),
            'url'              => $data['secure_url'],
            'public_id'        => $data['public_id'],
            'resource_type'    => $resourceType,
            'mime'             => $mime,
            'size'             => $data['bytes'] ?? null,
        ]);

        // Primera imagen del proyecto -> portada automática (editable después).
        if ($recurso->tipo === 'imagen' && $proyecto->imagen_portada_id === null) {
            $proyecto->update(['imagen_portada_id' => $recurso->id]);
        }

        return response()->json([
            'id'            => $recurso->id,
            'url'           => $recurso->url,
            'public_id'     => $recurso->public_id,
            'resource_type' => $recurso->resource_type,
            'filename'      => $recurso->filename,
            'label'         => $recurso->label ?? '',
            'size'          => $recurso->size,
            'mime'          => $recurso->mime,
        ], 201);
    }

    /**
     * POST /centros/imagen
     * Sube una imagen de portada de centro educativo a Cloudinary y devuelve su URL.
     * No crea ningún registro en BD — la URL se guarda en centro_educativo.img al
     * crear/editar el centro (ver DatosFPController::guardarCentro/actualizarCentro).
     */
    public function imagenCentro(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:5000', 'mimes:png,jpg,jpeg,gif,webp'],
        ], [
            'file.mimes' => 'Solo se admiten imágenes (PNG, JPG, GIF o WEBP).',
            'file.max'   => 'La imagen supera el límite de 5 MB.',
        ]);

        $cloudName = config('services.cloudinary.cloud_name');
        $apiKey    = config('services.cloudinary.api_key');
        $apiSecret = config('services.cloudinary.api_secret');
        $folder    = config('services.cloudinary.folder', 'dualab/recursos');

        if (!$cloudName || !$apiKey || !$apiSecret) {
            return response()->json([
                'message' => 'Cloudinary no está configurado. Añade CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY y CLOUDINARY_API_SECRET al .env.',
            ], 503);
        }

        $file         = $request->file('file');
        $timestamp    = time();
        $paramsToSign = "folder={$folder}&timestamp={$timestamp}";
        $signature    = hash('sha1', $paramsToSign . $apiSecret);

        $response = Http::timeout(60)
            ->attach('file', fopen($file->getRealPath(), 'r'), $file->getClientOriginalName())
            ->post("https://api.cloudinary.com/v1_1/{$cloudName}/image/upload", [
                'api_key'   => $apiKey,
                'timestamp' => $timestamp,
                'signature' => $signature,
                'folder'    => $folder,
            ]);

        if ($response->failed()) {
            return response()->json([
                'message' => 'Error al subir la imagen a Cloudinary.',
                'detail'  => $response->json('error.message', 'Respuesta inesperada'),
            ], 502);
        }

        return response()->json(['url' => $response->json('secure_url')]);
    }

    /**
     * Lista los recursos de un microproyecto desde la BD, agrupados por tipo.
     */
    public function listar(Request $request): JsonResponse
    {
        $uuid = $request->query('microproyecto');

        if (!$uuid) {
            return response()->json(['videos' => [], 'documentos' => []]);
        }

        $proyecto = Microproyecto::where('uuid', $uuid)->first();

        if (!$proyecto || !$proyecto->esVisiblePara($request->user())) {
            return response()->json(['videos' => [], 'documentos' => [], 'imagenes' => [], 'imagen_portada_id' => null]);
        }

        $recursos = $proyecto->recursos;

        $formato = fn(MicroproyectoRecurso $r) => [
            'id'            => $r->id,
            'url'           => $r->url,
            'public_id'     => $r->public_id,
            'resource_type' => $r->resource_type,
            'filename'      => $r->filename,
            'label'         => $r->label ?? '',
        ];

        return response()->json([
            'videos'            => $recursos->where('tipo', 'video')->map($formato)->values(),
            'documentos'        => $recursos->where('tipo', 'documento')->map($formato)->values(),
            'imagenes'          => $recursos->where('tipo', 'imagen')->map($formato)->values(),
            'imagen_portada_id' => $proyecto->imagen_portada_id,
        ]);
    }

    /**
     * Marca una imagen ya subida como portada del proyecto (la que se ve en las
     * tarjetas). Por defecto se asigna la primera imagen subida, pero se puede
     * cambiar en cualquier momento a otra de la galería.
     */
    public function marcarPortada(Request $request): JsonResponse
    {
        $request->validate([
            'microproyecto_uuid' => 'required|string|max:100',
            'recurso_id'         => 'required|integer',
        ]);

        $proyecto = Microproyecto::where('uuid', $request->input('microproyecto_uuid'))->firstOrFail();

        if (!$proyecto->esEditablePara($request->user())) {
            abort(403, 'No autorizado: no tienes acceso de edición a este microproyecto.');
        }

        $recurso = MicroproyectoRecurso::where('id', $request->input('recurso_id'))
            ->where('microproyecto_id', $proyecto->id)
            ->where('tipo', 'imagen')
            ->firstOrFail();

        $proyecto->update(['imagen_portada_id' => $recurso->id]);

        return response()->json(['ok' => true, 'imagen_portada_id' => $recurso->id]);
    }

    /**
     * Elimina un recurso de Cloudinary y borra su registro de la BD.
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'public_id'     => 'required|string|max:500',
            'resource_type' => 'nullable|string|in:image,video,raw',
        ]);

        $publicId     = $request->input('public_id');
        $resourceType = $request->input('resource_type', 'raw');

        $recurso = MicroproyectoRecurso::where('public_id', $publicId)->with('microproyecto')->first();
        if ($recurso && (!$recurso->microproyecto || !$recurso->microproyecto->esEditablePara($request->user()))) {
            abort(403, 'No autorizado: no tienes acceso de edición a este microproyecto.');
        }

        // Eliminar de BD primero
        MicroproyectoRecurso::where('public_id', $publicId)->delete();

        // Eliminar de Cloudinary
        $cloudName = config('services.cloudinary.cloud_name');
        $apiKey    = config('services.cloudinary.api_key');
        $apiSecret = config('services.cloudinary.api_secret');

        if ($cloudName && $apiKey && $apiSecret) {
            $timestamp = time();
            $signature = hash('sha1', "public_id={$publicId}&timestamp={$timestamp}{$apiSecret}");

            Http::post(
                "https://api.cloudinary.com/v1_1/{$cloudName}/{$resourceType}/destroy",
                [
                    'public_id' => $publicId,
                    'api_key'   => $apiKey,
                    'timestamp' => $timestamp,
                    'signature' => $signature,
                ]
            );
        }

        return response()->json(['ok' => true]);
    }
}
