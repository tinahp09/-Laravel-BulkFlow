<?php

declare(strict_types=1);

namespace BulkFlow\Queue;

/** @param list<string> $headers */
final readonly class CsvChunkRange
{
    public function __construct(
        public string $source,
        public array $headers,
        public int $offset,
        public int $firstRowNumber,
        public int $limit,
    ) {}
}
