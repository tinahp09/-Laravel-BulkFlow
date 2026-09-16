<?php

declare(strict_types=1);

namespace BulkFlow\Import;

final readonly class SourceRow
{
    /** @param array<string, mixed> $values */
    public function __construct(public int $number, public array $values) {}
}
