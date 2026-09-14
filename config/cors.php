<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Explícito a propósito: con Sanctum en modo stateful (cookies de sesión),
    | supports_credentials=true + allowed_origins=['*'] permitiría a cualquier
    | origen leer JSON autenticado por cookie. allowed_origins debe listar
    | siempre dominios exactos — nunca '*' — y este archivo va en el mismo
    | commit/deploy que bootstrap/app.php (statefulApi()).
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173'))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
