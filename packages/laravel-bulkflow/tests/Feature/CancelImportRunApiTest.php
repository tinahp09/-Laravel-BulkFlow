<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Feature;

use BulkFlow\BulkFlowManager;
use BulkFlow\Queue\ProcessImport;
use BulkFlow\Queue\QueuedImportDefinition;
use BulkFlow\Run\DatabaseRunRepository;
use BulkFlow\Run\ImportRun;
use BulkFlow\Tests\Fixtures\Models\User;
use BulkFlow\Tests\TestCase;
use Illuminate\Support\Facades\Bus;

final class CancelImportRunApiTest extends TestCase
{
    public function test_it_cancels_the_queued_batch_and_marks_its_run_as_cancelled(): void
    {
        Bus::fake();
        $batch = Bus::dispatchFakeBatch('BulkFlow import');
        $run = (new DatabaseRunRepository)->createQueued([]);
        ImportRun::query()->whereKey($run->id)->update(['batch_id' => $batch->id]);

        $this->postJson('/bulkflow/imports/'.$run->id.'/cancel')
            ->assertOk()
            ->assertJsonPath('id', $run->id)
            ->assertJsonPath('state', 'cancelled')
            ->assertJsonPath('processed_rows', 0)
            ->assertJsonPath('total_rows', 0);

        self::assertTrue(Bus::findBatch($batch->id)->cancelled());
        $this->assertDatabaseHas('bulkflow_import_runs', ['id' => $run->id, 'state' => 'cancelled']);
    }

    public function test_a_cancelled_run_cannot_be_completed_later_by_a_racing_job(): void
    {
        $repository = new DatabaseRunRepository;
        $run = $repository->createQueued([]);

        $repository->cancel($run->id);
        $repository->complete($run->id);

        $this->assertDatabaseHas('bulkflow_import_runs', ['id' => $run->id, 'state' => 'cancelled']);
    }

    public function test_a_worker_does_not_start_an_import_that_was_cancelled_while_queued(): void
    {
        $repository = new DatabaseRunRepository;
        $definition = new QueuedImportDefinition(User::class, '/missing.csv', [], [], [], 1, 'continue', null);
        $run = $repository->createQueued($definition->jsonSerialize());
        $repository->cancel($run->id);

        (new ProcessImport($run->id, $definition))->handle($this->app->make(BulkFlowManager::class));

        $this->assertDatabaseHas('bulkflow_import_runs', ['id' => $run->id, 'state' => 'cancelled']);
    }
}
