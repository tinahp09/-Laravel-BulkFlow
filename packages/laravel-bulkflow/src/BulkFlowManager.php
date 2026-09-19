<?php

declare(strict_types=1);

namespace BulkFlow;

use BulkFlow\Contracts\Reader;
use BulkFlow\Export\ExportBuilder;
use BulkFlow\Format\CsvReader;
use BulkFlow\Format\FileSource;
use BulkFlow\Format\FormatRegistry;
use BulkFlow\Format\XlsxReader;
use BulkFlow\Import\ImportBuilder;
use BulkFlow\Import\Profiles\ImportProfileRegistry;
use BulkFlow\Schedule\ScheduledExportBuilder;
use Illuminate\Database\Eloquent\Model;

final class BulkFlowManager
{
    public function __construct(
        private readonly FormatRegistry $formats = new FormatRegistry([
            'csv' => new CsvReader,
            'xlsx' => new XlsxReader,
        ]),
        private readonly ?ImportProfileRegistry $profiles = null,
    ) {}

    /** @param class-string<Model> $modelClass */
    public function import(string $modelClass): ImportBuilder
    {
        return new ImportBuilder($modelClass, $this->formats);
    }

    /** @param iterable<array<string, mixed>|Model> $source */
    public function export(iterable $source): ExportBuilder
    {
        return new ExportBuilder($source);
    }

    /** @param class-string<Model> $modelClass */
    public function scheduledExport(string $modelClass): ScheduledExportBuilder
    {
        return new ScheduledExportBuilder($modelClass);
    }

    public function readerFor(FileSource $source): Reader
    {
        return $this->formats->readerFor($source);
    }

    public function profiles(): ImportProfileRegistry
    {
        return $this->profiles ?? new ImportProfileRegistry;
    }
}
