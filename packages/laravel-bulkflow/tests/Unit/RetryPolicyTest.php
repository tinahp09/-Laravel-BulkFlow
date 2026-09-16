<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Unit;

use BulkFlow\Queue\RetryPolicy;
use BulkFlow\Tests\TestCase;

final class RetryPolicyTest extends TestCase
{
    public function test_it_reads_tries_and_backoff_from_package_config(): void
    {
        config()->set('bulkflow.queue.tries', 4);
        config()->set('bulkflow.queue.backoff', [2, 9]);

        self::assertEquals(new RetryPolicy(4, [2, 9]), RetryPolicy::fromConfig());
    }
}
