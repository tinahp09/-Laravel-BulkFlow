<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Integration;

use BulkFlow\Events\ImportProgressUpdated;
use BulkFlow\Progress\BroadcastProgressPublisher;
use BulkFlow\Progress\NullProgressPublisher;
use BulkFlow\Progress\ProgressSnapshot;
use BulkFlow\Run\ImportRun;
use BulkFlow\Tests\TestCase;
use Illuminate\Support\Facades\Event;

final class ProgressPublisherTest extends TestCase
{
    public function test_a_broadcast_publisher_emits_the_latest_revisioned_snapshot(): void
    {
        Event::fake([ImportProgressUpdated::class]);
        $run = new ImportRun([
            'id' => 'run-1',
            'state' => 'processing',
            'total_rows' => 100,
            'processed_rows' => 25,
            'successful_rows' => 24,
            'failed_rows' => 1,
            'revision' => 3,
        ]);

        (new BroadcastProgressPublisher)->publish(ProgressSnapshot::fromRun($run));

        Event::assertDispatched(ImportProgressUpdated::class, static fn (ImportProgressUpdated $event): bool => $event->broadcastWith()['revision'] === 3);
    }

    public function test_a_null_publisher_is_a_safe_no_op(): void
    {
        Event::fake([ImportProgressUpdated::class]);
        $run = new ImportRun(['id' => 'run-1', 'state' => 'processing', 'revision' => 1]);

        (new NullProgressPublisher)->publish(ProgressSnapshot::fromRun($run));

        Event::assertNotDispatched(ImportProgressUpdated::class);
    }
}
