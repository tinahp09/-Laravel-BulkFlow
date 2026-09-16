<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Integration;

use BulkFlow\Failure\DatabaseFailureRepository;
use BulkFlow\Failure\FailureReportExporter;
use BulkFlow\Run\DatabaseRunRepository;
use BulkFlow\Tests\TestCase;

final class FailureReportExporterTest extends TestCase
{
    public function test_it_exports_only_the_failures_for_the_requested_run(): void
    {
        $runs = new DatabaseRunRepository;
        $failures = new DatabaseFailureRepository;
        $first = $runs->create();
        $second = $runs->create();
        $failures->record($first->id, 7, 'validation', ['email' => ['Invalid email']], ['email' => 'bad']);
        $failures->record($second->id, 9, 'validation', ['email' => ['Invalid email']], ['email' => 'other']);
        $path = tempnam(sys_get_temp_dir(), 'bulkflow-report-').'.csv';

        (new FailureReportExporter)->toCsv($first->id, $path);

        $csv = file_get_contents($path);
        self::assertStringContainsString('7,validation', $csv);
        self::assertStringNotContainsString('other', $csv);
    }
}
