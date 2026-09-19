<?php

declare(strict_types=1);

namespace App\Support;

use BulkFlow\Format\FileSource;
use BulkFlow\Format\ReadOptions;
use BulkFlow\BulkFlowManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final readonly class DemoImportUploadStore
{
    private const CACHE_PREFIX = 'bulkflow.demo-upload.';

    public function __construct(private BulkFlowManager $bulkFlow) {}

    /** @return array{upload_id: string, headers: list<string>, preview: list<array<string, mixed>>} */
    public function store(UploadedFile $file): array
    {
        $source = FileSource::fromUploadedFile($file);

        try {
            $preview = [];
            foreach ($this->bulkFlow->readerFor($source)->rows($source, new ReadOptions) as $row) {
                $preview[] = $row->values;

                if (count($preview) === 5) {
                    break;
                }
            }
        } catch (Throwable $exception) {
            @unlink($source->path);

            throw new RuntimeException('The uploaded file could not be read.', previous: $exception);
        }

        if ($preview === []) {
            @unlink($source->path);

            throw new InvalidArgumentException('The uploaded file must include headings and at least one data row.');
        }

        $headers = array_keys($preview[0]);
        if ($headers === [] || count(array_unique($headers)) !== count($headers)) {
            @unlink($source->path);

            throw new InvalidArgumentException('The uploaded file must include unique column headings.');
        }

        $uploadId = (string) Str::uuid();
        Cache::put(self::CACHE_PREFIX.$uploadId, ['path' => $source->path, 'headers' => $headers], now()->addMinutes(15));

        return ['upload_id' => $uploadId, 'headers' => $headers, 'preview' => $preview];
    }

    /** @return array{path: string, headers: list<string>} */
    public function consume(string $uploadId): array
    {
        $upload = Cache::pull(self::CACHE_PREFIX.$uploadId);

        if (! is_array($upload) || ! isset($upload['path'], $upload['headers']) || ! is_string($upload['path']) || ! is_array($upload['headers'])) {
            throw new InvalidArgumentException('This upload has expired or has already been used.');
        }

        return ['path' => $upload['path'], 'headers' => array_values($upload['headers'])];
    }
}
