<?php

declare(strict_types=1);

namespace BulkFlow\Queue;

use InvalidArgumentException;

final class ChunkPlanner
{
    /** @return list<array{offset: int, limit: int}> */
    public function plan(int $totalRows, int $chunkSize): array
    {
        if ($totalRows < 0 || $chunkSize < 1) {
            throw new InvalidArgumentException('Total rows must be positive and chunk size must be at least 1.');
        }

        $chunks = [];

        for ($offset = 0; $offset < $totalRows; $offset += $chunkSize) {
            $chunks[] = [
                'offset' => $offset,
                'limit' => min($chunkSize, $totalRows - $offset),
            ];
        }

        return $chunks;
    }
}
