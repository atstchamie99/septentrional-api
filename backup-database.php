<?php

use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Process\Process;

require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$directory = $argv[2];
$connection = $app->make('db')->connection();
$config = $connection->getConfig();
$driver = $config['driver'];
umask(0077);

if ($driver === 'sqlite') {
    $destination = $directory.'/database.sqlite';
    $connection->getPdo()->exec('VACUUM INTO '.$connection->getPdo()->quote($destination));
    exit(0);
}

if (! in_array($driver, ['mysql', 'mariadb'], true)) {
    throw new RuntimeException('Automatic backup supports MySQL/MariaDB and SQLite only.');
}

$quote = static fn ($value) => '"'.str_replace(
    ["\\", '"', "\n", "\r"],
    ["\\\\", '\\"', '\\n', '\\r'],
    (string) $value
).'"';
$credentials = tempnam($directory, '.mysql-');
$dump = $directory.'/database.sql';
try {
    $settings = "[client]\n";
    foreach (['host', 'port', 'username' => 'user', 'password'] as $key => $option) {
        $key = is_int($key) ? $option : $key;
        if (isset($config[$key])) {
            $settings .= $option.'='.$quote($config[$key])."\n";
        }
    }
    if (! empty($config['unix_socket'])) {
        $settings .= 'socket='.$quote($config['unix_socket'])."\n";
    }
    file_put_contents($credentials, $settings);
    chmod($credentials, 0600);
    $process = new Process([
        'mysqldump', '--defaults-extra-file='.$credentials,
        '--single-transaction', '--quick', '--no-tablespaces', '--hex-blob',
        '--result-file='.$dump,
        '--', $config['database'],
    ]);
    $process->setTimeout(600);
    $process->run();
    if (! $process->isSuccessful()) {
        throw new RuntimeException('mysqldump failed; deployment stopped before updating code.');
    }
} finally {
    if (is_string($credentials) && file_exists($credentials)) {
        unlink($credentials);
    }
}
