<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Unit;

use BulkFlow\Notifications\ImportCompletedNotification;
use BulkFlow\Run\ImportRun;
use BulkFlow\Tests\TestCase;

final class ImportCompletedNotificationTest extends TestCase
{
    public function test_it_exposes_a_summary_for_database_or_mail_channels(): void
    {
        $run = new ImportRun(['id' => 'run-1', 'state' => 'completed_with_errors', 'processed_rows' => 10, 'successful_rows' => 8, 'failed_rows' => 2]);

        $payload = (new ImportCompletedNotification($run))->toArray(new \stdClass);

        self::assertSame('run-1', $payload['run_id']);
        self::assertSame(2, $payload['failed_rows']);
    }
}
