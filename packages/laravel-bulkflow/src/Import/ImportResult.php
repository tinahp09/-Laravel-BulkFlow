<?php

declare(strict_types=1);

namespace BulkFlow\Import;

final readonly class ImportResult
{
    /** @param list<RowFailure> $failures */
    public function __construct(
        public string $runId,
        public int $totalRows,
        public int $successfulRows,
        public int $failedRows,
        public int $skippedRows,
        public int $chunksProcessed,
        public array $failures,
    ) {}
}
