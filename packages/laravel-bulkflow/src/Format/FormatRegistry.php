<?php

declare(strict_types=1);

namespace BulkFlow\Format;

use BulkFlow\Contracts\Reader;

final readonly class FormatRegistry
{
    /** @param array<string, Reader> $readers */
    public function __construct(private array $readers) {}

    public function readerFor(FileSource $source): Reader
    {
        $extension = $source->extension();

        return $this->readers[$extension] ?? throw UnsupportedFormat::forExtension($extension);
    }
}
