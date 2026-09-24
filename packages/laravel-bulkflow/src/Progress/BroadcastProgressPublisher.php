<?php

declare(strict_types=1);

namespace BulkFlow\Progress;

use BulkFlow\Events\ImportProgressUpdated;

final class BroadcastProgressPublisher implements ProgressPublisher
{
    public function publish(ProgressSnapshot $snapshot): void
    {
        event(new ImportProgressUpdated(
            $snapshot->runId,
            $snapshot->state,
            $snapshot->totalRows,
            $snapshot->processedRows,
            $snapshot->successfulRows,
            $snapshot->failedRows,
            $snapshot->revision,
        ));
    }
}
