<?php

declare(strict_types=1);

namespace BulkFlow\Tests\Integration;

use BulkFlow\BulkFlowManager;
use BulkFlow\Tests\Fixtures\Models\User;
use BulkFlow\Tests\TestCase;

final class ErrorPolicyTest extends TestCase
{
    public function test_fail_fast_stops_after_the_first_invalid_row(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bulkflow-policy-').'.csv';
        file_put_contents($path, "name,email\nBroken,invalid\nValid,valid@example.test\n");

        $result = $this->app->make(BulkFlowManager::class)
            ->import(User::class)
            ->from($path)
            ->map(['name' => 'name', 'email' => 'email'])
            ->validate(['email' => ['email']])
            ->onError('fail-fast')
            ->run();

        self::assertSame(1, $result->totalRows);
        self::assertSame(1, $result->failedRows);
        self::assertSame(0, User::query()->count());
    }

    public function test_stop_on_threshold_stops_after_configured_number_of_failed_rows(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bulkflow-stop-threshold-').'.csv';
        file_put_contents($path, "name,email\nBad,not-an-email\nAlso bad,still-not-an-email\nValid,valid@example.test\n");

        $result = $this->app->make(BulkFlowManager::class)
            ->import(User::class)
            ->from($path)
            ->map(['name' => 'name', 'email' => 'email'])
            ->validate(['email' => ['required', 'email']])
            ->onError('stop-on-threshold')
            ->stopOnErrorCount(2)
            ->run();

        self::assertSame(2, $result->totalRows);
        self::assertSame(2, $result->failedRows);
        self::assertSame(0, $result->successfulRows);
        self::assertDatabaseMissing('users', ['email' => 'valid@example.test']);
    }
}
