<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Unit;

use BulkFlow\Queue\ProcessImport;
use BulkFlow\Queue\QueuedImportDefinition;
use BulkFlow\Queue\RetryPolicy;
use BulkFlow\Tests\Fixtures\Models\User;
use BulkFlow\Tests\TestCase;

final class RetryPolicyTest extends TestCase
{
    public function test_it_reads_tries_and_backoff_from_package_config(): void
    {
        config()->set('bulkflow.queue.tries', 4);
        config()->set('bulkflow.queue.backoff', [2, 9]);
        config()->set('bulkflow.queue.timeout', 45);

        self::assertEquals(new RetryPolicy(4, [2, 9], 45), RetryPolicy::fromConfig());
    }

    public function test_it_applies_the_configured_timeout_to_queue_jobs(): void
    {
        config()->set('bulkflow.queue.timeout', 45);

        $definition = new QueuedImportDefinition(
            User::class,
            '/tmp/users.csv',
            ['name' => 'name'],
            [],
            ['email'],
            1_000,
            'continue',
            null,
        );

        self::assertSame(45, (new ProcessImport('run-id', $definition))->timeout);
    }
}
