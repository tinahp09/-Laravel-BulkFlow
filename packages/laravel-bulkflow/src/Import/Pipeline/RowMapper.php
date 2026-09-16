<?php

declare(strict_types=1);

namespace BulkFlow\Import\Pipeline;

use BulkFlow\Import\SourceRow;

final class RowMapper
{
    /** @param array<string, string> $resolvedMapping destination => source */
    public function map(SourceRow $row, array $resolvedMapping): array
    {
        $mapped = [];

        foreach ($resolvedMapping as $destination => $source) {
            $mapped[$destination] = $row->values[$source] ?? null;
        }

        return $mapped;
    }
}
