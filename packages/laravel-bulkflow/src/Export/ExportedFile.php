<?php

declare(strict_types=1);

namespace BulkFlow\Export;

use DateTimeInterface;
use Illuminate\Support\Facades\Storage;
use LogicException;

final readonly class ExportedFile
{
    public function __construct(public string $path, public ?string $disk = null) {}

    /** @param array<string, mixed> $options */
    public function temporaryUrl(DateTimeInterface $expiration, array $options = []): string
    {
        if ($this->disk === null) {
            throw new LogicException('Temporary URLs are only available for exports stored on a Laravel disk.');
        }

        return Storage::disk($this->disk)->temporaryUrl($this->path, $expiration, $options);
    }
}
