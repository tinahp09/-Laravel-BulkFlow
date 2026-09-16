<?php

declare(strict_types=1);

namespace BulkFlow\Queue;

use JsonSerializable;

/**
 * Serializable queue payload. Transform closures are intentionally excluded.
 *
 * @implements JsonSerializable<array{modelClass: string, source: string, mapping: array<string, string>, rules: array<string, mixed>, upsertKeys: list<string>, chunkSize: int, errorPolicy: string, stopOnErrorCount: ?int}>
 */
final readonly class QueuedImportDefinition implements JsonSerializable
{
    /**
     * @param  array<string, string>  $mapping
     * @param  array<string, mixed>  $rules
     * @param  list<string>  $upsertKeys
     */
    public function __construct(
        public string $modelClass,
        public string $source,
        public array $mapping,
        public array $rules,
        public array $upsertKeys,
        public int $chunkSize,
        public string $errorPolicy,
        public ?int $stopOnErrorCount,
    ) {}

    /** @return array{modelClass: string, source: string, mapping: array<string, string>, rules: array<string, mixed>, upsertKeys: list<string>, chunkSize: int, errorPolicy: string, stopOnErrorCount: ?int} */
    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
