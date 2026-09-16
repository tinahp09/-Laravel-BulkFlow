<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Integration;

use BulkFlow\BulkFlowManager;
use BulkFlow\Queue\ProcessImport;
use BulkFlow\Queue\QueuedImportDefinition;
use BulkFlow\Run\DatabaseRunRepository;
use BulkFlow\Tests\Fixtures\Models\User;
use BulkFlow\Tests\TestCase;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

final class QueuedBatchImportTest extends TestCase
{
    public function test_dispatcher_completes_a_sync_batch_and_finalizes_its_run(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bulkflow-batch-').'.csv';
        file_put_contents($path, "name,email\nAda,ada@example.test\nLin,lin@example.test\nNeda,neda@example.test\n");
        $definition = new QueuedImportDefinition(
            modelClass: User::class,
            source: $path,
            mapping: ['name' => 'name', 'email' => 'email'],
            rules: ['email' => ['required', 'email']],
            upsertKeys: ['email'],
            chunkSize: 2,
            errorPolicy: 'continue',
            stopOnErrorCount: null,
        );
        $run = (new DatabaseRunRepository)->createQueued($definition->jsonSerialize());

        (new ProcessImport($run->id, $definition))->handle($this->app->make(BulkFlowManager::class));

        $run->refresh();
        self::assertSame('completed', $run->state);
        self::assertSame(3, $run->total_rows);
        self::assertSame(3, $run->processed_rows);
        self::assertSame(3, $run->successful_rows);
        self::assertSame(3, User::query()->count());
    }

    public function test_dispatcher_records_the_total_for_an_xlsx_batch(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bulkflow-batch-').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['name', 'email']));
        $writer->addRow(Row::fromValues(['Ada', 'ada@example.test']));
        $writer->addRow(Row::fromValues(['Lin', 'lin@example.test']));
        $writer->close();

        $definition = new QueuedImportDefinition(User::class, $path, ['name' => 'name', 'email' => 'email'], [], ['email'], 2, 'continue', null);
        $run = (new DatabaseRunRepository)->createQueued($definition->jsonSerialize());

        (new ProcessImport($run->id, $definition))->handle($this->app->make(BulkFlowManager::class));

        $run->refresh();
        self::assertSame(2, $run->total_rows);
        self::assertSame(2, $run->processed_rows);
    }
}
