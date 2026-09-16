<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Integration;

use BulkFlow\Format\FileSource;
use BulkFlow\Format\ReadOptions;
use BulkFlow\Format\XlsxReader;
use BulkFlow\Tests\TestCase;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

final class XlsxReaderTest extends TestCase
{
    public function test_it_streams_rows_from_the_first_sheet_with_header_keys(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bulkflow-xlsx-').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['نام', 'ایمیل']));
        $writer->addRow(Row::fromValues(['ندا', 'neda@example.test']));
        $writer->close();

        $rows = iterator_to_array((new XlsxReader)->rows(FileSource::fromPath($path), new ReadOptions));

        self::assertCount(1, $rows);
        self::assertSame(2, $rows[0]->number);
        self::assertSame(['نام' => 'ندا', 'ایمیل' => 'neda@example.test'], $rows[0]->values);
    }
}
