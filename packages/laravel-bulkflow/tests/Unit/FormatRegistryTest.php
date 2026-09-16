<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Unit;

use BulkFlow\Contracts\Reader;
use BulkFlow\Format\FileSource;
use BulkFlow\Format\FormatRegistry;
use BulkFlow\Format\ReadOptions;
use BulkFlow\Format\UnsupportedFormat;
use BulkFlow\Tests\TestCase;

final class FormatRegistryTest extends TestCase
{
    public function test_it_selects_a_reader_from_the_file_extension(): void
    {
        $csvReader = new class implements Reader
        {
            public function rows(FileSource $source, ReadOptions $options): iterable
            {
                return [];
            }
        };

        $registry = new FormatRegistry(['csv' => $csvReader]);

        self::assertSame($csvReader, $registry->readerFor(FileSource::fromPath('/tmp/users.csv')));
    }

    public function test_it_rejects_an_unsupported_file_extension(): void
    {
        $registry = new FormatRegistry([]);

        $this->expectException(UnsupportedFormat::class);
        $this->expectExceptionMessage('pdf');

        $registry->readerFor(FileSource::fromPath('/tmp/users.pdf'));
    }
}
