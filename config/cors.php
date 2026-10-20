<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'videos/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],

    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,http://localhost:8000,https://septentrional.tg,https://www.septentrional.tg,https://septentrional.com,https://admin.septentrional.com')),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Requested-With', 'X-CSRF-TOKEN', 'X-API-Key'],

    'exposed_headers' => [],

    'max_age' => 86400, // 24 heures

    'supports_credentials' => true,

];
