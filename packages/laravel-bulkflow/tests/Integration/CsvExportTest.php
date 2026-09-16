<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Integration;

use BulkFlow\BulkFlowManager;
use BulkFlow\Tests\TestCase;

final class CsvExportTest extends TestCase
{
    public function test_it_exports_selected_attributes_with_headings_to_csv(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bulkflow-export-').'.csv';

        $exported = $this->app->make(BulkFlowManager::class)
            ->export([
                ['name' => 'ندا', 'email' => 'neda@example.test', 'hidden' => 'x'],
                ['name' => 'آرین', 'email' => 'arian@example.test', 'hidden' => 'y'],
            ])
            ->columns(['name' => 'نام', 'email' => 'ایمیل'])
            ->asCsv()
            ->store($path);

        self::assertSame($path, $exported->path);
        self::assertSame("نام,ایمیل\nندا,neda@example.test\nآرین,arian@example.test\n", file_get_contents($path));
    }
}
