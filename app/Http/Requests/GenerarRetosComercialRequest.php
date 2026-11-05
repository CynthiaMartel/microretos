<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /comercial/empresas/{id}/generar-retos — retos de demostración delante de la empresa.
 * Solo el contexto académico: los datos y el diagnóstico de la empresa salen de la BD (nunca
 * del cliente). La familia tiene que estar vinculada a la empresa y el ciclo pertenecer a
 * ella (se comprueba en BorradorRetoService).
 */
class GenerarRetosComercialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'familia'           => 'required|string|max:255|exists:familias,nombre,deleted_at,NULL',
            'ciclo_id'          => 'required|integer|exists:ciclos_formativos,id,deleted_at,NULL',
            'cursoSeleccionado' => 'required|in:1,2,ambos_cursos',
            'nivelGrupo'        => 'required|in:Bajo,Medio,Alto',
            // Demostración: pocos retos, que la IA responda rápido delante del cliente
            'cantidad'          => 'required|integer|min:1|max:3',
            'enfoqueReto'       => 'sometimes|nullable|string|max:500',
        ];
    }
}
