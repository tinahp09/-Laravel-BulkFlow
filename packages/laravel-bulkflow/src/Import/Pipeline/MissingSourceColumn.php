<?php

declare(strict_types=1);

namespace BulkFlow\Import\Pipeline;

use InvalidArgumentException;

final class MissingSourceColumn extends InvalidArgumentException
{
    public static function named(string $heading): self
    {
        return new self(sprintf('The mapped source column [%s] does not exist.', $heading));
    }
}
