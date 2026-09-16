<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Integration;

use BulkFlow\BulkFlowManager;
use BulkFlow\Tests\Fixtures\Models\User;
use BulkFlow\Tests\TestCase;

final class ChunkedImportTest extends TestCase
{
    public function test_it_reports_completed_chunks_while_streaming_the_source(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bulkflow-chunks-').'.csv';
        file_put_contents($path, "name,email\nA,a@example.test\nB,b@example.test\nC,c@example.test\n");

        $result = $this->app->make(BulkFlowManager::class)
            ->import(User::class)
            ->from($path)
            ->map(['name' => 'name', 'email' => 'email'])
            ->chunkSize(2)
            ->upsertBy(['email'])
            ->run();

        self::assertSame(3, $result->totalRows);
        self::assertSame(2, $result->chunksProcessed);
        self::assertSame(3, User::query()->count());
    }
}
