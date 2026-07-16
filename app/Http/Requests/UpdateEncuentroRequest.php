<?php

namespace App\Http\Requests;

use App\Models\Encuentro;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEncuentroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'fecha_fin' => ['sometimes', 'nullable', 'date', function ($attribute, $value, $fail) {
                if (!$value) return;

                $encuentro = Encuentro::where('id', $this->route('id'))
                    ->where('user_id', $this->user()->id)
                    ->first();
                if (!$encuentro || !$encuentro->fecha || !$encuentro->microproyecto) return;

                $minimo = $encuentro->microproyecto->fechaFinSugerida(Carbon::parse($encuentro->fecha));
                if ($minimo && Carbon::parse($value)->lt($minimo)) {
                    $totalClases = $encuentro->microproyecto->totalClasesEstimadas();
                    $fail("La fecha fin no puede ser anterior a {$minimo->toDateString()} ({$totalClases} clases estimadas para este proyecto).");
                }
            }],
        ];
    }
}
