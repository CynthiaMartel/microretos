<?php

// API firmada del backend de la web (FRONTOFFICE/dualab_proyecto) que usa DuaLab Central en su
// espacio «Primer contacto»: la bandeja de formularios se ve en Central, pero los datos y el correo
// siguen en la web (docs/MAPA_DOMINIOS.md: en producción es info.dualab.es/api-backend/public).
return [
    // Sin barra final. En local, desde el contenedor de Sail: http://host.docker.internal:8001
    'api_url' => rtrim((string) env('FRONTOFFICE_API_URL', ''), '/'),

    // Mismo valor que CENTRAL_API_SECRETO en el .env de la web (mínimo 32 caracteres)
    'central_secreto' => env('FRONTOFFICE_CENTRAL_SECRETO'),

    'timeout' => (int) env('FRONTOFFICE_API_TIMEOUT', 8),
];
