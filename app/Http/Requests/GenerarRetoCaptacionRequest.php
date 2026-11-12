<?php

namespace App\Http\Requests;

/**
 * Petición INTERNA de BorradorRetoService (retos de demostración en la entrevista del comercial):
 * nunca se enlaza a una ruta. La empresa aún es una captación, no existe en `empresas`, así que
 * no lleva empresa_id; la actividad la fija el servicio desde la captación (no llega del
 * cliente) y el interés por la familia va en interesReto, como un ajuste del docente.
 */
class GenerarRetoCaptacionRequest extends GenerarMicroretoRequest
{
    // Actividad de la captación para el prompt (en el Generador sale de la empresa guardada)
    public ?string $actividadCaptacion = null;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['empresa_id' => 'prohibited'] + parent::rules();
    }
}
