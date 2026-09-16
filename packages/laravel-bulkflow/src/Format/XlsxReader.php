<?php

declare(strict_types=1);

namespace BulkFlow\Format;

use BulkFlow\Contracts\Reader;
use BulkFlow\Import\SourceRow;
use OpenSpout\Reader\SheetInterface;
use OpenSpout\Reader\XLSX\Reader as OpenSpoutReader;
use RuntimeException;

final class XlsxReader implements Reader
{
    public function rows(FileSource $source, ReadOptions $options): iterable
    {
        $reader = new OpenSpoutReader;
        $reader->open($source->path);

        try {
            $sheet = $this->selectSheet($reader->getSheetIterator(), $options->sheetName);
            $header = null;
            $rowNumber = 0;

            foreach ($sheet->getRowIterator() as $row) {
                $rowNumber++;
                $values = array_map(static fn ($cell): mixed => $cell->getValue(), $row->getCells());

                if ($header === null && $options->hasHeader) {
                    $header = array_map(static fn (mixed $value): string => trim((string) $value), $values);

                    continue;
                }

                $mapped = $header === null ? $values : array_combine($header, $values);

                if ($mapped === false) {
                    throw new RuntimeException(sprintf('XLSX row %d does not match the header column count.', $rowNumber));
                }

                yield new SourceRow($rowNumber, $mapped);
            }
        } finally {
            $reader->close();
        }
    }

    private function selectSheet(iterable $sheets, ?string $sheetName): SheetInterface
    {
        foreach ($sheets as $sheet) {
            if ($sheetName === null || $sheet->getName() === $sheetName) {
                return $sheet;
            }
        }

        throw SheetNotFound::named($sheetName ?? 'first');
    }
}
