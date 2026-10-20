<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
Dotenv\Dotenv::createImmutable($root)->load();

if (($_ENV['APP_ENV'] ?? '') === 'production' || ($_ENV['DB_DATABASE'] ?? '') !== 'SeptentrionalTest') {
    fwrite(STDERR, "Les tests d’intégration sont réservés à la base locale SeptentrionalTest.\n");
    exit(1);
}
$environment = ['SEPTENTRIONAL_WORDPRESS_INTEGRATION' => '1'];
foreach (['DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'] as $key) {
    $environment[$key] = $_ENV[$key] ?? '';
}
$environment['DB_URL'] = '';
$process = new Process([PHP_BINARY, 'vendor/bin/phpunit', 'tests/Feature/WordpressImportIntegrationTest.php'], $root, $environment);
$process->setTimeout(900);
$process->run(static function (string $type, string $buffer): void {
    echo $buffer;
});
exit($process->getExitCode() ?? 1);
