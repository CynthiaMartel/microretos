<?php

namespace App\Policies;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EmpresaPolicy
{
    // Datos de ficha (nombre, CIF, sector, contacto, familias...) — PUT /empresas/{id}.
    // Solo superadmin (DuaLab): los retos guardan su texto y una copia del diagnóstico, pero
    // la ficha la leen en vivo (nombre, sector, familia, real/ficticia), así que cambiarla
    // alteraría cómo se ven los retos ya generados con esa empresa en todos sus centros.
    public function update(User $user, Empresa $empresa): Response
    {
        return $user->isSuperAdmin()
            ? Response::allow()
            : Response::deny('Solo DUALAB puede modificar los datos de una empresa: los retos ya generados con ella los muestran. Ponte en contacto con DUALAB para cualquier cambio.');
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
            return Response::deny('Esta empresa es del catálogo de DUALAB y es de solo lectura. Usa «Usar en mi centro» para tener tu propia copia editable.');
        }

        if (!($user->isDocente() || $user->isAdmin()) || !$empresa->perteneceAlCentroDe($user)) {
            return Response::deny('No autorizado: esta empresa no pertenece a tu centro educativo.');
        }

        return $empresa->es_simulada
            ? Response::allow()
            : Response::deny('Esta empresa es real y su diagnóstico no puede modificarse porque contiene datos sensibles. Ponte en contacto con DUALAB para cualquier cambio.');
    }

    // App comercial (/comercial/*): el comercial solo ve las empresas que ha captado él.
    // Superadmin, todas las captadas.
    public function verCaptacion(User $user, Empresa $empresa): Response
    {
        if ($empresa->estado_captacion === null) {
            return Response::denyAsNotFound();
        }

        return $user->isSuperAdmin() || ($user->isComercial() && $empresa->creada_por_user_id === $user->id)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    // Datos, diagnóstico, retos y firma: solo mientras la entrevista no está firmada. Lo
    // firmado no puede cambiar después (la firma guarda una copia y su huella).
    public function editarCaptacion(User $user, Empresa $empresa): Response
    {
        $ver = $this->verCaptacion($user, $empresa);
        if ($ver->denied()) {
            return $ver;
        }

        return $empresa->esBorradorCaptacion()
            ? Response::allow()
            : Response::deny('La entrevista ya está firmada: sus datos no se pueden cambiar. Ponte en contacto con DUALAB para cualquier corrección.');
    }
}
