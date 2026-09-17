<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Models\Encuentro;
use App\Models\Equipo;
use App\Models\Microproyecto;
use App\Models\Microreto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Borra en cascada TODO lo generado por los comandos demo:generar-* (empresas ficticias,
 * sus retos, proyectos, encuentros y equipos), en el orden correcto según las FKs reales:
 *
 *   Equipo (borrado duro — el modelo no usa SoftDeletes; equipo_miembros/fases/tareas/
 *           reflexiones/prototipos tienen FK cascadeOnDelete hacia equipos, así que se
 *           limpian solos a nivel de BD)
 *     → Encuentro (encuentros.microproyecto_id es nullOnDelete, NO cascade — hay que
 *                  borrarlo explícitamente o quedaría huérfano)
 *       → Microproyecto (microproyectos.microreto_id/empresa_id son nullOnDelete)
 *         → Microreto (microretos.empresa_id es RESTRICT — bloquea el borrado de la
 *                      empresa si queda algún reto suyo sin borrar antes)
 *           → empresa_familia (pivote, sin modelo Eloquent)
 *             → Empresa
 *
 * Se usa forceDelete() (no soft-delete) en los modelos con SoftDeletes: el objetivo es
 * limpiar de verdad para poder regenerar datos de demo limpios, no dejarlos recuperables
 * en la papelera. Se consultan también los registros ya soft-deleted (::withTrashed())
 * por si una ejecución parcial anterior, u otro flujo, los dejó a medio borrar.
 *
 * La trazabilidad de qué borrar sube por las FKs desde Empresa.es_simulada=true /
 * Microreto.es_simulado=true, y desde Microproyecto.es_demo=true (columna añadida
 * expresamente para no tener que inferir qué proyectos son de demo por FK arriba/abajo).
 * Encuentro/Equipo siguen sin columna propia — se derivan de microproyecto_id/encuentro_id.
 *
 * IMPORTANTE — 'es_simulada'/'es_simulado' son flags GENÉRICOS, no exclusivos de estos
 * comandos: al probar este comando en local se ha comprobado que ya existen empresas y
 * microretos de prueba con estos flags a true creados a mano, ajenos a demo:generar-*, de
 * OTROS centros (p.ej. empresas de un "IES PRUEBA_88" o similar, y microretos sin empresa
 * asociada). Sin acotar por centro, este comando los borraría también — justo lo que la
 * regla de "nunca tocar datos que no se pidió" prohíbe. Por eso TODO se acota además a
 * centro_id=10 (DuaLab), el único centro al que cuelgan las empresas de demo:generar-empresas.
 *
 * Dry-run por defecto: sin --force solo lista qué se borraría, sin tocar la BD.
 */
class DemoBorrarFicticios extends Command
{
    protected $signature = 'demo:borrar-ficticios
                            {--force : Ejecuta el borrado de verdad. Sin esta opción es un dry-run que solo lista qué se borraría.}';

    protected $description = 'Borra en cascada todos los datos de demo generados por los comandos demo:generar-* (empresas, retos, proyectos, encuentros y equipos).';

    private const CENTRO_ID = 10; // DuaLab — ver aviso de scoping en la cabecera de la clase

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        if (!$force) {
            $this->warn('Modo DRY-RUN — no se borra nada. Relanza con --force para borrar de verdad.');
        }

        $microretoIds = Microreto::withTrashed()->where('es_simulado', true)
            ->whereHas('empresa', fn ($q) => $q->where('centro_id', self::CENTRO_ID))
            ->pluck('id');
        $empresaIds   = Empresa::withTrashed()->where('es_simulada', true)
            ->where('centro_id', self::CENTRO_ID)
            ->pluck('id');

        $microproyectoIds = Microproyecto::withTrashed()
            ->where(function ($q) use ($microretoIds, $empresaIds) {
                $q->where('es_demo', true)
                  ->orWhereIn('microreto_id', $microretoIds)
                  ->orWhereIn('empresa_id', $empresaIds);
            })
            ->pluck('id');

        $encuentroIds = Encuentro::withTrashed()->whereIn('microproyecto_id', $microproyectoIds)->pluck('id');
        $equipoIds    = Equipo::whereIn('encuentro_id', $encuentroIds)
            ->orWhereIn('microproyecto_id', $microproyectoIds)
            ->pluck('id');

        $this->table(['Entidad', 'Nº registros'], [
            ['Empresas simuladas',    $empresaIds->count()],
            ['Microretos simulados',  $microretoIds->count()],
            ['Microproyectos',        $microproyectoIds->count()],
            ['Encuentros',            $encuentroIds->count()],
            ['Equipos',               $equipoIds->count()],
        ]);

        if ($empresaIds->isEmpty() && $microretoIds->isEmpty()) {
            $this->info('No hay datos de demo que borrar.');
            return self::SUCCESS;
        }

        if (!$force) {
            $this->newLine();
            $this->comment('Dry-run: nada borrado. Relanza con --force para borrar de verdad (irreversible: usa forceDelete, no papelera).');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($equipoIds, $encuentroIds, $microproyectoIds, $microretoIds, $empresaIds) {
            // Equipo no usa SoftDeletes — delete() ya es un borrado real, y arrastra en
            // cascada (FK a nivel de BD) a equipo_miembros/fases/tareas/reflexiones/prototipos.
            Equipo::whereIn('id', $equipoIds)->delete();

            Encuentro::withTrashed()->whereIn('id', $encuentroIds)->forceDelete();

            Microproyecto::withTrashed()->whereIn('id', $microproyectoIds)->forceDelete();

            // Los microretos deben borrarse ANTES que las empresas: microretos.empresa_id
            // es RESTRICT, no nullOnDelete — una empresa con algún reto vivo no se puede borrar.
            Microreto::withTrashed()->whereIn('id', $microretoIds)->forceDelete();

            DB::table('empresa_familia')->whereIn('empresa_id', $empresaIds)->delete();

            Empresa::withTrashed()->whereIn('id', $empresaIds)->forceDelete();
        });

        $this->newLine();
        $this->info('Borrado completado: ' . $empresaIds->count() . ' empresas, ' . $microretoIds->count() . ' retos, '
            . $microproyectoIds->count() . ' proyectos, ' . $encuentroIds->count() . ' encuentros, ' . $equipoIds->count() . ' equipos.');

        return self::SUCCESS;
    }
}
