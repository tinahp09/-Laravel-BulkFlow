<?php

declare(strict_types=1);

namespace BulkFlow\Format;

use InvalidArgumentException;

final class UnsupportedFormat extends InvalidArgumentException
{
    public static function forExtension(string $extension): self
    {
        return new self(sprintf('Unsupported file format: %s.', $extension === '' ? '(none)' : $extension));
    }
}
