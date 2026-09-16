<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Feature;

use BulkFlow\BulkFlowManager;
use BulkFlow\Tests\TestCase;

final class ServiceProviderTest extends TestCase
{
    public function test_it_registers_the_manager_and_default_configuration(): void
    {
        self::assertInstanceOf(BulkFlowManager::class, $this->app->make(BulkFlowManager::class));
        self::assertSame(1_000, config('bulkflow.chunk_size'));
    }
}
