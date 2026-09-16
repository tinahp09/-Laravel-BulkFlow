<?php

declare(strict_types=1);

namespace BulkFlow\Contracts;

use BulkFlow\Format\FileSource;
use BulkFlow\Format\ReadOptions;
use BulkFlow\Import\SourceRow;

interface Reader
{
    /** @return iterable<array-key, SourceRow> */
    public function rows(FileSource $source, ReadOptions $options): iterable;
}
