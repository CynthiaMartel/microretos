<?php

namespace App\Http\Requests;

use App\Models\Empresa;
use App\Support\SectoresEmpresa;
use Illuminate\Support\Facades\DB;

/**
 * PUT /empresas/{id} — mismas reglas que el alta, pero todo es 'sometimes': un campo
 * que no llega no se toca. El Generador de Retos solo envía los campos del diagnóstico
 * y no debe vaciar el resto (CIF, contacto, dirección…).
 */
class UpdateEmpresaRequest extends StoreEmpresaRequest
{
    public function rules(): array
    {
        $reglas = collect(parent::rules())
            ->map(function (string|array $regla, string $campo) {
                if (str_ends_with($campo, '.*')) return $regla;
                return is_array($regla) ? ['sometimes', ...$regla] : 'sometimes|' . $regla;
            })
            ->all();

        // Empresas anteriores a la lista cerrada: su sector en texto libre se admite mientras
        // no cambie (el modal y el Generador reenvían el valor actual); para cambiarlo, de la lista.
        $reglas['sector'] = ['sometimes', 'nullable', 'string', 'max:255', function (string $atributo, mixed $valor, \Closure $fail) {
            if (SectoresEmpresa::esValido($valor)) return;
            if ($valor !== Empresa::whereKey($this->route('id'))->value('sector')) {
                $fail('Elige un sector de la lista.');
            }
        }];

        return $reglas;
    }

    /** En la edición también vale el interés de una familia que la empresa ya tenga vinculada. */
    protected function familiasConInteresPermitido(): array
    {
        // Con 'familias' se reemplaza el conjunto entero: solo cuentan las enviadas.
        if ($this->has('familias')) return parent::familiasConInteresPermitido();

        $vinculadas = DB::table('empresa_familia')
            ->join('familias', 'familias.id', '=', 'empresa_familia.familia_id')
            ->where('empresa_familia.empresa_id', (int) $this->route('id'))
            ->pluck('familias.nombre')
            ->all();

        return array_merge(parent::familiasConInteresPermitido(), $vinculadas);
    }
}
