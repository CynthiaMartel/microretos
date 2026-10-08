<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /empresas — alta de empresa desde el modal InsertModifyEmpresa o desde el
 * guardado del diagnóstico del Generador de Retos. Los nombres de campo van en
 * camelCase porque es el contrato que ya usa el frontend.
 */
class StoreEmpresaRequest extends FormRequest
{
    public const ESTADOS_CONTACTO = [
        'Pendiente de llamar',
        'Llamado - Información obtenida',
        'Llamado - Negativa',
        'Llamado - Llamar más tarde',
        'En colaboración activa',
        'Descartada',
    ];

    public function authorize(): bool
    {
        // La ruta ya está restringida por el middleware 'admin'; el alcance por centro
        // (admin solo en el suyo) se aplica en DatosFPController.
        return true;
    }

    protected function prepareForValidation(): void
    {
        // El frontend envía 'consecuencias' como array de chips o como texto ya unido;
        // se normaliza a texto, que es lo que guarda la columna.
        if (is_array($this->consecuencias)) {
            $this->merge(['consecuencias' => implode(', ', array_filter($this->consecuencias, 'is_string'))]);
        }
    }

    public function rules(): array
    {
        return [
            'nombreComercial'  => 'required|string|max:255',
            'razonSocial'      => 'nullable|string|max:255',
            'cif'              => 'nullable|string|max:20',
            'centroEducativo'  => 'nullable|string|max:255',
            'sector'           => 'nullable|string|max:255',
            'tamano'           => 'nullable|string|max:50',
            'web'              => 'nullable|string|max:255',
            'actividad'        => 'nullable|string|max:500',
            'personaContacto'  => 'nullable|string|max:255',
            'telefono'         => 'nullable|string|max:20',
            'emailGeneral'     => 'nullable|email|max:255',
            'direccion'        => 'nullable|string|max:255',
            'municipio'        => 'nullable|string|max:255',
            'provincia'        => 'nullable|string|max:255',
            'codigoPostal'     => 'nullable|string|max:10',
            'diaANormal'       => 'nullable|string|max:1000',
            'friccionArea'     => 'nullable|string|max:400',
            'friccionProblema' => 'nullable|string|max:1200',
            'consecuencias'    => 'nullable|string|max:2000',
            'restricciones'    => 'nullable|string|max:600',
            'loQueNoQuieren'   => 'nullable|string|max:500',
            'expectativasAlumno' => 'nullable|string|max:800',
            'esSimulada'       => 'nullable|boolean',
            'estadoContacto'   => 'nullable|string|in:' . implode(',', self::ESTADOS_CONTACTO),
            // Toda empresa nueva nace con al menos una familia vinculada. 'familias' (lista
            // completa, por nombre) es lo que envía el modal InsertModifyEmpresa: en la edición
            // reemplaza el conjunto entero. 'familia' (una sola) se mantiene para el Generador
            // de Retos, y en la edición solo añade esa familia sin quitar las demás.
            'familia'          => 'required_without:familias|string|max:255|exists:familias,nombre,deleted_at,NULL',
            'familias'         => 'required_without:familia|array|min:1|max:30',
            'familias.*'       => 'string|distinct|max:255|exists:familias,nombre,deleted_at,NULL',
            'ciclosIds'        => 'nullable|array|max:100',
            'ciclosIds.*'      => 'integer|distinct|exists:ciclos_formativos,id,deleted_at,NULL',
        ];
    }
}
