<?php

namespace Tests\Unit;

use App\Support\DatabaseSafety;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DatabaseSafetyTest extends TestCase
{
    public function test_allows_isolated_development_and_test_targets(): void
    {
        $guard = new DatabaseSafety;
        $guard->assertSafe(['driver' => 'mysql', 'host' => '127.0.0.1', 'database' => 'dineflow_laravel_dev'], 'local');
        $guard->assertSafe(['driver' => 'mysql', 'host' => '127.0.0.1', 'database' => 'dineflow_laravel_test'], 'testing');
        $guard->assertSafe(['driver' => 'sqlite', 'database' => ':memory:'], 'testing');
        $this->addToAssertionCount(3);
    }

    public static function unsafeTargets(): array
    {
        $safe = ['driver' => 'mysql', 'host' => '127.0.0.1', 'database' => 'dineflow_laravel_dev'];

        return [
            'legacy' => [array_replace($safe, ['database' => 'dineflow']), 'local'],
            'test pointing at dev' => [$safe, 'testing'],
            'remote host' => [array_replace($safe, ['host' => 'example.invalid']), 'local'],
            'url override' => [array_replace($safe, ['url' => 'mysql://example.invalid/source']), 'local'],
            'production' => [$safe, 'production'],
            'sqlite file' => [['driver' => 'sqlite', 'database' => 'source.sqlite'], 'testing'],
            'read override' => [array_replace($safe, ['read' => ['database' => 'dineflow']]), 'local'],
            'write override' => [array_replace($safe, ['write' => ['database' => 'dineflow']]), 'local'],
        ];
    }

    #[DataProvider('unsafeTargets')]
    public function test_rejects_unsafe_targets(array $configuration, string $environment): void
    {
        $this->expectException(LogicException::class);
        (new DatabaseSafety)->assertSafe($configuration, $environment);
    }
}
