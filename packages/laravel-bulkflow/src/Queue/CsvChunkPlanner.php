<?php

declare(strict_types=1);

namespace BulkFlow\Queue;

use RuntimeException;

final class CsvChunkPlanner
{
    /** @return list<CsvChunkRange> */
    public function plan(string $path, int $chunkSize): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException(sprintf('CSV file is not readable: %s', $path));
        }

        try {
            $header = fgetcsv($handle);
            if ($header === false) {
                return [];
            }
            $headers = array_map(static fn (?string $value): string => trim((string) $value), $header);
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/u', '', $headers[0]) ?? $headers[0];
            $starts = [];
            $rows = 0;

            while (($offset = ftell($handle)) !== false && fgetcsv($handle) !== false) {
                if ($rows % $chunkSize === 0) {
                    $starts[] = ['offset' => $offset, 'firstRowNumber' => $rows + 2];
                }
                $rows++;
            }

            return array_map(static fn (array $start, int $index): CsvChunkRange => new CsvChunkRange(
                $path,
                $headers,
                $start['offset'],
                $start['firstRowNumber'],
                min($chunkSize, $rows - ($index * $chunkSize)),
            ), $starts, array_keys($starts));
        } finally {
            fclose($handle);
        }
    }
}
