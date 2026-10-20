<?php

return [
    'connection' => 'wordpress',
    'prefix' => env('WORDPRESS_TABLE_PREFIX', 'wp4e_'),
    'site_url' => env('WORDPRESS_SITE_URL', 'https://www.septentrional.tg'),
];
