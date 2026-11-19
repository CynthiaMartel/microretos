<?php

/*
|--------------------------------------------------------------------------
| Solicitudes de retos de docentes (SolicitudRetoService)
|--------------------------------------------------------------------------
|
| DuaLab envía a un docente un enlace (app DuaLab, /solicitud-reto/{token}, sin login) para que
| cuente qué quiere trabajar; DuaLab genera los retos y, cuando decide, le avisa con el mismo
| enlace, que entonces muestra los retos en solo lectura.
|
| ⚠ El texto de protección de datos es de ejemplo, como los de config/captacion.php: lo tiene
| que revisar el DPO / asesoría legal (y subir su 'version') antes de usarlo fuera de la beta.
|
*/

return [

    // El enlace se abre en la app DuaLab (frontend-microretos/src)
    'url_app' => env('FRONTEND_URL', 'http://localhost:5173'),
    // Aviso interno de solicitud respondida: enlaza a su ficha en DuaLab Central (Primer contacto)
    'url_central' => env('CENTRAL_URL', env('APP_ENV') === 'production' ? 'https://central.dualab.es' : 'http://localhost:5179'),

    // Las respuestas de los docentes van a info@ (Reply-To) y se atienden en Central → Primer contacto →
    // «Respuestas de solicitudes»: la referencia del asunto (SolicitudReto::referencia) las asocia a su solicitud
    'responder_a' => [
        'email'  => env('SOLICITUDES_RETO_RESPONDER_A', env('MAIL_FROM_ADDRESS', 'info@dualab.es')),
        'nombre' => 'DuaLab',
    ],

    // Centro con el que se abre el generador cuando la solicitud no tiene empresa ni centro dado de alta:
    // el de la beta, con las empresas de DuaLab (ver memoria «centros y datos beta»)
    'centro_generador' => env('SOLICITUDES_RETO_CENTRO_GENERADOR', 'DuaLab'),

    // Validez del enlace para rellenar el formulario
    'dias_validez' => 21,
    // Al avisar al docente de que sus retos están listos, el enlace se renueva para verlos
    'dias_validez_retos' => 90,

    'privacidad' => [
        'version' => '0.1-borrador',
        'titulo'  => 'Información sobre protección de datos',
        'texto'   => "Responsable: [SOCIEDAD TITULAR DE DUALAB, CIF Y DOMICILIO], que opera bajo la marca "
            . "Grupo Óptima.\n\n"
            . "Finalidad: diseñar retos formativos para tu alumnado a partir de lo que nos cuentes "
            . "(módulos, resultados de aprendizaje y la sesión en el aula) y ponerte en contacto "
            . "contigo para enviártelos. No escribas datos personales de tu alumnado.\n\n"
            . "Datos: tu nombre, tu email y el centro educativo.\n\n"
            . "Base jurídica: tu interés en recibir los retos, que has pedido a nuestro equipo.\n\n"
            . "Conservación: mientras preparamos los retos y, si tu centro se une a DuaLab, mientras "
            . "dure la colaboración. Si no sigue adelante, la solicitud se descarta.\n\n"
            . "Derechos: acceso, rectificación, supresión, oposición, limitación y portabilidad en "
            . "[EMAIL DE CONTACTO]. Puedes reclamar ante la Agencia Española de Protección de Datos "
            . "(www.aepd.es).",
    ],

];
