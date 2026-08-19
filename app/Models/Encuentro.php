<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Encuentro extends Model
{
    use SoftDeletes;
    protected $table = 'encuentros';

    protected $fillable = [
        'user_id',
        'microproyecto_id',
        'fecha',
        'fecha_fin',
        'centro_educativo',
        'centro_educativo_id',
        'ciclo_formativo',
        'curso',
        'grupo',
        'num_alumnos',
        'notas',
        'num_equipos',
        'alumnados',
        'codigo_clase',
        'codigo_ia',
    ];

    protected $casts = [
        'fecha'            => 'date:Y-m-d',
        'fecha_fin'        => 'date:Y-m-d',
        'num_alumnos'      => 'integer',
        'num_equipos'      => 'integer',
        'microproyecto_id' => 'integer',
        'alumnados'        => 'array',
    ];

    public function docente()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function microproyecto()
    {
        return $this->belongsTo(Microproyecto::class);
    }

    public function equipos()
    {
        return $this->hasMany(\App\Models\Equipo::class);
    }

    // Docentes con los que el propietario ha compartido explícitamente este encuentro.
    // El pivot 'puede_editar' decide si el colaborador solo puede ver o también mutar.
    public function colaboradores()
    {
        return $this->belongsToMany(\App\Models\User::class, 'encuentro_colaboradores')
            ->withPivot('puede_editar')
            ->withTimestamps();
    }

    // Encuentros que el usuario puede VER: los suyos, o aquellos donde es colaborador
    // (con cualquier nivel de permiso). Admin ve todo su centro; superadmin, todo.
    public function scopeVisiblesPara($query, \App\Models\User $user)
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        if ($user->isAdmin() && $user->centro_educativo_id) {
            return $query->where('centro_educativo_id', $user->centro_educativo_id);
        }

        return $query->where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->orWhereHas('colaboradores', fn ($cq) => $cq->where('user_id', $user->id));
        });
    }

    // Encuentros que el usuario puede MUTAR: los suyos, o aquellos donde es colaborador
    // con 'puede_editar' explícito. Un colaborador de solo lectura no pasa este scope.
    public function scopeEditablesPara($query, \App\Models\User $user)
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        if ($user->isAdmin() && $user->centro_educativo_id) {
            return $query->where('centro_educativo_id', $user->centro_educativo_id);
        }

        return $query->where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->orWhereHas('colaboradores', fn ($cq) => $cq->where('user_id', $user->id)->where('puede_editar', true));
        });
    }
}
