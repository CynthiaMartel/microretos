<?php

/*
|--------------------------------------------------------------------------
| Captación de empresas por comerciales (app comercial.dualab.es)
|--------------------------------------------------------------------------
|
| Textos legales que el empresario acepta y firma en la tablet. Viven aquí (versionados en
| git) y no en la BD: cambiar un texto exige subir su 'version'. Cada firma guarda una copia
| literal del texto aceptado y su hash SHA-256 (empresa_firmas.snapshot), así que editar este
| archivo nunca altera lo que ya se firmó.
|
| ⚠ TEXTOS DE EJEMPLO. Antes de usarlos con empresas reales los tiene que redactar o revisar
| el DPO / asesoría legal, y hay que poner CAPTACION_TEXTOS_REVISADOS=true en el .env.
| Mientras no se haga, en producción no se puede firmar (CaptacionEmpresaService::firmar).
|
*/

return [

    'textos_revisados' => (bool) env('CAPTACION_TEXTOS_REVISADOS', false),

    // Obligatorios: sin aceptarlos no se puede firmar.
    'textos' => [
        'aviso_legal' => [
            'version' => '0.3-borrador',
            'titulo'  => 'Aviso legal y condiciones de participación',
            'texto'   => "1. Participación. La empresa participa de forma voluntaria y gratuita en el programa "
                . "de aprendizaje por retos para prácticas de empresa de DuaLab y en las acciones de "
                . "formación de IniciaLab. La participación no crea relación laboral ni obligación "
                . "económica, y la empresa puede dejar el programa en cualquier momento.\n\n"
                . "2. Uso de la información. Los datos facilitados en esta entrevista (datos de la empresa, "
                . "actividad, necesidades y retos planteados) se usarán para diseñar retos formativos que "
                . "se compartirán con los centros educativos y el alumnado de Formación Profesional que "
                . "participan en el programa. La empresa declara que la información es veraz y que no "
                . "incluye secretos empresariales ni datos de terceros que no esté autorizada a facilitar.\n\n"
                . "3. Quién trata los datos. Grupo Óptima es la marca comercial bajo la que operan Vía "
                . "Óptima SL [CIF] e Inicia Lab SL [CIF]; no es una sociedad. La responsable de los datos "
                . "de este programa es [SOCIEDAD TITULAR DE DUALAB, CIF Y DOMICILIO], que los comunica a "
                . "Inicia Lab SL solo para gestionar la parte de formación de IniciaLab. El uso de los "
                . "datos para proponerle futuros proyectos se recoge aparte, en una casilla opcional.\n\n"
                . "4. Protección de datos. El tratamiento de los datos personales se rige por el Reglamento "
                . "(UE) 2016/679 (RGPD) y la Ley Orgánica 3/2018 (LOPDGDD). Encontrará el detalle del "
                . "tratamiento y cómo ejercer sus derechos en la «Información sobre protección de datos».\n\n"
                . "5. Legislación aplicable. Estas condiciones se rigen por la legislación española.",
        ],
        'info_rgpd' => [
            'version' => '0.3-borrador',
            'titulo'  => 'Información sobre protección de datos',
            'texto'   => "Responsable: [SOCIEDAD TITULAR DE DUALAB, CIF Y DOMICILIO], que opera bajo la marca "
                . "Grupo Óptima.\n\n"
                . "Finalidades: (1) gestionar la participación de la empresa en el programa y diseñar "
                . "retos formativos; (2) solo si se marca la casilla opcional, proponerle futuros "
                . "proyectos y servicios de Vía Óptima.\n\n"
                . "Base jurídica: (1) la relación de colaboración que se acepta con este documento; "
                . "(2) el consentimiento, que puede retirarse en cualquier momento.\n\n"
                . "Destinatarios: Inicia Lab SL [CIF], para la formación de IniciaLab; los centros "
                . "educativos participantes, solo los datos de la empresa y del reto; y, si se marca la "
                . "casilla opcional, Vía Óptima SL [CIF]. No se transfieren datos fuera de la UE.\n\n"
                . "Conservación: mientras dure la colaboración y, después, durante los plazos legales. "
                . "La firma y los consentimientos se guardan como prueba.\n\n"
                . "Derechos: acceso, rectificación, supresión, oposición, limitación y portabilidad en "
                . "[EMAIL DE CONTACTO]. Puede reclamar ante la Agencia Española de Protección de Datos "
                . "(www.aepd.es).",
        ],
    ],

    // Una casilla por cesionaria, sin marcar por defecto; son opcionales (se registra el sí y el no).
    'cesiones' => [
        // Clave nueva a propósito: las firmas anteriores guardan cesion_1 (Vía Óptima SL) y
        // cesion_2 (Inicia Lab SL) con su texto en el snapshot.
        'grupo_optima' => [
            'version'    => '0.3-borrador',
            'cesionaria' => 'Grupo Óptima (Vía Óptima SL e Inicia Lab SL)',
            'texto'      => "Autorizo que Vía Óptima SL [CIF] e Inicia Lab SL [CIF], que operan bajo la marca "
                . "Grupo Óptima, traten los datos de la empresa y de la persona de contacto para "
                . "proponernos futuros proyectos y servicios de Vía Óptima relacionados con "
                . "nuestra actividad, también por email o teléfono. Es voluntario: si no lo marco, la "
                . "participación en el programa no cambia. Puedo retirar esta autorización en cualquier "
                . "momento escribiendo a [EMAIL DE CONTACTO].",
        ],
    ],

    // Centros educativos (entrevista a centros de la app comercial). Sin firma, como el
    // formulario de centros de la web: aceptan la información de protección de datos
    // (obligatoria) y deciden las cesiones de 'cesiones' (opcionales, sin marcar).
    'centros' => [
        'textos' => [
            'info_rgpd_centro' => [
                // Versión nueva al cambiar el texto: las aceptaciones guardan la versión que se mostró
                'version' => '0.2-borrador',
                'titulo'  => 'Información sobre protección de datos',
                'texto'   => "Responsable: Grupo Óptima [CIF], que trata estos datos con la finalidad de atender "
                    . "la solicitud del centro (alta en DuaLab, información o demo) y, si el centro se une, "
                    . "gestionar su participación en el programa.\n\n"
                    . "Base jurídica: la solicitud del propio centro y, después, la relación de colaboración.\n\n"
                    . "Datos: los del centro y los de la persona de contacto (nombre, cargo, email y teléfono).\n\n"
                    . "Destinatarios: Inicia Lab SL [CIF], para la formación de IniciaLab. No se transfieren "
                    . "datos fuera de la UE.\n\n"
                    . "Conservación: mientras se atiende la solicitud y dure la colaboración y, después, "
                    . "durante los plazos legales.\n\n"
                    . "Derechos: acceso, rectificación, supresión, oposición, limitación y portabilidad en "
                    . "[EMAIL DE CONTACTO]. Puede reclamar ante la Agencia Española de Protección de Datos "
                    . "(www.aepd.es).",
            ],
        ],
    ],

    // Formularios del frontoffice (dualab.es/contacto) que llegan como captaciones: el frontoffice
    // los reenvía por su webhook (Gestión web → Integraciones) firmados con HMAC-SHA256 con este
    // secreto, el mismo que se configure allí. Sin secreto, el endpoint no acepta nada.
    'frontoffice_webhook_secreto' => env('FRONTOFFICE_WEBHOOK_SECRETO'),
];
