<?php

declare(strict_types=1);

namespace BulkFlow\Import\Profiles;

use BulkFlow\BulkFlowManager;
use BulkFlow\Format\FileSource;
use BulkFlow\Format\ReadOptions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final readonly class ProfileUploadStore
{
    private const CachePrefix = 'bulkflow.profile-upload.';

    public function __construct(private BulkFlowManager $bulkFlow) {}

    /** @return array{upload_id: string, headers: list<string>, preview: list<array<string, mixed>>} */
    public function store(UploadedFile $file, string $profileKey, string $actorFingerprint): array
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
        Cache::put(self::CachePrefix.$uploadId, [
            'path' => $source->path,
            'headers' => $headers,
            'profile_key' => $profileKey,
            'actor_fingerprint' => $actorFingerprint,
        ], now()->addMinutes(15));

        return ['upload_id' => $uploadId, 'headers' => $headers, 'preview' => $preview];
    }

    /** @return array{path: string, headers: list<string>, profile_key: string, actor_fingerprint: string} */
    public function find(string $uploadId): array
    {
        $upload = Cache::get(self::CachePrefix.$uploadId);

        if (! is_array($upload) || ! isset($upload['path'], $upload['headers'], $upload['profile_key'], $upload['actor_fingerprint'])) {
            throw new InvalidArgumentException('This upload has expired or has already been used.');
        }

        return [
            'path' => (string) $upload['path'],
            'headers' => array_values($upload['headers']),
            'profile_key' => (string) $upload['profile_key'],
            'actor_fingerprint' => (string) $upload['actor_fingerprint'],
        ];
    }

    public function consume(string $uploadId): void
    {
        Cache::forget(self::CachePrefix.$uploadId);
    }
}
