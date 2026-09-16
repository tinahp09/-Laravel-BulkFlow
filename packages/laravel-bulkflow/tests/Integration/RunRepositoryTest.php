<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Integration;

use BulkFlow\Run\DatabaseRunRepository;
use BulkFlow\Tests\TestCase;

final class RunRepositoryTest extends TestCase
{
    public function test_it_tracks_progress_and_marks_a_run_completed(): void
    {
        $repository = new DatabaseRunRepository;
        $run = $repository->create();

        $repository->recordChunk($run->id, processed: 2, successful: 1, failed: 1);
        $completed = $repository->complete($run->id);

        self::assertSame('completed_with_errors', $completed->state);
        self::assertSame(2, $completed->processed_rows);
        self::assertSame(1, $completed->successful_rows);
        self::assertSame(1, $completed->failed_rows);
        self::assertSame(2, $completed->revision);
    }
}
