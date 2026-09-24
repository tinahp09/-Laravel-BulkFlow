<?php

declare(strict_types=1);

namespace BulkFlow\Progress;

use BulkFlow\Run\ImportRun;

final readonly class ProgressSnapshot
{
    public function __construct(
        public string $runId,
        public string $state,
        public int $totalRows,
        public int $processedRows,
        public int $successfulRows,
        public int $failedRows,
        public int $revision,
    ) {}

    public static function fromRun(ImportRun $run): self
    {
        return new self(
            $run->id,
            $run->state,
            (int) $run->total_rows,
            (int) $run->processed_rows,
            (int) $run->successful_rows,
            (int) $run->failed_rows,
            (int) $run->revision,
        );
    }
}
