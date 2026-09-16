<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Integration;

use BulkFlow\Failure\DatabaseFailureRepository;
use BulkFlow\Failure\FailureReportExporter;
use BulkFlow\Format\FileSource;
use BulkFlow\Format\ReadOptions;
use BulkFlow\Format\XlsxReader;
use BulkFlow\Run\DatabaseRunRepository;
use BulkFlow\Tests\TestCase;

final class FailureReportXlsxTest extends TestCase
{
    public function test_it_exports_failures_as_xlsx(): void
    {
        $run = (new DatabaseRunRepository)->create();
        (new DatabaseFailureRepository)->record($run->id, 5, 'validation', ['email' => ['Invalid']], ['email' => 'bad']);
        $path = tempnam(sys_get_temp_dir(), 'bulkflow-report-').'.xlsx';

        (new FailureReportExporter)->toXlsx($run->id, $path);
        $rows = iterator_to_array((new XlsxReader)->rows(FileSource::fromPath($path), new ReadOptions));

        self::assertSame(5, $rows[0]->values['row_number']);
        self::assertSame('validation', $rows[0]->values['type']);
    }
}
