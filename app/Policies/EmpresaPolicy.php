<?php

namespace App\Policies;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EmpresaPolicy
{
    // Datos de ficha (nombre, CIF, contacto, dirección...) — PUT /empresas/{id}.
    // Superadmin: todas. Admin de centro: las de su centro. Docente: solo las FICTICIAS de
    // su centro (son inventadas: no hay datos sensibles que proteger). Plantillas del
    // catálogo: solo superadmin.
    public function update(User $user, Empresa $empresa): Response
    {
        if ($user->isSuperAdmin()) {
            return Response::allow();
        }
        if ($empresa->es_catalogo) {
            return Response::deny('Esta empresa es del catálogo de DuaLab y es de solo lectura. Usa «Usar en mi centro» para tener tu propia copia editable.');
        }
        if (!($user->isDocente() || $user->isAdmin()) || !$empresa->perteneceAlCentroDe($user)) {
            return Response::deny('No autorizado: esta empresa no pertenece a tu centro educativo.');
        }
        if ($user->isDocente() && !$empresa->es_simulada) {
            return Response::deny('Esta empresa es real y sus datos no pueden modificarse porque son sensibles. Ponte en contacto con DuaLab para cualquier cambio.');
        }
        return Response::allow();
    }

    // Diagnóstico (P1–P4) desde el Generador de Retos. Las empresas reales contienen
    // datos sensibles recogidos por DuaLab: solo superadmin (DuaLab) puede tocarlas.
    // Las ficticias las puede editar el docente/admin de su propio centro.
    public function actualizarDiagnostico(User $user, Empresa $empresa): Response
    {
        if ($user->isSuperAdmin()) {
            return Response::allow();
        }

        // Plantilla del catálogo DuaLab (T2): compartida por todos los centros, solo lectura.
        if ($empresa->es_catalogo) {
            return Response::deny('Esta empresa es del catálogo de DuaLab y es de solo lectura. Usa «Usar en mi centro» para tener tu propia copia editable.');
        }

        if (!($user->isDocente() || $user->isAdmin()) || !$empresa->perteneceAlCentroDe($user)) {
            return Response::deny('No autorizado: esta empresa no pertenece a tu centro educativo.');
        }

        return $empresa->es_simulada
            ? Response::allow()
            : Response::deny('Esta empresa es real y su diagnóstico no puede modificarse porque contiene datos sensibles. Ponte en contacto con DuaLab para cualquier cambio.');
    }
}
