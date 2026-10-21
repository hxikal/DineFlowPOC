<?php

require dirname(__DIR__).'/vendor/autoload.php';
$values = Dotenv\Dotenv::createArrayBacked(dirname(__DIR__), '.env.mysql-testing')->load();
foreach ($values as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}
if (($values['DB_DATABASE'] ?? '') !== 'dineflow_laravel_test' || ($values['DB_HOST'] ?? '') !== '127.0.0.1') {
    throw new RuntimeException('Unsafe MySQL test target.');
}
