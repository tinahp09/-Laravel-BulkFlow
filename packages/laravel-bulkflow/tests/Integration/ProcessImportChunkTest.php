<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Integration;

use BulkFlow\Queue\CsvChunkPlanner;
use BulkFlow\Queue\ProcessImportChunk;
use BulkFlow\Queue\QueuedImportDefinition;
use BulkFlow\Run\DatabaseRunRepository;
use BulkFlow\Tests\Fixtures\Models\User;
use BulkFlow\Tests\TestCase;

final class ProcessImportChunkTest extends TestCase
{
    public function test_it_persists_rows_and_records_validation_failures_for_its_assigned_chunk(): void
    {
        $definition = new QueuedImportDefinition(
            modelClass: User::class,
            source: '/unused.csv',
            mapping: ['name' => 'name', 'email' => 'email'],
            rules: ['email' => ['required', 'email']],
            upsertKeys: ['email'],
            chunkSize: 2,
            errorPolicy: 'continue',
            stopOnErrorCount: null,
        );
        $runs = new DatabaseRunRepository;
        $run = $runs->createQueued($definition->jsonSerialize());
        $runs->start($run->id);

        (new ProcessImportChunk($run->id, $definition, [
            ['number' => 2, 'values' => ['name' => 'Ada', 'email' => 'ada@example.test']],
            ['number' => 3, 'values' => ['name' => 'Bad', 'email' => 'not-an-email']],
        ]))->handle();

        self::assertDatabaseHas('users', ['email' => 'ada@example.test']);
        self::assertDatabaseHas('bulkflow_row_failures', ['run_id' => $run->id, 'row_number' => 3, 'type' => 'validation']);
        $run->refresh();
        self::assertSame(2, $run->processed_rows);
        self::assertSame(1, $run->successful_rows);
        self::assertSame(1, $run->failed_rows);
    }

    public function test_reprocessing_an_upsert_chunk_does_not_duplicate_models(): void
    {
        $definition = new QueuedImportDefinition(
            modelClass: User::class,
            source: '/unused.csv',
            mapping: ['name' => 'name', 'email' => 'email'],
            rules: [],
            upsertKeys: ['email'],
            chunkSize: 1,
            errorPolicy: 'continue',
            stopOnErrorCount: null,
        );
        $runs = new DatabaseRunRepository;
        $run = $runs->createQueued($definition->jsonSerialize());
        $runs->start($run->id);
        $job = new ProcessImportChunk($run->id, $definition, [
            ['number' => 2, 'values' => ['name' => 'Ada', 'email' => 'ada@example.test']],
        ]);

        $job->handle();
        $job->handle();

        self::assertSame(1, User::query()->where('email', 'ada@example.test')->count());
    }

    public function test_csv_range_chunk_reads_only_its_assigned_file_range(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bulkflow-range-').'.csv';
        file_put_contents($path, "name,email\nAda,ada@example.test\nLin,lin@example.test\nNeda,neda@example.test\n");
        $definition = new QueuedImportDefinition(User::class, $path, ['name' => 'name', 'email' => 'email'], [], ['email'], 2, 'continue', null);
        $runs = new DatabaseRunRepository;
        $run = $runs->createQueued($definition->jsonSerialize());
        $runs->start($run->id);
        $ranges = (new CsvChunkPlanner)->plan($path, 2);

        (new ProcessImportChunk($run->id, $definition, $ranges[1]))->handle();

        self::assertDatabaseMissing('users', ['email' => 'ada@example.test']);
        self::assertDatabaseHas('users', ['email' => 'neda@example.test']);
        $run->refresh();
        self::assertSame(1, $run->successful_rows);
    }
}
