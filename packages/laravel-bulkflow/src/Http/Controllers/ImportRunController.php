<?php

declare(strict_types=1);

namespace BulkFlow\Http\Controllers;

use BulkFlow\Failure\FailureReportExporter;
use BulkFlow\Failure\RowFailureRecord;
use BulkFlow\Retry\RetryFailedRows;
use BulkFlow\Run\DatabaseRunRepository;
use BulkFlow\Run\ImportRun;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Bus;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ImportRunController
{
    public function index(): JsonResponse
    {
        $this->authorize(null);

        return response()->json([
            'data' => $this->scopedRuns()->latest()->get()->map(static fn (ImportRun $run): array => [
                'id' => $run->id,
                'state' => $run->state,
                'total_rows' => $run->total_rows,
                'processed_rows' => $run->processed_rows,
                'successful_rows' => $run->successful_rows,
                'failed_rows' => $run->failed_rows,
                'revision' => $run->revision,
            ]),
        ]);
    }

    public function show(string $run): JsonResponse
    {
        $model = $this->findRun($run);
        $this->authorize($model);

        return response()->json([
            'id' => $model->id,
            'state' => $model->state,
            'total_rows' => $model->total_rows,
            'processed_rows' => $model->processed_rows,
            'successful_rows' => $model->successful_rows,
            'failed_rows' => $model->failed_rows,
            'revision' => $model->revision,
        ]);
    }

    public function failures(string $run): JsonResponse
    {
        $model = $this->findRun($run);
        $this->authorize($model);
        $validated = request()->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'status' => ['nullable', 'in:pending,resolved'],
            'type' => ['nullable', 'string', 'max:100'],
        ]);
        $query = RowFailureRecord::query()->where('run_id', $run)->orderBy('row_number');

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (isset($validated['type'])) {
            $query->where('type', $validated['type']);
        }
        $failures = $query->paginate($validated['per_page'] ?? 50);

        return response()->json([
            'data' => collect($failures->items())
                ->map(static fn (RowFailureRecord $failure): array => [
                    'id' => $failure->id,
                    'row_number' => $failure->row_number,
                    'type' => $failure->type,
                    'status' => $failure->status,
                    'errors' => $failure->errors,
                    'payload' => $failure->payload,
                ]),
            'meta' => [
                'current_page' => $failures->currentPage(),
                'last_page' => $failures->lastPage(),
                'per_page' => $failures->perPage(),
                'total' => $failures->total(),
            ],
        ]);
    }

    public function downloadFailureReport(string $run, FailureReportExporter $exporter): StreamedResponse
    {
        $model = $this->findRun($run);
        $this->authorize($model);
        $validated = request()->validate([
            'format' => ['nullable', 'in:csv,xlsx'],
        ]);
        $format = $validated['format'] ?? 'csv';
        $path = tempnam(sys_get_temp_dir(), 'bulkflow-failures-');

        if ($path === false) {
            throw new RuntimeException('Unable to create a temporary failure report.');
        }

        $path .= '.'.$format;

        try {
            if ($format === 'xlsx') {
                $exporter->toXlsx($model->id, $path);
            } else {
                $exporter->toCsv($model->id, $path);
            }
        } catch (\Throwable $exception) {
            @unlink($path);

            throw $exception;
        }

        return response()->streamDownload(static function () use ($path): void {
            try {
                readfile($path);
            } finally {
                @unlink($path);
            }
        }, 'bulkflow-failures-'.$model->id.'.'.$format, [
            'Content-Type' => $format === 'xlsx'
                ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                : 'text/csv; charset=UTF-8',
        ]);
    }

    public function retryFailures(string $run, RetryFailedRows $retry): JsonResponse
    {
        $this->authorize($this->findRun($run));
        $validated = request()->validate([
            'failure_ids' => ['nullable', 'array'],
            'failure_ids.*' => ['string', 'uuid'],
        ]);
        $child = $retry->run($run, $validated['failure_ids'] ?? null);

        return response()->json([
            'id' => $child->id,
            'parent_run_id' => $child->parent_run_id,
            'state' => $child->state,
        ]);
    }

    public function cancel(string $run, DatabaseRunRepository $runs): JsonResponse
    {
        $model = $this->findRun($run);
        $this->authorize($model);

        if (! in_array($model->state, ['queued', 'processing'], true)) {
            abort(409, 'Only queued or processing imports can be cancelled.');
        }

        if ($model->batch_id !== null) {
            $batch = Bus::findBatch($model->batch_id);

            if ($batch === null) {
                abort(409, 'The queued import batch is no longer available.');
            }

            $batch->cancel();
        }

        $cancelled = $runs->cancel($model->id);

        return response()->json([
            'id' => $cancelled->id,
            'state' => $cancelled->state,
            'total_rows' => $cancelled->total_rows,
            'processed_rows' => $cancelled->processed_rows,
            'successful_rows' => $cancelled->successful_rows,
            'failed_rows' => $cancelled->failed_rows,
            'revision' => $cancelled->revision,
        ]);
    }

    private function authorize(?ImportRun $run): void
    {
        $authorizer = config('bulkflow.authorize');

        if (is_callable($authorizer)) {
            abort_unless((bool) $authorizer(request()->user(), $run), 403);
        }
    }

    /** @return Builder<ImportRun> */
    private function scopedRuns(): Builder
    {
        $query = ImportRun::query();
        $scope = config('bulkflow.scope');

        if (is_callable($scope)) {
            $scoped = $scope($query, request()->user());

            if ($scoped instanceof Builder) {
                return $scoped;
            }
        }

        return $query;
    }

    private function findRun(string $id): ImportRun
    {
        return $this->scopedRuns()->findOrFail($id);
    }
}
