<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use App\Models\MicroproyectoRecurso;

class Microproyecto extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'uuid', 'microreto_id', 'empresa_id', 'centro_id', 'familia_id', 'ciclo_id',
        'titulo', 'curso',
        'datos_empresa', 'datos_centro', 'equipo', 'modulos_seleccionados', 'ra_ce',
        'fundamentacion', 'diseno_reto', 'diseno_microproyecto', 'resumen',
        'objetivos', 'kpis', 'validacion_empresa',
        'paso_actual', 'estado', 'token_empresa', 'empresa_validado',
        'empresa_no_valida_aun', 'enviado_a_empresa_mail', 'docente_validado',
    ];

    protected $casts = [
        'datos_empresa'          => 'array',
        'datos_centro'           => 'array',
        'equipo'                 => 'array',
        'modulos_seleccionados'  => 'array',
        'ra_ce'                  => 'string',
        'fundamentacion'         => 'array',
        'diseno_reto'            => 'array',
        'diseno_microproyecto'   => 'array',
        'resumen'                => 'array',
        'objetivos'              => 'array',
        'kpis'                   => 'array',
        'validacion_empresa'     => 'array',
        'empresa_validado'        => 'boolean',
        'empresa_no_valida_aun'   => 'boolean',
        'enviado_a_empresa_mail'  => 'boolean',
        'docente_validado'        => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Microproyecto $mp) {
            if (empty($mp->uuid)) {
                $mp->uuid = (string) Str::uuid();
            }
            if (empty($mp->token_empresa)) {
                $mp->token_empresa = Str::random(40);
            }
        });
    }

    public function recursos()
    {
        return $this->hasMany(MicroproyectoRecurso::class)->orderBy('created_at');
    }

    public function microreto()
    {
        return $this->belongsTo(Microreto::class);
    }

    public function sesiones()
    {
        return $this->hasMany(Sesion::class);
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function centroEducativo()
    {
        return $this->belongsTo(CentroEducativo::class, 'centro_id');
    }

    public function familia()
    {
        return $this->belongsTo(Familia::class);
    }

    public function cicloFormativo()
    {
        return $this->belongsTo(CicloFormativo::class, 'ciclo_id');
    }

    public function equipos()
    {
        return $this->hasMany(Equipo::class)->orderBy('id');
    }
}
