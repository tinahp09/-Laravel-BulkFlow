<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Integration;

use BulkFlow\BulkFlowManager;
use BulkFlow\Failure\RowFailureRecord;
use BulkFlow\Tests\Fixtures\Models\User;
use BulkFlow\Tests\TestCase;

final class SyncImportTest extends TestCase
{
    public function test_it_maps_validates_and_upserts_rows_from_a_csv_file(): void
    {
        User::query()->create(['name' => 'نام پیشین', 'email' => 'neda@example.test']);

        $path = tempnam(sys_get_temp_dir(), 'bulkflow-import-').'.csv';
        file_put_contents($path, "نام,ایمیل\nندا,neda@example.test\nآرین,arian@example.test\nبدون ایمیل,invalid\n");

        $result = $this->app->make(BulkFlowManager::class)
            ->import(User::class)
            ->from($path)
            ->map(['نام' => 'name', 'ایمیل' => 'email'])
            ->validate(['email' => ['required', 'email']])
            ->upsertBy(['email'])
            ->run();

        self::assertSame(3, $result->totalRows);
        self::assertSame(2, $result->successfulRows);
        self::assertSame(1, $result->failedRows);
        self::assertSame('ندا', User::query()->where('email', 'neda@example.test')->value('name'));
        self::assertSame(2, User::query()->count());
        self::assertSame(4, $result->failures[0]->rowNumber);
        self::assertSame('validation', $result->failures[0]->type);
        self::assertSame(1, RowFailureRecord::query()->count());
        self::assertDatabaseHas('bulkflow_import_runs', [
            'id' => $result->runId,
            'total_rows' => 3,
            'processed_rows' => 3,
        ]);
    }
}
