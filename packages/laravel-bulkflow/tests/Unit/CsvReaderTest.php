<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Unit;

use BulkFlow\Format\CsvReader;
use BulkFlow\Format\FileSource;
use BulkFlow\Format\ReadOptions;
use BulkFlow\Tests\TestCase;

final class CsvReaderTest extends TestCase
{
    public function test_it_streams_associative_rows_after_the_header(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bulkflow-csv-');
        file_put_contents($path, " نام , ایمیل \nندا,neda@example.test\nآرین,arian@example.test\n");

        $rows = iterator_to_array((new CsvReader)->rows(FileSource::fromPath($path), new ReadOptions));

        self::assertSame(2, $rows[0]->number);
        self::assertSame(['نام' => 'ندا', 'ایمیل' => 'neda@example.test'], $rows[0]->values);
        self::assertSame(3, $rows[1]->number);
        self::assertSame('arian@example.test', $rows[1]->values['ایمیل']);
    }

    public function test_it_uses_the_configured_delimiter(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bulkflow-csv-');
        file_put_contents($path, "name;email\nNeda;neda@example.test\n");

        $rows = iterator_to_array((new CsvReader)->rows(FileSource::fromPath($path), new ReadOptions(delimiter: ';')));

        self::assertSame(['name' => 'Neda', 'email' => 'neda@example.test'], $rows[0]->values);
    }
}
