<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Feature;

use BulkFlow\Failure\DatabaseFailureRepository;
use BulkFlow\Run\DatabaseRunRepository;
use BulkFlow\Tests\TestCase;

final class FailureReportDownloadApiTest extends TestCase
{
    public function test_it_downloads_a_csv_report_for_the_requested_run(): void
    {
        $run = (new DatabaseRunRepository)->create();
        (new DatabaseFailureRepository)->record($run->id, 7, 'validation', ['email' => ['Invalid']], ['email' => 'bad@example.test']);

        $response = $this->get('/bulkflow/imports/'.$run->id.'/failures/report?format=csv')
            ->assertOk()
            ->assertStreamed()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition', 'attachment; filename=bulkflow-failures-'.$run->id.'.csv');

        self::assertStringContainsString('row_number,type,errors,payload', $response->streamedContent());
        self::assertStringContainsString('bad@example.test', $response->streamedContent());
    }
}
