<?php

$proxies = array_map(
    'trim',
    explode(',', (string) env('TRUSTED_PROXIES', '127.0.0.1,::1')),
);

return [
    // Illuminate\Http\Middleware\TrustProxies lit cette configuration au
    // traitement de la requête. La valeur est donc conservée par config:cache.
    // Le joker est volontairement rejeté : l'IP alimente les limites de débit
    // et l'unicité quotidienne des écoutes.
    'proxies' => array_values(array_filter(
        $proxies,
        static fn (string $proxy): bool => $proxy !== '' && ! in_array($proxy, ['*', '**'], true),
    )),
];
