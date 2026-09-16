<?php

declare(strict_types=1);

namespace BulkFlow\Format;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class FileSource
{
    private function __construct(public string $path) {}

    public static function fromPath(string $path): self
    {
        if (trim($path) === '') {
            throw new InvalidArgumentException('A file path is required.');
        }

        return new self($path);
    }

    public static function fromDisk(string $disk, string $path): self
    {
        if (trim($disk) === '' || trim($path) === '') {
            throw new InvalidArgumentException('A storage disk and path are required.');
        }

        $stream = Storage::disk($disk)->readStream($path);

        if (! is_resource($stream)) {
            throw new InvalidArgumentException(sprintf('Unable to read [%s] from disk [%s].', $path, $disk));
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        self::assertAllowedExtension($extension);

        return self::materialize($stream, $extension, sprintf('Unable to materialize [%s] from disk [%s].', $path, $disk));
    }

    public static function fromUploadedFile(UploadedFile $file): self
    {
        if (! $file->isValid()) {
            throw new InvalidArgumentException('The uploaded file is invalid.');
        }

        $extension = $file->extension();
        self::assertAllowedExtension($extension);
        $size = $file->getSize() ?? 0;
        $maxBytes = (int) config('bulkflow.input.max_bytes', 100 * 1024 * 1024);

        if ($maxBytes > 0 && $size > $maxBytes) {
            throw new InvalidArgumentException('The uploaded file exceeds the configured maximum size.');
        }

        $path = $file->getRealPath() ?: $file->getPathname();
        $stream = @fopen($path, 'rb');

        if ($stream === false) {
            throw new InvalidArgumentException('The uploaded file is no longer readable.');
        }

        return self::materialize($stream, $extension, 'Unable to materialize the uploaded file.');
    }

    public function extension(): string
    {
        return strtolower((string) pathinfo($this->path, PATHINFO_EXTENSION));
    }

    private static function assertAllowedExtension(string $extension): void
    {
        $extension = strtolower($extension);
        $allowed = array_map('strtolower', (array) config('bulkflow.input.allowed_extensions', ['csv', 'xlsx']));

        if ($extension === '' || ! in_array($extension, $allowed, true)) {
            throw new InvalidArgumentException(sprintf('The uploaded file extension [%s] is not allowed.', $extension));
        }
    }

    /** @param resource $source */
    private static function materialize($source, string $extension, string $failureMessage): self
    {
        $directory = storage_path('app/bulkflow-inputs');
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            fclose($source);
            throw new InvalidArgumentException(sprintf('Unable to create BulkFlow input directory [%s].', $directory));
        }

        $localPath = $directory.'/'.Str::uuid().($extension === '' ? '' : '.'.strtolower($extension));
        $destination = fopen($localPath, 'wb');

        if ($destination === false) {
            fclose($source);
            throw new InvalidArgumentException($failureMessage);
        }

        try {
            stream_copy_to_stream($source, $destination);
        } finally {
            fclose($source);
            fclose($destination);
        }

        return new self($localPath);
    }
}
