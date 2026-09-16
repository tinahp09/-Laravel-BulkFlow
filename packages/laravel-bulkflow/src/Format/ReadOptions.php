<?php

declare(strict_types=1);

namespace BulkFlow\Format;

final readonly class ReadOptions
{
    public function __construct(
        public string $delimiter = ',',
        public bool $hasHeader = true,
        public ?string $sheetName = null,
    ) {}
}
