<?php

declare(strict_types=1);

namespace BulkFlow\Import;

use BulkFlow\Events\ImportProgressUpdated;
use BulkFlow\Failure\DatabaseFailureRepository;
use BulkFlow\Format\FormatRegistry;
use BulkFlow\Format\ReadOptions;
use BulkFlow\Import\Pipeline\HeaderResolver;
use BulkFlow\Import\Pipeline\RowMapper;
use BulkFlow\Import\Pipeline\RowValidator;
use BulkFlow\Run\DatabaseRunRepository;
use Illuminate\Contracts\Validation\Factory;
use Throwable;

final class SyncImportRunner
{
    public function __construct(private readonly FormatRegistry $formats) {}

    public function run(ImportDefinition $definition): ImportResult
    {
        $total = 0;
        $successful = 0;
        $failures = [];
        $chunksProcessed = 0;
        $resolvedMapping = null;
        $runRepository = new DatabaseRunRepository;
        $failureRepository = new DatabaseFailureRepository;
        $run = $runRepository->create([
            'modelClass' => $definition->modelClass,
            'source' => $definition->source->path,
            'mapping' => $definition->mapping,
            'rules' => $definition->rules,
            'upsertKeys' => $definition->upsertKeys,
            'chunkSize' => $definition->chunkSize,
            'errorPolicy' => $definition->errorPolicy,
            'stopOnErrorCount' => $definition->stopOnErrorCount,
        ]);
        $validator = new RowValidator(app(Factory::class));
        $mapper = new RowMapper;
        $reader = $this->formats->readerFor($definition->source);

        foreach ($reader->rows($definition->source, new ReadOptions) as $sourceRow) {
            $total++;

            if (($total - 1) % $definition->chunkSize === 0) {
                $chunksProcessed++;
            }

            try {
                $resolvedMapping ??= (new HeaderResolver)->resolve(array_keys($sourceRow->values), $definition->mapping);
                $row = $mapper->map($sourceRow, $resolvedMapping);

                foreach ($definition->transforms as $transform) {
                    $row = $transform($row);
                }

                $errors = $validator->errors($row, $definition->rules);

                if ($errors !== []) {
                    $failures[] = new RowFailure($sourceRow->number, 'validation', $errors);
                    $failureRepository->record($run->id, $sourceRow->number, 'validation', $errors, $row);
                    $updatedRun = $runRepository->recordChunk($run->id, 1, 0, 1);
                    event(ImportProgressUpdated::fromRun($updatedRun));

                    if ($this->shouldStop($definition, count($failures))) {
                        break;
                    }

                    continue;
                }

                $this->persist($definition, $row);
                $successful++;
                $updatedRun = $runRepository->recordChunk($run->id, 1, 1, 0);
                event(ImportProgressUpdated::fromRun($updatedRun));
            } catch (Throwable $exception) {
                $errors = ['row' => [$exception->getMessage()]];
                $failures[] = new RowFailure($sourceRow->number, 'persistence', $errors);
                $failureRepository->record($run->id, $sourceRow->number, 'persistence', $errors, $sourceRow->values);
                $updatedRun = $runRepository->recordChunk($run->id, 1, 0, 1);
                event(ImportProgressUpdated::fromRun($updatedRun));

                if ($this->shouldStop($definition, count($failures))) {
                    break;
                }
            }
        }

        $runRepository->setTotalRows($run->id, $total);
        $runRepository->complete($run->id);

        return new ImportResult($run->id, $total, $successful, count($failures), 0, $chunksProcessed, $failures);
    }

    /** @param array<string, mixed> $row */
    private function persist(ImportDefinition $definition, array $row): void
    {
        $modelClass = $definition->modelClass;

        if ($definition->upsertKeys === []) {
            $modelClass::query()->create($row);

            return;
        }

        $lookup = [];

        foreach ($definition->upsertKeys as $key) {
            $lookup[$key] = $row[$key] ?? null;
        }

        $modelClass::query()->updateOrCreate($lookup, $row);
    }

    private function shouldStop(ImportDefinition $definition, int $failureCount): bool
    {
        return $definition->errorPolicy === 'fail-fast'
            || ($definition->errorPolicy === 'stop-on-threshold' && $failureCount >= $definition->stopOnErrorCount);
    }
}
