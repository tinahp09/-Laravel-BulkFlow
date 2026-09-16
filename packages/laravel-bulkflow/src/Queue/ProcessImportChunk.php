<?php

declare(strict_types=1);

namespace BulkFlow\Queue;

use BulkFlow\Events\ImportProgressUpdated;
use BulkFlow\Failure\DatabaseFailureRepository;
use BulkFlow\Import\Pipeline\HeaderResolver;
use BulkFlow\Import\Pipeline\RowMapper;
use BulkFlow\Import\Pipeline\RowValidator;
use BulkFlow\Import\SourceRow;
use BulkFlow\Run\DatabaseRunRepository;
use BulkFlow\Run\ImportRun;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class ProcessImportChunk implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    /** @var list<int> */
    public array $backoff;

    /** @param list<array{number: int, values: array<string, mixed>}>|string|CsvChunkRange $rows */
    public function __construct(
        public string $runId,
        public QueuedImportDefinition $definition,
        public array|string|CsvChunkRange $rows,
    ) {
        $retry = RetryPolicy::fromConfig();
        $this->tries = $retry->tries;
        $this->backoff = $retry->backoff;
    }

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $runs = new DatabaseRunRepository;
        $failures = new DatabaseFailureRepository;
        $validator = new RowValidator(app(Factory::class));
        $mapper = new RowMapper;
        $resolvedMapping = null;
        $validRows = [];

        foreach ($this->loadRows() as $record) {
            $sourceRow = new SourceRow($record['number'], $record['values']);

            try {
                $resolvedMapping ??= (new HeaderResolver)->resolve(array_keys($sourceRow->values), $this->definition->mapping);
                $row = $mapper->map($sourceRow, $resolvedMapping);
                $errors = $validator->errors($row, $this->definition->rules);

                if ($errors !== []) {
                    $failures->record($this->runId, $sourceRow->number, 'validation', $errors, $row);
                    $run = $runs->recordChunk($this->runId, 1, 0, 1);
                    $this->publishProgress($run);

                    continue;
                }

                $validRows[] = ['sourceRow' => $sourceRow, 'row' => $row];
            } catch (Throwable $exception) {
                $failures->record($this->runId, $sourceRow->number, 'persistence', ['row' => [$exception->getMessage()]], $sourceRow->values);
                $run = $runs->recordChunk($this->runId, 1, 0, 1);
                $this->publishProgress($run);
            }
        }

        if ($validRows !== []) {
            try {
                $this->persistMany(array_column($validRows, 'row'));
                $run = $runs->recordChunk($this->runId, count($validRows), count($validRows), 0);
                $this->publishProgress($run);
            } catch (Throwable) {
                foreach ($validRows as $valid) {
                    try {
                        $this->persistOne($valid['row']);
                        $run = $runs->recordChunk($this->runId, 1, 1, 0);
                        $this->publishProgress($run);
                    } catch (Throwable $exception) {
                        $failures->record($this->runId, $valid['sourceRow']->number, 'persistence', ['row' => [$exception->getMessage()]], $valid['sourceRow']->values);
                        $run = $runs->recordChunk($this->runId, 1, 0, 1);
                        $this->publishProgress($run);
                    }
                }
            }
        }

        $this->deleteChunkFile();
    }

    /** @param array<string, mixed> $row */
    private function persistOne(array $row): void
    {
        $modelClass = $this->definition->modelClass;

        if ($this->definition->upsertKeys === []) {
            $modelClass::query()->create($row);

            return;
        }

        $lookup = [];
        foreach ($this->definition->upsertKeys as $key) {
            $lookup[$key] = $row[$key] ?? null;
        }

        $modelClass::query()->updateOrCreate($lookup, $row);
    }

    /** @param list<array<string, mixed>> $rows */
    private function persistMany(array $rows): void
    {
        $modelClass = $this->definition->modelClass;
        $model = new $modelClass;
        $now = now();
        $prepared = [];

        foreach ($rows as $row) {
            $instance = $model->newInstance()->fill($row);

            if ($instance->usesTimestamps()) {
                $instance->setCreatedAt($now);
                $instance->setUpdatedAt($now);
            }

            $prepared[] = $instance->getAttributes();
        }

        if ($this->definition->upsertKeys === []) {
            $modelClass::query()->insert($prepared);

            return;
        }

        $updateColumns = array_values(array_diff(array_keys($prepared[0]), $this->definition->upsertKeys));
        $modelClass::query()->upsert($prepared, $this->definition->upsertKeys, $updateColumns);
    }

    private function publishProgress(ImportRun $run): void
    {
        event(ImportProgressUpdated::fromRun($run));
    }

    public function failed(Throwable $exception): void
    {
        (new DatabaseRunRepository)->fail($this->runId);
    }

    /** @return iterable<array{number: int, values: array<string, mixed>}> */
    private function loadRows(): iterable
    {
        if (is_array($this->rows)) {
            yield from $this->rows;

            return;
        }

        if ($this->rows instanceof CsvChunkRange) {
            $handle = fopen($this->rows->source, 'rb');
            if ($handle === false || fseek($handle, $this->rows->offset) !== 0) {
                throw new \RuntimeException('Unable to read assigned CSV chunk range.');
            }
            try {
                for ($index = 0; $index < $this->rows->limit && ($columns = fgetcsv($handle)) !== false; $index++) {
                    $columns = array_map(static fn (?string $value): string => trim((string) $value), $columns);
                    $values = array_combine($this->rows->headers, $columns);
                    if ($values === false) {
                        throw new \RuntimeException('CSV chunk row does not match its header.');
                    }
                    yield ['number' => $this->rows->firstRowNumber + $index, 'values' => $values];
                }
            } finally {
                fclose($handle);
            }

            return;
        }

        $file = new \SplFileObject($this->rows, 'r');

        foreach ($file as $line) {
            if (! is_string($line) || trim($line) === '') {
                continue;
            }

            /** @var array{number: int, values: array<string, mixed>} $row */
            $row = json_decode($line, true, flags: JSON_THROW_ON_ERROR);

            yield $row;
        }
    }

    private function deleteChunkFile(): void
    {
        if (is_string($this->rows) && is_file($this->rows)) {
            unlink($this->rows);
        }
    }
}
