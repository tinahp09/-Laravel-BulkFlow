<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Feature;

use BulkFlow\BulkFlowManager;
use BulkFlow\Queue\CsvChunkRange;
use BulkFlow\Queue\ProcessImport;
use BulkFlow\Queue\ProcessImportChunk;
use BulkFlow\Tests\Fixtures\Models\User;
use BulkFlow\Tests\TestCase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;

final class QueuedImportTest extends TestCase
{
    public function test_it_queues_a_run_then_dispatches_a_batch_of_chunk_jobs(): void
    {
        Queue::fake();
        $path = tempnam(sys_get_temp_dir(), 'bulkflow-queued-').'.csv';
        file_put_contents($path, "name,email\nNeda,neda@example.test\nAda,ada@example.test\nLin,lin@example.test\n");

        $run = $this->app->make(BulkFlowManager::class)
            ->import(User::class)
            ->from($path)
            ->map(['name' => 'name', 'email' => 'email'])
            ->upsertBy(['email'])
            ->chunkSize(2)
            ->queue();

        self::assertSame('queued', $run->state);
        Queue::assertPushed(ProcessImport::class, static fn (ProcessImport $job): bool => $job->definition->source === $path && $job->runId === $run->id);

        /** @var ProcessImport $job */
        $job = Queue::pushed(ProcessImport::class)->first();
        Bus::fake();
        $job->handle($this->app->make(BulkFlowManager::class));

        Bus::assertBatched(static fn ($batch): bool => $batch->name === 'BulkFlow import '.$run->id
            && count($batch->jobs) === 2
            && collect($batch->jobs)->every(static fn (object $job): bool => $job instanceof ProcessImportChunk && $job->rows instanceof CsvChunkRange));
    }
}
