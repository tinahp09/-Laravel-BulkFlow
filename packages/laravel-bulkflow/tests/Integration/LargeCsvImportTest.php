<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Integration;

use BulkFlow\BulkFlowManager;
use BulkFlow\Tests\Fixtures\Models\User;
use BulkFlow\Tests\TestCase;

final class LargeCsvImportTest extends TestCase
{
    public function test_it_streams_ten_thousand_csv_rows_in_configured_chunks(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bulkflow-large-').'.csv';
        $handle = fopen($path, 'wb');
        fwrite($handle, "name,email\n");

        for ($row = 1; $row <= 10_000; $row++) {
            fputcsv($handle, ['User '.$row, 'user'.$row.'@example.test']);
        }
        fclose($handle);

        $result = $this->app->make(BulkFlowManager::class)
            ->import(User::class)
            ->from($path)
            ->map(['name' => 'name', 'email' => 'email'])
            ->upsertBy(['email'])
            ->chunkSize(1_000)
            ->run();

        self::assertSame(10_000, $result->successfulRows);
        self::assertSame(10, $result->chunksProcessed);
        self::assertSame(10_000, User::query()->count());
    }
}
