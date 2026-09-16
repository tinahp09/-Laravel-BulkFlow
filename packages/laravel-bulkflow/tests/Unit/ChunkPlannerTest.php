<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Unit;

use BulkFlow\Queue\ChunkPlanner;
use BulkFlow\Tests\TestCase;

final class ChunkPlannerTest extends TestCase
{
    public function test_it_splits_a_row_count_into_stable_offsets(): void
    {
        self::assertSame([
            ['offset' => 0, 'limit' => 1000],
            ['offset' => 1000, 'limit' => 1000],
            ['offset' => 2000, 'limit' => 501],
        ], (new ChunkPlanner)->plan(2501, 1000));
    }
}
