<?php

declare(strict_types=1);

namespace BulkFlow\Run;

use BulkFlow\Notifications\ImportCompletionNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DatabaseRunRepository
{
    /** @param array<string, mixed> $definition */
    public function create(array $definition = [], ?string $parentRunId = null): ImportRun
    {
        return ImportRun::query()->create([
            'id' => (string) Str::uuid(),
            'state' => 'processing',
            'definition' => $definition,
            'parent_run_id' => $parentRunId,
        ]);
    }

    /** @param array<string, mixed> $definition */
    public function createQueued(array $definition): ImportRun
    {
        return ImportRun::query()->create([
            'id' => (string) Str::uuid(),
            'state' => 'queued',
            'definition' => $definition,
        ]);
    }

    public function start(string $runId): bool
    {
        return ImportRun::query()
            ->whereKey($runId)
            ->where('state', 'queued')
            ->update([
                'state' => 'processing',
                'revision' => DB::raw('revision + 1'),
            ]) === 1;
    }

    public function fail(string $runId): void
    {
        ImportRun::query()
            ->whereKey($runId)
            ->where('state', '!=', 'cancelled')
            ->update([
                'state' => 'failed',
                'revision' => DB::raw('revision + 1'),
            ]);
    }

    public function cancel(string $runId): ImportRun
    {
        ImportRun::query()
            ->whereKey($runId)
            ->whereIn('state', ['queued', 'processing'])
            ->update([
                'state' => 'cancelled',
                'revision' => DB::raw('revision + 1'),
            ]);

        return ImportRun::query()->findOrFail($runId);
    }

    public function recordChunk(string $runId, int $processed, int $successful, int $failed): ImportRun
    {
        ImportRun::query()->whereKey($runId)->incrementEach([
            'processed_rows' => $processed,
            'successful_rows' => $successful,
            'failed_rows' => $failed,
            'revision' => 1,
        ]);

        return ImportRun::query()->findOrFail($runId);
    }

    public function setTotalRows(string $runId, int $totalRows): ImportRun
    {
        ImportRun::query()->whereKey($runId)->update([
            'total_rows' => $totalRows,
            'revision' => DB::raw('revision + 1'),
        ]);

        return ImportRun::query()->findOrFail($runId);
    }

    public function setBatchId(string $runId, string $batchId): ImportRun
    {
        ImportRun::query()->whereKey($runId)->update(['batch_id' => $batchId]);

        return ImportRun::query()->findOrFail($runId);
    }

    public function complete(string $runId): ImportRun
    {
        $run = ImportRun::query()->findOrFail($runId);

        if ($run->state === 'cancelled') {
            return $run;
        }

        $run->state = $run->failed_rows > 0 ? 'completed_with_errors' : 'completed';
        $run->revision++;
        $run->save();

        $completed = ImportRun::query()->findOrFail($runId);
        app(ImportCompletionNotifier::class)->notify($completed);

        return $completed;
    }
}
