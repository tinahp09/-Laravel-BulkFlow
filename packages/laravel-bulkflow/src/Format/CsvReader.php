<?php

declare(strict_types=1);

namespace BulkFlow\Format;

use BulkFlow\Contracts\Reader;
use BulkFlow\Import\SourceRow;
use RuntimeException;
use SplFileObject;

final class CsvReader implements Reader
{
    public function rows(FileSource $source, ReadOptions $options): iterable
    {
        if (! is_file($source->path) || ! is_readable($source->path)) {
            throw new RuntimeException(sprintf('CSV file is not readable: %s', $source->path));
        }

        $file = new SplFileObject($source->path, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl($options->delimiter);

        $header = null;

        foreach ($file as $index => $columns) {
            if ($columns === [null] || $columns === false) {
                continue;
            }

            $columns = array_map(static fn (mixed $value): string => trim((string) $value), $columns);

            if ($header === null && $options->hasHeader) {
                $header = array_map(static fn (string $value): string => preg_replace('/^\xEF\xBB\xBF/u', '', $value) ?? $value, $columns);

                continue;
            }

            $values = $header === null
                ? $columns
                : array_combine($header, $columns);

            if ($values === false) {
                throw new RuntimeException(sprintf('CSV row %d does not match the header column count.', $index + 1));
            }

            yield new SourceRow($index + 1, $values);
        }
    }
}
