<?php

declare(strict_types=1);

namespace BulkFlow\Export;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

final class ExportBuilder
{
    /** @var array<string, string> */
    private array $columns = [];

    private string $format = 'csv';

    /** @param iterable<array<string, mixed>|Model> $source */
    public function __construct(private readonly iterable $source) {}

    /** @param array<string, string> $attributeToHeading */
    public function columns(array $attributeToHeading): self
    {
        $this->columns = $attributeToHeading;

        return $this;
    }

    public function asCsv(): self
    {
        $this->format = 'csv';

        return $this;
    }

    public function asXlsx(): self
    {
        $this->format = 'xlsx';

        return $this;
    }

    public function store(string $path): ExportedFile
    {
        return match ($this->format) {
            'csv' => $this->writeCsv($path),
            'xlsx' => $this->writeXlsx($path),
            default => throw new InvalidArgumentException(sprintf('Unsupported export format [%s].', $this->format)),
        };
    }

    public function storeOnDisk(string $disk, string $path): ExportedFile
    {
        $extension = $this->format === 'xlsx' ? '.xlsx' : '.csv';
        $temporary = tempnam(sys_get_temp_dir(), 'bulkflow-export-').$extension;

        try {
            $this->store($temporary);
            $stream = fopen($temporary, 'rb');

            if ($stream === false || ! Storage::disk($disk)->put($path, $stream)) {
                throw new InvalidArgumentException(sprintf('Cannot store export on disk [%s].', $disk));
            }

            return new ExportedFile($path, $disk);
        } finally {
            if (isset($stream) && is_resource($stream)) {
                fclose($stream);
            }
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    private function writeCsv(string $path): ExportedFile
    {
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new InvalidArgumentException(sprintf('Cannot write export to [%s].', $path));
        }

        try {
            fputcsv($handle, array_values($this->columns));

            foreach ($this->source as $item) {
                $row = $item instanceof Model ? $item->getAttributes() : $item;
                fputcsv($handle, array_map(static fn (string $attribute): mixed => $row[$attribute] ?? null, array_keys($this->columns)));
            }
        } finally {
            fclose($handle);
        }

        return new ExportedFile($path);
    }

    private function writeXlsx(string $path): ExportedFile
    {
        $writer = new XlsxWriter;
        $writer->openToFile($path);

        try {
            $writer->addRow(Row::fromValues(array_values($this->columns)));

            foreach ($this->source as $item) {
                $row = $item instanceof Model ? $item->getAttributes() : $item;
                $writer->addRow(Row::fromValues(array_map(static fn (string $attribute): mixed => $row[$attribute] ?? null, array_keys($this->columns))));
            }
        } finally {
            $writer->close();
        }

        return new ExportedFile($path);
    }
}
