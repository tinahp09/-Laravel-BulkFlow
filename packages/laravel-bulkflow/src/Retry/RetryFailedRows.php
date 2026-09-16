<?php

declare(strict_types=1);

namespace BulkFlow\Retry;

use BulkFlow\Failure\RowFailureRecord;
use BulkFlow\Run\DatabaseRunRepository;
use BulkFlow\Run\ImportRun;
use Illuminate\Contracts\Validation\Factory;
use InvalidArgumentException;
use Throwable;

final class RetryFailedRows
{
    /** @param list<string>|null $failureIds */
    public function run(string $parentRunId, ?array $failureIds = null): ImportRun
    {
        $parent = ImportRun::query()->findOrFail($parentRunId);
        $definition = $parent->definition ?? [];
        $modelClass = $definition['modelClass'] ?? null;

        if (! is_string($modelClass) || ! class_exists($modelClass)) {
            throw new InvalidArgumentException('The original import definition has no retryable model class.');
        }

        $runs = new DatabaseRunRepository;
        $child = $runs->create($definition, $parent->id);
        $validator = app(Factory::class);

        $failures = RowFailureRecord::query()
            ->where('run_id', $parent->id)
            ->where('status', 'pending');

        if ($failureIds !== null) {
            $failures->whereIn('id', $failureIds);
        }

        $failures->each(function (RowFailureRecord $failure) use ($definition, $modelClass, $runs, $child, $validator): void {
            $payload = $failure->payload ?? [];
            $validation = $validator->make($payload, $definition['rules'] ?? []);

            if ($validation->fails()) {
                $runs->recordChunk($child->id, 1, 0, 1);

                return;
            }

            try {
                $keys = $definition['upsertKeys'] ?? [];

                if ($keys === []) {
                    $modelClass::query()->create($payload);
                } else {
                    $lookup = [];
                    foreach ($keys as $key) {
                        $lookup[$key] = $payload[$key] ?? null;
                    }
                    $modelClass::query()->updateOrCreate($lookup, $payload);
                }

                $runs->recordChunk($child->id, 1, 1, 0);
                $failure->update(['status' => 'resolved']);
            } catch (Throwable) {
                $runs->recordChunk($child->id, 1, 0, 1);
            }
        });

        return $runs->complete($child->id);
    }
}
