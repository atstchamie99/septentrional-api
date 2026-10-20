<?php

declare(strict_types=1);

$envExamplePath = dirname(__DIR__) . '/.env.example';

if (! is_file($envExamplePath)) {
    fwrite(STDERR, ".env.example not found.\n");
    exit(1);
}

$contents = file_get_contents($envExamplePath);

if ($contents === false) {
    fwrite(STDERR, "Unable to read .env.example.\n");
    exit(1);
}

$violations = [];

if (preg_match('/^APP_DEBUG\s*=\s*true\b/im', $contents) === 1) {
    $violations[] = 'APP_DEBUG must remain false in .env.example.';
}

if (preg_match('/^LOG_LEVEL\s*=\s*debug\b/im', $contents) === 1) {
    $violations[] = 'LOG_LEVEL must not default to debug in .env.example.';
}

if ($violations !== []) {
    foreach ($violations as $violation) {
        fwrite(STDERR, $violation . PHP_EOL);
    }

    exit(1);
}

fwrite(STDOUT, ".env.example debug settings look safe.\n");
