<?php

declare(strict_types=1);

namespace BulkFlow\Import;

use BulkFlow\Format\FileSource;
use Illuminate\Database\Eloquent\Model;

final readonly class ImportDefinition
{
    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, string>  $mapping
     * @param  array<string, mixed>  $rules
     * @param  list<callable(array<string, mixed>): array<string, mixed>>  $transforms
     * @param  list<string>  $upsertKeys
     */
    public function __construct(
        public string $modelClass,
        public FileSource $source,
        public array $mapping,
        public array $rules,
        public array $transforms,
        public array $upsertKeys,
        public int $chunkSize,
        public string $errorPolicy,
        public ?int $stopOnErrorCount,
    ) {}
}
