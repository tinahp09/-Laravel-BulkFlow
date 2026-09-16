<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Integration;

use BulkFlow\Format\FileSource;
use BulkFlow\Format\ReadOptions;
use BulkFlow\Format\XlsxReader;
use BulkFlow\Tests\TestCase;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

final class LargeXlsxReaderTest extends TestCase
{
    public function test_it_streams_ten_thousand_xlsx_rows_without_materializing_the_result(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bulkflow-large-xlsx-').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['name', 'email']));

        for ($row = 1; $row <= 10_000; $row++) {
            $writer->addRow(Row::fromValues(['User '.$row, 'user-'.$row.'@example.test']));
        }

        $writer->close();

        try {
            $count = 0;
            $first = null;
            $last = null;

            foreach ((new XlsxReader)->rows(FileSource::fromPath($path), new ReadOptions) as $row) {
                $count++;
                $first ??= $row->values;
                $last = $row->values;
            }

            self::assertSame(10_000, $count);
            self::assertSame(['name' => 'User 1', 'email' => 'user-1@example.test'], $first);
            self::assertSame(['name' => 'User 10000', 'email' => 'user-10000@example.test'], $last);
        } finally {
            @unlink($path);
        }
    }
}
