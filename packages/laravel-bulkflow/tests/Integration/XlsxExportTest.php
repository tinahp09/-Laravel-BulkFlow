<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Integration;

use BulkFlow\BulkFlowManager;
use BulkFlow\Format\FileSource;
use BulkFlow\Format\ReadOptions;
use BulkFlow\Format\XlsxReader;
use BulkFlow\Tests\TestCase;

final class XlsxExportTest extends TestCase
{
    public function test_it_exports_selected_attributes_to_a_readable_xlsx_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bulkflow-export-').'.xlsx';

        $this->app->make(BulkFlowManager::class)
            ->export([['name' => 'ندا', 'email' => 'neda@example.test']])
            ->columns(['name' => 'نام', 'email' => 'ایمیل'])
            ->asXlsx()
            ->store($path);

        $rows = iterator_to_array((new XlsxReader)->rows(FileSource::fromPath($path), new ReadOptions));

        self::assertSame(['نام' => 'ندا', 'ایمیل' => 'neda@example.test'], $rows[0]->values);
    }
}
