<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Integration;

use BulkFlow\BulkFlowManager;
use BulkFlow\Events\ImportProgressUpdated;
use BulkFlow\Run\ImportRun;
use BulkFlow\Tests\Fixtures\Models\User;
use BulkFlow\Tests\TestCase;
use Illuminate\Support\Facades\Event;

final class ProgressEventTest extends TestCase
{
    public function test_it_emits_progress_when_a_row_is_processed(): void
    {
        Event::fake([ImportProgressUpdated::class]);
        $path = tempnam(sys_get_temp_dir(), 'bulkflow-progress-').'.csv';
        file_put_contents($path, "name,email\nNeda,neda@example.test\n");

        $this->app->make(BulkFlowManager::class)
            ->import(User::class)->from($path)->map(['name' => 'name', 'email' => 'email'])->upsertBy(['email'])->run();

        Event::assertDispatched(ImportProgressUpdated::class);
    }

    public function test_it_emits_a_terminal_snapshot_after_a_sync_import_completes(): void
    {
        Event::fake([ImportProgressUpdated::class]);
        $path = tempnam(sys_get_temp_dir(), 'bulkflow-progress-complete-').'.csv';
        file_put_contents($path, "name,email\nNeda,neda@example.test\n");

        $this->app->make(BulkFlowManager::class)
            ->import(User::class)->from($path)->map(['name' => 'name', 'email' => 'email'])->upsertBy(['email'])->run();

        Event::assertDispatched(ImportProgressUpdated::class, static fn (ImportProgressUpdated $event): bool => $event->state === 'completed'
            && $event->processedRows === 1
            && $event->totalRows === 1
            && $event->revision === 3);
    }

    public function test_it_uses_a_stable_broadcast_name_and_full_run_snapshot(): void
    {
        $run = new ImportRun([
            'id' => 'run-1',
            'state' => 'processing',
            'total_rows' => 100,
            'processed_rows' => 25,
            'successful_rows' => 24,
            'failed_rows' => 1,
            'revision' => 3,
        ]);
        $event = ImportProgressUpdated::fromRun($run);

        self::assertSame('bulkflow.progress.updated', $event->broadcastAs());
        self::assertSame([
            'id' => 'run-1',
            'state' => 'processing',
            'total_rows' => 100,
            'processed_rows' => 25,
            'successful_rows' => 24,
            'failed_rows' => 1,
            'revision' => 3,
        ], $event->broadcastWith());
    }
}
