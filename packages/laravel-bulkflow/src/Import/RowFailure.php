<?php

declare(strict_types=1);

namespace BulkFlow\Import;

final readonly class RowFailure
{
    /** @param array<string, mixed> $errors */
    public function __construct(
        public int $rowNumber,
        public string $type,
        public array $errors,
    ) {}
}
