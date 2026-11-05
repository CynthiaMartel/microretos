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
            'version' => '0.1-ejemplo',
            'titulo'  => 'Aviso legal y condiciones de participación',
            'texto'   => "TEXTO DE EJEMPLO — pendiente de revisión legal.\n\n"
                . "La empresa participa voluntariamente en el programa de retos de DuaLab. La información "
                . "facilitada en esta entrevista se utilizará para diseñar retos formativos para alumnado "
                . "de Formación Profesional. La empresa puede solicitar en cualquier momento que se dejen "
                . "de usar sus datos.",
        ],
        'info_rgpd' => [
            'version' => '0.1-ejemplo',
            'titulo'  => 'Información sobre protección de datos',
            'texto'   => "TEXTO DE EJEMPLO — pendiente de revisión legal.\n\n"
                . "Responsable del tratamiento: [RAZÓN SOCIAL Y CIF DEL RESPONSABLE]. Finalidad: gestionar la "
                . "colaboración de la empresa en el programa y diseñar retos formativos. Base jurídica: "
                . "consentimiento. Conservación: mientras dure la colaboración. Derechos: acceso, "
                . "rectificación, supresión, oposición, limitación y portabilidad en [EMAIL DE CONTACTO DPO]. "
                . "Puede presentar una reclamación ante la Agencia Española de Protección de Datos.",
        ],
    ],

    // Una casilla por cesionaria, sin marcar por defecto; son opcionales (se registra el sí y el no).
    'cesiones' => [
        'cesion_1' => [
            'version'    => '0.1-ejemplo',
            'cesionaria' => 'Vía Óptima SL',
            'texto'      => "TEXTO DE EJEMPLO — pendiente de revisión legal.\n\n"
                . "Autorizo la cesión de los datos de la empresa y de la persona de contacto a [EMPRESA "
                . "CESIONARIA 1] con la finalidad de [FINALIDAD DE LA CESIÓN].",
        ],
        'cesion_2' => [
            'version'    => '0.1-ejemplo',
            'cesionaria' => 'Inicia Lab SL',
            'texto'      => "TEXTO DE EJEMPLO — pendiente de revisión legal.\n\n"
                . "Autorizo la cesión de los datos de la empresa y de la persona de contacto a [EMPRESA "
                . "CESIONARIA 2] con la finalidad de [FINALIDAD DE LA CESIÓN].",
        ],
    ],

];
