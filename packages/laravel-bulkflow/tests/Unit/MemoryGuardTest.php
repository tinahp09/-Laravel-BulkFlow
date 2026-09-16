<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Unit;

use BulkFlow\Support\ImportInfrastructureFailure;
use BulkFlow\Support\MemoryGuard;
use BulkFlow\Tests\TestCase;

final class MemoryGuardTest extends TestCase
{
    public function test_it_throws_before_the_configured_memory_limit_is_exhausted(): void
    {
        $guard = new MemoryGuard(100, 0.80, static fn (): int => 80);

        $this->expectException(ImportInfrastructureFailure::class);
        $guard->check();
    }

    public function test_unlimited_memory_does_not_trip_the_guard(): void
    {
        (new MemoryGuard(null, 0.80, static fn (): int => PHP_INT_MAX))->check();
        self::assertTrue(true);
    }
}
