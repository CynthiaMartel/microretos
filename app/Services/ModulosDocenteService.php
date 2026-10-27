<?php

namespace App\Services;

use App\Models\CentroEducativo;
use App\Models\CicloFormativo;
use App\Models\Familia;
use App\Models\Microproyecto;
use App\Models\Modulo;
use App\Models\User;

/**
 * Módulos que imparte cada docente (pivot docente_modulo) y la regla de "compañeros" que
 * se apoya en ellos: un docente puede invitar como colaborador de un encuentro solo a
 * compañeros de su centro que compartan con el proyecto la familia y al menos un módulo.
 *
 * Los módulos válidos para un docente son los de los ciclos que oferta su centro
 * (centro_ciclo). La familia de un docente se deduce del ciclo de sus módulos.
 */
class ModulosDocenteService
{
    /**
     * Oferta del centro para el selector: sus ciclos (con familia) y los módulos de cada uno.
     *
     * @return list<array{id: int, nombre: string, familia: ?string, modulos: list<array{id: int, nombre: string, curso: mixed, codigo: ?string}>}>
     */
    public function oferta(?int $centroId): array
    {
        $ciclos = $this->ciclosDelCentro($centroId);
        if (!$ciclos) {
            return [];
        }

        $resultado = [];
        $ciclosOferta = CicloFormativo::with(['modulos' => fn ($q) => $q->orderBy('curso')->orderBy('nombre')])
            ->whereIn('id', $ciclos)->orderBy('nombre')->get();
        // Nombre de la familia por familia_id: ciclos_formativos tiene además una columna de
        // texto legacy llamada 'familia' que taparía la relación del mismo nombre
        $familias = Familia::whereIn('id', $ciclosOferta->pluck('familia_id')->filter())->pluck('nombre', 'id');

        foreach ($ciclosOferta as $c) {
            $modulosCiclo = [];
            foreach ($c->modulos as $m) {
                $modulosCiclo[] = [
                    'id'     => (int) $m->id,
                    'nombre' => (string) $m->getAttribute('nombre'),
                    'curso'  => $m->getAttribute('curso'),
                    'codigo' => $m->getAttribute('codigoBOE'),
                ];
            }
            $familia = $familias->get($c->familia_id);
            $resultado[] = [
                'id'      => (int) $c->id,
                'nombre'  => (string) $c->nombre,
                'familia' => is_string($familia) ? $familia : null,
                'modulos' => $modulosCiclo,
            ];
        }

        return $resultado;
    }

    /**
     * Solo los ids que pertenecen a ciclos ofertados por el centro (lista blanca).
     *
     * @param array<int, int> $ids
     * @return array<int, int>
     */
    public function idsValidosParaCentro(?int $centroId, array $ids): array
    {
        $ciclos = $this->ciclosDelCentro($centroId);
        if (!$ciclos || !$ids) {
            return [];
        }

        return Modulo::whereIn('id', $ids)->whereIn('idcicloformativo', $ciclos)
            ->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }

    /** @param array<int, int> $ids  ya validados con idsValidosParaCentro() */
    public function sincronizar(User $docente, array $ids): void
    {
        $docente->modulos()->sync($ids);
    }

    /**
     * Módulos de la oferta de su centro que el docente usa en sus propios proyectos
     * (modulos_seleccionados), y cuántos proyectos tiene. Solo lectura: sirve para sugerirle
     * qué marcar como impartido y para la ficha de «Mi usuario».
     *
     * `descartados`: módulos de sus proyectos que no están en la oferta actual de su centro.
     *
     * @return array{modulos: array<int, int>, descartados: int, proyectos: int}
     */
    public function usoEnProyectos(User $docente): array
    {
        $proyectos = Microproyecto::where('user_id', $docente->id);

        $ids = (clone $proyectos)->latest('id')->take(500)->pluck('modulos_seleccionados')
            ->flatten(1)
            ->map(fn ($m) => is_array($m) ? (int) ($m['id'] ?? 0) : 0)
            ->filter(fn (int $id) => $id > 0)->unique()->values()->all();

        $validos = $this->idsValidosParaCentro($docente->centro_educativo_id, $ids);

        return [
            'modulos'     => $validos,
            'descartados' => count($ids) - count($validos),
            'proyectos'   => $proyectos->count(),
        ];
    }

    /**
     * ¿Se le piden los módulos que imparte? Docentes con centro y admins de centro que crean
     * proyectos (también dan clase). Un admin que solo gestiona no recibe el aviso.
     */
    public function imparte(User $user): bool
    {
        if (!$user->centro_educativo_id) {
            return false;
        }

        return $user->isDocente()
            || ($user->isAdmin() && Microproyecto::where('user_id', $user->id)->exists());
    }

    /** @return array<int, int> */
    public function asignados(User $docente): array
    {
        return $docente->modulos()->pluck('modulos.id')->map(fn ($id) => (int) $id)->values()->all();
    }

    /**
     * ¿El docente comparte con el proyecto la familia y al menos un módulo?
     * Los proyectos guardan sus módulos como [{id, nombre}]; los antiguos solo tienen los
     * nombres en evaluacion_oficial, así que entonces se compara por nombre. Un proyecto
     * sin módulos registrados se conforma con la familia.
     */
    public function comparteConProyecto(User $docente, ?Microproyecto $proyecto): bool
    {
        if (!$proyecto || !$proyecto->familia_id) {
            return false;
        }

        $modulos = $docente->relationLoaded('modulos')
            ? $docente->modulos
            : $docente->modulos()->with('cicloFormativo:id,familia_id')->get();
        $modulos->loadMissing('cicloFormativo:id,familia_id');

        $familias = $modulos->map(fn (Modulo $m) => $m->cicloFormativo?->familia_id)->filter()->unique();
        if (!$familias->contains($proyecto->familia_id)) {
            return false;
        }

        $idsProyecto = collect($proyecto->modulos_seleccionados ?? [])
            ->map(fn ($m) => is_array($m) ? (int) ($m['id'] ?? 0) : 0)->filter()->values();
        if ($idsProyecto->isNotEmpty()) {
            return $modulos->pluck('id')->map(fn ($id) => (int) $id)->intersect($idsProyecto)->isNotEmpty();
        }

        $nombresProyecto = collect($proyecto->nombresModulos())->map(fn ($n) => mb_strtolower(trim($n)));
        if ($nombresProyecto->isEmpty()) {
            return true;
        }

        return $modulos->map(fn (Modulo $m) => mb_strtolower(trim((string) $m->getAttribute('nombre'))))
            ->intersect($nombresProyecto)->isNotEmpty();
    }

    /** @return array<int, int> */
    private function ciclosDelCentro(?int $centroId): array
    {
        if (!$centroId) {
            return [];
        }
        $nombre = CentroEducativo::whereKey($centroId)->value('nombre');
        $ids = CicloFormativo::idsDelCentro(is_string($nombre) ? $nombre : null);

        return $ids ? $ids->map(fn ($id) => (int) $id)->values()->all() : [];
    }
}
