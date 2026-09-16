<?php

declare(strict_types=1);

namespace BulkFlow\Failure;

use Illuminate\Support\Str;

final class DatabaseFailureRepository
{
    public function __construct(private readonly PayloadRedactor $redactor = new PayloadRedactor) {}

    /** @param array<string, list<string>> $errors @param array<string, mixed> $payload */
    public function record(string $runId, int $rowNumber, string $type, array $errors, array $payload): RowFailureRecord
    {
        return RowFailureRecord::query()->create([
            'id' => (string) Str::uuid(),
            'run_id' => $runId,
            'row_number' => $rowNumber,
            'type' => $type,
            'errors' => $errors,
            'payload' => $this->redactor->redact($payload),
            'status' => 'pending',
        ]);
    }
}
