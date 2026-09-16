<?php

declare(strict_types=1);

namespace BulkFlow\Events;

use BulkFlow\Run\ImportRun;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ImportProgressUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $runId,
        public readonly string $state,
        public readonly int $totalRows,
        public readonly int $processedRows,
        public readonly int $successfulRows,
        public readonly int $failedRows,
        public readonly int $revision = 0,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('bulkflow.imports.'.$this->runId)];
    }

    public function broadcastAs(): string
    {
        return 'bulkflow.progress.updated';
    }

    /** @return array{id: string, state: string, total_rows: int, processed_rows: int, successful_rows: int, failed_rows: int, revision: int} */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->runId,
            'state' => $this->state,
            'total_rows' => $this->totalRows,
            'processed_rows' => $this->processedRows,
            'successful_rows' => $this->successfulRows,
            'failed_rows' => $this->failedRows,
            'revision' => $this->revision,
        ];
    }

    public static function fromRun(ImportRun $run): self
    {
        return new self(
            $run->id,
            $run->state,
            $run->total_rows,
            $run->processed_rows,
            $run->successful_rows,
            $run->failed_rows,
            $run->revision,
        );
    }
}
