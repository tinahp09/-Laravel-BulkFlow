<?php

declare(strict_types=1);

namespace BulkFlow\Queue;

use BulkFlow\BulkFlowManager;
use BulkFlow\Format\FileSource;
use BulkFlow\Format\ReadOptions;
use BulkFlow\Progress\ProgressPublisher;
use BulkFlow\Progress\ProgressSnapshot;
use BulkFlow\Run\DatabaseRunRepository;
use BulkFlow\Run\ImportRun;
use BulkFlow\Support\MemoryGuard;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Throwable;

final class ProcessImport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    public int $timeout;

    /** @var list<int> */
    public array $backoff;

    public function __construct(public string $runId, public QueuedImportDefinition $definition)
    {
        $retry = RetryPolicy::fromConfig();
        $this->tries = $retry->tries;
        $this->backoff = $retry->backoff;
        $this->timeout = $retry->timeout;
    }

    public function handle(BulkFlowManager $bulkFlow): void
    {
        $runs = new DatabaseRunRepository;
        if (! $runs->start($this->runId)) {
            return;
        }
        $source = FileSource::fromPath($this->definition->source);
        $jobs = [];
        $chunkDirectory = storage_path('app/bulkflow-chunks/'.$this->runId);
        $totalRows = 0;

        if ($source->extension() === 'csv') {
            foreach ((new CsvChunkPlanner)->plan($source->path, $this->definition->chunkSize) as $range) {
                $jobs[] = new ProcessImportChunk($this->runId, $this->definition, $range);
                $totalRows += $range->limit;
            }
        } else {
            $totalRows = $this->spoolReaderChunks($bulkFlow, $source, $jobs);
        }

        $totalsUpdatedRun = $runs->setTotalRows($this->runId, $totalRows);
        app(ProgressPublisher::class)->publish(ProgressSnapshot::fromRun($totalsUpdatedRun));

        if (ImportRun::query()->whereKey($this->runId)->value('state') === 'cancelled') {
            @rmdir($chunkDirectory);

            return;
        }

        if ($jobs === []) {
            $completedRun = $runs->complete($this->runId);
            app(ProgressPublisher::class)->publish(ProgressSnapshot::fromRun($completedRun));

            return;
        }

        $runId = $this->runId;

        $batch = Bus::batch($jobs)
            ->name('BulkFlow import '.$runId)
            ->then(static function (Batch $batch) use ($runId, $chunkDirectory): void {
                $completedRun = (new DatabaseRunRepository)->complete($runId);
                app(ProgressPublisher::class)->publish(ProgressSnapshot::fromRun($completedRun));
                @rmdir($chunkDirectory);
            })
            ->catch(static function (Batch $batch, Throwable $exception) use ($runId): void {
                (new DatabaseRunRepository)->fail($runId);
                $failedRun = ImportRun::query()->findOrFail($runId);
                app(ProgressPublisher::class)->publish(ProgressSnapshot::fromRun($failedRun));
            })
            ->dispatch();

        $runs->setBatchId($this->runId, $batch->id);
    }

    /** @param list<ProcessImportChunk> $jobs */
    private function spoolReaderChunks(BulkFlowManager $bulkFlow, FileSource $source, array &$jobs): int
    {
        $rows = [];
        $totalRows = 0;
        $memory = MemoryGuard::forCurrentProcess();
        $chunkDirectory = storage_path('app/bulkflow-chunks/'.$this->runId);

        if (! is_dir($chunkDirectory)) {
            mkdir($chunkDirectory, 0755, true);
        }

        foreach ($bulkFlow->readerFor($source)->rows($source, new ReadOptions) as $sourceRow) {
            $memory->check();
            $rows[] = ['number' => $sourceRow->number, 'values' => $sourceRow->values];
            $totalRows++;

            if (count($rows) === $this->definition->chunkSize) {
                $jobs[] = new ProcessImportChunk($this->runId, $this->definition, $this->writeChunk($chunkDirectory, count($jobs), $rows));
                $rows = [];
            }
        }

        if ($rows !== []) {
            $jobs[] = new ProcessImportChunk($this->runId, $this->definition, $this->writeChunk($chunkDirectory, count($jobs), $rows));
        }

        return $totalRows;
    }

    public function failed(Throwable $exception): void
    {
        (new DatabaseRunRepository)->fail($this->runId);
    }

    /** @param list<array{number: int, values: array<string, mixed>}> $rows */
    private function writeChunk(string $directory, int $index, array $rows): string
    {
        $path = $directory.'/'.str_pad((string) $index, 8, '0', STR_PAD_LEFT).'.ndjson';
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new \RuntimeException(sprintf('Unable to create BulkFlow chunk file [%s].', $path));
        }

        try {
            foreach ($rows as $row) {
                fwrite($handle, json_encode($row, JSON_THROW_ON_ERROR)."\n");
            }
        } finally {
            fclose($handle);
        }

        return $path;
    }
}
