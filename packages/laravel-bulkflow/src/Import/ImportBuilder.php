<?php

declare(strict_types=1);

namespace BulkFlow\Import;

use BulkFlow\Format\FileSource;
use BulkFlow\Format\FormatRegistry;
use BulkFlow\Queue\ProcessImport;
use BulkFlow\Queue\QueuedImportDefinition;
use BulkFlow\Run\DatabaseRunRepository;
use BulkFlow\Run\ImportRun;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

final class ImportBuilder
{
    private ?FileSource $source = null;

    /** @var array<string, string> */
    private array $mapping = [];

    /** @var array<string, mixed> */
    private array $rules = [];

    /** @var list<callable(array<string, mixed>): array<string, mixed>> */
    private array $transforms = [];

    /** @var list<string> */
    private array $upsertKeys = [];

    private int $chunkSize = 1_000;

    private string $errorPolicy = 'continue';

    private ?int $stopOnErrorCount = null;

    /** @param class-string<Model> $modelClass */
    public function __construct(
        private readonly string $modelClass,
        private readonly FormatRegistry $formats,
    ) {}

    public function from(FileSource|string|UploadedFile $source): self
    {
        $this->source = match (true) {
            is_string($source) => FileSource::fromPath($source),
            $source instanceof UploadedFile => FileSource::fromUploadedFile($source),
            default => $source,
        };

        return $this;
    }

    public function fromDisk(string $disk, string $path): self
    {
        $this->source = FileSource::fromDisk($disk, $path);

        return $this;
    }

    /** @param array<string, string> $mapping source heading => destination attribute */
    public function map(array $mapping): self
    {
        $this->mapping = $mapping;

        return $this;
    }

    /** @param array<string, mixed> $rules */
    public function validate(array $rules): self
    {
        $this->rules = $rules;

        return $this;
    }

    /** @param callable(array<string, mixed>): array<string, mixed> $transform */
    public function transform(callable $transform): self
    {
        $this->transforms[] = $transform;

        return $this;
    }

    /** @param list<string> $keys */
    public function upsertBy(array $keys): self
    {
        if ($keys === []) {
            throw new InvalidArgumentException('At least one upsert key is required.');
        }

        $this->upsertKeys = $keys;

        return $this;
    }

    public function chunkSize(int $size): self
    {
        if ($size < 1) {
            throw new InvalidArgumentException('Chunk size must be at least 1.');
        }

        $this->chunkSize = $size;

        return $this;
    }

    public function onError(string $policy): self
    {
        if (! in_array($policy, ['continue', 'fail-fast', 'stop-on-threshold'], true)) {
            throw new InvalidArgumentException('Error policy must be continue, fail-fast, or stop-on-threshold.');
        }

        $this->errorPolicy = $policy;

        return $this;
    }

    public function stopOnErrorCount(int $count): self
    {
        if ($count < 1) {
            throw new InvalidArgumentException('Error threshold must be at least 1.');
        }

        $this->stopOnErrorCount = $count;

        return $this;
    }

    public function run(): ImportResult
    {
        if ($this->source === null) {
            throw new InvalidArgumentException('An import source must be configured with from().');
        }

        if ($this->errorPolicy === 'stop-on-threshold' && $this->stopOnErrorCount === null) {
            throw new InvalidArgumentException('stop-on-threshold requires stopOnErrorCount().');
        }

        return (new SyncImportRunner($this->formats))->run(new ImportDefinition(
            modelClass: $this->modelClass,
            source: $this->source,
            mapping: $this->mapping,
            rules: $this->rules,
            transforms: $this->transforms,
            upsertKeys: $this->upsertKeys,
            chunkSize: $this->chunkSize,
            errorPolicy: $this->errorPolicy,
            stopOnErrorCount: $this->stopOnErrorCount,
        ));
    }

    public function queue(): ImportRun
    {
        if ($this->source === null) {
            throw new InvalidArgumentException('An import source must be configured with from().');
        }

        if ($this->errorPolicy === 'stop-on-threshold' && $this->stopOnErrorCount === null) {
            throw new InvalidArgumentException('stop-on-threshold requires stopOnErrorCount().');
        }

        if ($this->transforms !== []) {
            throw new InvalidArgumentException('Queued imports require serializable transformer classes; closures are only supported by run().');
        }

        $definition = new QueuedImportDefinition(
            modelClass: $this->modelClass,
            source: $this->source->path,
            mapping: $this->mapping,
            rules: $this->rules,
            upsertKeys: $this->upsertKeys,
            chunkSize: $this->chunkSize,
            errorPolicy: $this->errorPolicy,
            stopOnErrorCount: $this->stopOnErrorCount,
        );
        $run = (new DatabaseRunRepository)->createQueued($definition->jsonSerialize());
        ProcessImport::dispatch($run->id, $definition);

        return $run;
    }
}
