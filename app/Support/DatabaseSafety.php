<?php

namespace App\Support;

use LogicException;

final class DatabaseSafety
{
    public function assertSafe(array $connection, string $environment): void
    {
        if (! empty($connection['url']) || ! empty($connection['read']) || ! empty($connection['write'])) {
            throw new LogicException('DB_URL is disabled: configure the isolated target explicitly.');
        }
        $driver = $connection['driver'] ?? '';
        $database = $connection['database'] ?? '';
        if ($environment === 'testing' && $driver === 'sqlite' && $database === ':memory:') {
            return;
        }
        $expected = $environment === 'testing' ? 'dineflow_laravel_test' : 'dineflow_laravel_dev';
        if (! in_array($environment, ['local', 'testing'], true)
            || $driver !== 'mysql' || $database !== $expected
            || ! in_array($connection['host'] ?? '', ['127.0.0.1', 'localhost', '::1'], true)
            || ! empty($connection['unix_socket'])) {
            throw new LogicException('Database safety guard: only the isolated local development/test target is allowed.');
        }
    }
}
