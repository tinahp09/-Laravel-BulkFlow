<?php

declare(strict_types=1);

namespace BulkFlow\Schedule;

use Cron\CronExpression;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class ScheduledExportBuilder
{
    /** @var array<string, string> */
    private array $columns = [];

    private string $name = '';

    private string $format = 'csv';

    private ?string $disk = null;

    private string $path = '';

    private string $expression = '0 0 * * *';

    private ?string $recipient = null;

    /** @param class-string<Model> $modelClass */
    public function __construct(private readonly string $modelClass) {}

    public function named(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /** @param array<string, string> $attributeToHeading */
    public function columns(array $attributeToHeading): self
    {
        $this->columns = $attributeToHeading;

        return $this;
    }

    public function asCsv(): self
    {
        $this->format = 'csv';

        return $this;
    }

    public function asXlsx(): self
    {
        $this->format = 'xlsx';

        return $this;
    }

    public function store(string $path): self
    {
        $this->disk = null;
        $this->path = $path;

        return $this;
    }

    public function storeOnDisk(string $disk, string $path): self
    {
        $this->disk = $disk;
        $this->path = $path;

        return $this;
    }

    public function cron(string $expression): self
    {
        $this->expression = $expression;

        return $this;
    }

    public function notifyTo(string $recipient): self
    {
        $this->recipient = $recipient;

        return $this;
    }

    public function save(): ScheduledExport
    {
        if ($this->name === '' || $this->path === '' || $this->columns === []) {
            throw new InvalidArgumentException('A scheduled export requires a name, columns, and destination path.');
        }

        CronExpression::factory($this->expression);

        return ScheduledExport::query()->updateOrCreate(['name' => $this->name], [
            'model_class' => $this->modelClass,
            'columns' => $this->columns,
            'format' => $this->format,
            'disk' => $this->disk,
            'path' => $this->path,
            'expression' => $this->expression,
            'recipient' => $this->recipient,
            'active' => true,
        ]);
    }
}
