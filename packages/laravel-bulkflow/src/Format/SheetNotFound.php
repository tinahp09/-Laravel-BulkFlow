<?php

declare(strict_types=1);

namespace BulkFlow\Format;

use InvalidArgumentException;

final class SheetNotFound extends InvalidArgumentException
{
    public static function named(string $name): self
    {
        return new self(sprintf('Worksheet [%s] was not found.', $name));
    }
}
